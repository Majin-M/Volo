# Contrat d'API — VOLO

[api_specification.md](api_specification.md) liste les endpoints, leurs paramètres et leurs réponses. Ce document explique les **conventions transversales** qui ne se lisent bien nulle part : ce qui vaut pour toutes les routes à la fois, et pourquoi ces choix ont été faits plutôt que d'autres.

---

## 1. Authentification — pourquoi pas de JWT dans `localStorage`

**Le choix** : le jeton JWT est posé dans un cookie `volo_token`, `HttpOnly`, `SameSite=Lax`, `Secure` en production. Il n'est **jamais** accessible au JavaScript.

C'est un revirement par rapport à la conception initiale, qui stockait le jeton en `localStorage` et l'envoyait dans un en-tête `Authorization: Bearer`. Ce schéma est le plus répandu dans les tutoriels ; il est aussi le plus vulnérable.

**Pourquoi il a été abandonné** : `localStorage` est lisible par n'importe quel script s'exécutant sur la page. Une seule faille XSS — une dépendance npm compromise, un champ mal échappé — et le jeton part chez l'attaquant. Un cookie `HttpOnly` n'est pas lisible par un script, même en cas de XSS réussie.

Conséquences concrètes de ce choix, toutes visibles dans le code :

| Conséquence | Où |
|---|---|
| `AuthContext` ne stocke aucun jeton, seulement l'utilisateur | `frontend/src/contexts/AuthContext.jsx` |
| La session est restaurée au montage via `GET /api/auth/me` | idem |
| Chaque appel porte `credentials: 'include'` | `frontend/src/api/api.js` |
| CORS exige `allow_credentials: true` **et** une origine explicite (jamais `*`) | `config/packages/nelmio_cors.yaml` |
| Lexik est configuré pour lire le cookie, pas l'en-tête | `config/packages/lexik_jwt_authentication.yaml` |
| Une protection CSRF devient obligatoire | §2 |

Ce dernier point est le prix du choix, et il n'est pas optionnel.

---

## 2. Protection CSRF — pourquoi le CORS ne suffit pas

Un cookie est envoyé **automatiquement** par le navigateur, y compris quand la requête est déclenchée depuis un autre site. C'est précisément ce qui rend le cookie pratique, et c'est ce qui ouvre la faille : un formulaire hébergé sur `site-malveillant.fr` peut poster vers `volo.fr/api/orders` avec le cookie de la victime.

**Le CORS ne protège pas de ça.** Il empêche le site tiers de *lire* la réponse, pas de *l'envoyer*. Une requête « simple » (formulaire en `application/x-www-form-urlencoded`) ne déclenche aucune requête préliminaire : l'effet de bord — la commande créée — a déjà eu lieu avant tout contrôle CORS.

**La protection en place** : double-submit cookie.

1. À la connexion, `AuthController` pose un second cookie `volo_csrf`, **non** `HttpOnly` — donc lisible par le JavaScript de VOLO.
2. `api.js` lit ce cookie et le renvoie dans un en-tête `X-Csrf-Token` sur tout `POST`/`PUT`/`PATCH`/`DELETE`.
3. `CsrfProtectionSubscriber` compare l'en-tête et le cookie. S'ils diffèrent ou si l'en-tête manque → **403**.

Pourquoi ça marche : un site tiers peut *faire envoyer* le cookie par le navigateur, mais il ne peut pas le *lire* (politique d'origine identique) — donc il ne peut pas fabriquer l'en-tête correspondant.

Exemptions : `/api/auth/login`, `/api/auth/register` et `/api/contact` — aucun cookie CSRF n'existe encore au moment où ces routes sont appelées.

> ✅ **Vérifié le 17/07/2026** — `tests/Security/CsrfProtectionTest.php`, 8 tests. La protection est réelle : `POST /api/orders` sans en-tête → 403, en-tête ≠ cookie → 403, bon jeton → passe. Éprouvé en neutralisant la comparaison du subscriber : 4 tests virent au rouge.
>
> Ce document disait : « Le mécanisme est écrit, sa protection réelle est **supposée**. C'est exactement le genre de sécurité qu'on croit avoir. » Le doute était sain, et la réponse est bonne — mais le test a trouvé autre chose.

### Ce que le test a révélé

**`/api/contact` était cassé.** La route est `PUBLIC_ACCESS` — le formulaire de contact s'adresse aux visiteurs non connectés, c'est sa raison d'être. Mais elle n'était **pas exemptée**, et le cookie `volo_csrf` n'est posé qu'au login/register. Un visiteur anonyme n'avait donc aucun jeton à envoyer : **403**. Côté front, `api.js` fait `if (csrfToken)` — sans cookie, il n'envoie pas l'en-tête et l'utilisateur voit « Erreur API: Forbidden ».

Le formulaire public était inutilisable pour exactement le public auquel il était destiné. Corrigé par exemption : le CSRF défend contre l'usage des identifiants d'une victime **connectée**, en exploitant l'envoi automatique de son cookie. Une route publique qui n'agit au nom de personne n'a rien à y gagner — faire soumettre un message de contact par un tiers à son insu n'apporte aucun bénéfice à un attaquant. Ce qui protège cette route est le rate limiter, et un CAPTCHA si le spam devenait réel.

### L'ordre d'exécution, à connaître

`CsrfProtectionSubscriber` écoute `kernel.request` sans priorité, donc à **0**. Deux écouteurs Symfony passent avant :

| Écouteur | Priorité | Réponse |
|---|---|---|
| `RouterListener` | 32 | Route inconnue → **404**, jamais 403 |
| Firewall | 8 | Non authentifié → **401**, jamais 403 |
| `CsrfProtectionSubscriber` | 0 | → **403** |

Le contrôle CSRF arrive donc **en dernier**. Ce n'est pas un défaut — une route inexistante ne fait rien, une requête non authentifiée est déjà rejetée. Mais il faut le savoir pour tester la bonne chose : un test non authentifié prouverait que le *firewall* fonctionne, pas le CSRF.

> À noter : **l'API n'expose aucune route `PUT`/`PATCH`/`DELETE`.** Les trois verbes figurent dans `UNSAFE_METHODS` du subscriber, mais c'est une garantie prospective — il n'y a rien à protéger avec aujourd'hui.

---

## 3. Deux firewalls, pas un

`config/packages/security.yaml` déclare deux firewalls disjoints :

| Firewall | Périmètre | Mécanisme | Session |
|---|---|---|---|
| `api` | `^/api` | JWT en cookie | **Stateless** |
| `admin` | `^/admin` | `form_login` classique | Session PHP |

Le back-office EasyAdmin est du Twig rendu serveur : il n'a aucune raison de porter un JWT. L'API est stateless : elle n'a aucune raison d'ouvrir une session.

Les fusionner aurait signifié faire porter à l'un le mécanisme conçu pour l'autre. Les séparer permet aussi que le back-office ait sa propre politique (déconnexion, expiration, `remember_me`) sans toucher à l'API.

**Point non évident** : EasyAdmin ne fournit **aucun écran de connexion**. Sans `Admin/SecurityController` et son template, `/admin` renvoie vers une route `admin_login` inexistante → erreur 500. Le formulaire doit inclure `_csrf_token`, exigé par `enable_csrf: true`.

---

## 4. Autorisation — ce que le contrat ne dit pas

L'`access_control` de `security.yaml` couvre le gros grain :

```yaml
- { path: ^/admin/login, roles: PUBLIC_ACCESS }
- { path: ^/admin, roles: ROLE_ADMIN }
- { path: ^/api/products, methods: [GET], roles: PUBLIC_ACCESS }
- { path: ^/api, roles: ROLE_USER }
```

Il ne couvre **pas** le grain fin : « seulement le propriétaire de la ressource ». `GET /api/orders/{id}` avec `ROLE_USER` passe l'`access_control` — rien à ce niveau ne dit que la commande 42 appartient à l'utilisateur qui la demande.

> ⚠️ **Les Voters ne sont pas implémentés** (roadmap 2.5 ⬜ 🟠). Chaque contrôleur doit donc vérifier la propriété à la main. Toute route oubliée est une fuite de données : un client peut lire la commande d'un autre en changeant l'ID dans l'URL.
>
> Un menu masqué côté React n'est **jamais** une protection : c'est du code livré au client, il fait ce que le client veut.

**Le rôle n'est jamais attribuable par HTTP.** `POST /api/auth/register` force `["ROLE_USER"]` et ignore tout `roles` reçu dans le corps. Promouvoir un administrateur passe exclusivement par `php bin/console app:create-admin`. C'est RG11 de [MODELE_DONNEES.md](MODELE_DONNEES.md).

---

## 5. Français en base, anglais dans le code — et l'API suit le code

`convention_de_nommage.md` impose : code en anglais, interface en français.

L'API suit la convention du **code** : clés JSON en `camelCase` anglais (`isAvailable`, `createdAt`, `postalCode`), valeurs d'énumération en anglais (`pending`, `paid`, `shipped`).

Le français n'apparaît que dans deux endroits : les libellés affichés par React, et les `message:` des contraintes de validation Symfony (qui remontent tels quels au client pour être affichés).

Aucune traduction n'est donc nécessaire entre la base et l'API — contrairement à un modèle Merise nommé en français, VOLO nomme ses entités en anglais dès l'origine. Le seul écart potentiel est celui de §6.6 de [MODELE_DONNEES.md](MODELE_DONNEES.md) (`camelCase` vs `snake_case` en colonnes), qui est un problème de casse, pas de langue.

---

## 6. Erreurs et pagination

**Erreurs** : `api_specification.md` documente une enveloppe `{ error: { code, message } }`.

> ⚠️ **Aucun `ExceptionSubscriber` global n'existe.** Une exception non capturée produit donc la forme par défaut de Symfony (`{ "type", "title", "status", "detail" }` en dev, un HTML d'erreur en prod), pas l'enveloppe documentée. Le contrat et l'implémentation divergent silencieusement.
>
> Le front doit aujourd'hui parser deux formats selon le chemin d'erreur emprunté. C'est un bug documentaire autant que technique : la spécification décrit une intention, pas le comportement.

**Pagination** : `?page` et `?limit` (défaut 20, max 50), réponse enveloppée `{ data: [...], pagination: {...} }`.

Le plafond à 50 n'est pas cosmétique : sans lui, `?limit=100000` devient un déni de service à une requête. Il doit être appliqué **côté serveur**, jamais déduit de ce que le front demande.

---

## 7. Ce qui n'est volontairement pas dans le contrat

- **Le SEO.** `SitemapController` répond en XML, pas en JSON. Ce n'est pas une route d'API : elle sert des robots d'indexation, pas la SPA.

  > ⚠️ **`SeoController` n'existe pas.** Ce document, [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §2 et [TECHNOLOGIES.md](TECHNOLOGIES.md) §1 le citent tous les trois comme acquis, avec `IndexHtmlInjector`. Aucune des deux classes n'est dans `backend/src/`. Seul `SitemapController` existe.
  >
  > Conséquence : **les aperçus de partage sociaux ne fonctionnent pas.** WhatsApp, Facebook et LinkedIn n'exécutent pas le JavaScript, donc les balises `<meta og:*>` posées par `react-helmet-async` leur sont invisibles, et rien côté serveur ne les injecte. Le référencement Google, lui, fonctionne (Google exécute le JS). Le dispositif décrit dans ces trois documents est une **intention**, pas une implémentation.
- **Le back-office.** `/admin/*` est du Twig rendu serveur. Aucune de ces routes n'appartient au contrat d'API.
- **Le webhook Stripe.** `POST /api/webhooks/stripe` **devra** être exempté du firewall `api` (Stripe n'a pas de session VOLO) et du CSRF (Stripe ne peut pas lire `volo_csrf`). Sa protection reposera exclusivement sur la **vérification de signature** du payload, pas sur un cookie.

  Ce point est à décider **avant** d'écrire le webhook : un `access_control` mal posé le rendrait soit inaccessible (401 pour Stripe), soit ouvert à tous. Voir [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2.

---

## 8. Ce qui manque à ce contrat

Il n'existe **pas de fichier OpenAPI** pour VOLO. `api_specification.md` est du Markdown écrit à la main : rien ne garantit qu'il décrive l'API réelle, et rien ne le vérifie.

Un `openapi.yaml` apporterait trois choses que le Markdown ne peut pas donner :

1. Une documentation interactive générée (Swagger UI / Redoc) plutôt qu'un tableau à relire.
2. Des **tests de contrat** : vérifier automatiquement que les réponses réelles respectent les schémas déclarés, et faire échouer la CI si l'API dérive de sa documentation.
3. Un serveur mock, pour que le front travaille sans back démarré.

Le point 2 est le vrai argument : aujourd'hui, `api_specification.md` peut mentir sans que rien ne le signale.

> ⚠️ **Et il ment beaucoup plus que ce paragraphe ne le laissait croire.** §6 donnait l'enveloppe d'erreur comme « un exemple avéré ». L'inventaire du 17/07/2026 a montré l'ampleur réelle : sur ~20 endpoints documentés, **11 existent**. Toute la section Administration (10 routes), toutes les écritures sur les produits, les routines, `GET /api/orders/{id}`, `/api/users/me` — **404**. L'enveloppe d'erreur était le plus petit des écarts.
>
> **Le projet n'utilise pas API Platform.** Ce paragraphe proposait de générer le contrat « depuis les attributs des entités » : la brique ne figure pas dans `composer.json`, les contrôleurs sont écrits à la main. Cette voie n'existe pas — voir [architecture.md](architecture.md) §1.

**À ajouter à la roadmap.** Sans API Platform, un `openapi.yaml` devra être écrit à la main *ou* généré via `nelmio/api-doc-bundle` (qui sait lire des contrôleurs classiques). Le coût est réel ; ce qu'il achète est le seul mécanisme qui empêcherait la dérive constatée de se reproduire.

> **Ce que cette révision a démontré, et qui est l'argument décisif** : la dérive n'a pas été détectée par une relecture attentive — plusieurs documents avaient été relus, se citaient mutuellement et se croyaient à jour. Elle a été détectée par une commande, `debug:router`, confrontée au Markdown. **Un contrat qu'aucune machine ne compare au code est une intention, pas un contrat.**
