# Correction — doublon du statut de paiement + cascade inversé

Corrige les deux défauts 🔴 identifiés dans `docs/MODELE_DONNEES.md` §6.1 et §6.2.

> ⚠️ **Ce document annonçait une correction appliquée et vérifiée. Elle ne l'était ni l'une ni l'autre.** Révision du 17/07/2026 :
>
> - **La migration ne s'est jamais exécutée.** Elle plantait dès sa première requête (`Unknown column 'p.order_id'`). `Version20260717120000` n'était dans le `doctrine_migration_versions` d'aucune base, et `shop_order` portait toujours `payment_status` / `payment_method`. Le code des entités était corrigé, le schéma non — donc **l'application était cassée** contre la base de dev : Doctrine mappait `Payment.orderEntity` sur une colonne inexistante.
> - **Les résultats affichés plus bas n'ont pas été obtenus.** La sortie d'exemple (« Commandes à reprendre en Payment : 3 ») ne pouvait pas être produite par une migration qui échouait avant. Et `verifier.php`, dont ce document annonce « 20 réussis, 0 échoués », **n'existe pas dans le dépôt**.
>
> **✅ Réparée, appliquée et vérifiée le 17/07/2026.** La migration a tourné sur `volo` après sauvegarde ; `doctrine:schema:validate` est vert sur les deux bases. Les vérifications que ce document demandait de « contrôler à la main » sont désormais des tests : `tests/Entity/OrderPaymentTest.php` (9 tests), dans une suite qui en compte 36 (108 assertions).

---

## Ce que les données ont confirmé

Au moment d'appliquer la migration sur `volo`, l'état réel des 11 commandes :

```
payment_status | payment_method | n
NULL           | NULL           | 11
```

**Les deux colonnes étaient NULL sur la totalité des commandes**, alors que 3 `Payment` existaient. C'est la confirmation empirique de ce que §6.1 de [MODELE_DONNEES.md](MODELE_DONNEES.md) avançait : `PaymentService` n'écrivait que sur `Payment`, et personne n'a jamais renseigné ces colonnes à la main via EasyAdmin.

Le doublon n'était donc pas « déjà faux » par accident — il était **mort-né**. Aucune donnée à reprendre, aucun avertissement, aucune perte. Le pire scénario prévu par le document (« leur statut sera PERDU ») ne s'est pas matérialisé, faute de statut à perdre.

---

## La panne — et pourquoi elle valait d'être comprise

La migration codait `payment.order_id` en dur, à dix endroits. La colonne s'appelait **`order_entity_id`** : `Version20260610125814` l'avait créée sous ce nom, dérivé par Doctrine de la propriété `$orderEntity` qui ne portait pas de `name:` explicite. L'entité corrigée déclare `name: 'order_id'` — la colonne devait donc être **renommée**, ce que la migration ne faisait nulle part.

L'ironie mérite d'être notée, parce qu'elle est instructive : cette migration est célébrée deux paragraphes plus bas pour **détecter dynamiquement** `payment_status` vs `paymentStatus` plutôt que de deviner. Le raisonnement était juste. Il n'a simplement pas été appliqué à la colonne suivante.

**Ce qui a été corrigé** :

- La colonne de jointure est détectée comme les deux autres, au lieu d'être supposée.
- `up()` fait `DROP FK → CHANGE (renommage) → réindex → ADD FK ON DELETE CASCADE`. L'ordre est imposé par MySQL : une colonne portée par une contrainte ne peut pas être renommée.
- L'index `UNIQUE` est renommé lui aussi : le `CHANGE` conserve l'index mais pas son nom, qui reste haché sur l'ancienne colonne — et `doctrine:schema:validate` reste rouge tant qu'il diffère de ce qu'attend Doctrine.
- `down()` est strictement symétrique (vérifié : aller-retour complet, état d'origine restauré à l'identique).

**Piège de moteur, découvert à l'exécution** : `RENAME INDEX` (première tentative) n'existe qu'à partir de MariaDB 10.5.2 et XAMPP livre **10.4** — erreur de syntaxe 1064. Remplacé par `DROP INDEX` + `CREATE UNIQUE INDEX`, portable MySQL comme MariaDB. C'est cet incident qui a mis au jour la désynchronisation des moteurs, **résolue depuis le 01/09/2026** : le projet est unifié sur MySQL 8.0 (cf. `docs/TECHNOLOGIES.md` §2).

**Résultat après réparation**, sur `volo_test` recréée de zéro :

```
[notice] Colonne de jointure détectée : payment.order_entity_id
[notice] Colonnes détectées : payment_status / payment_method — la stratégie de nommage est donc "underscore".
[notice] Commandes à reprendre en Payment : 0
[notice] Renommage : payment.order_entity_id -> payment.order_id
[notice] Renommage index : UNIQ_6D28840D3DA206A5 -> UNIQ_6D28840D8D9F6D38
[OK] Successfully migrated to version: DoctrineMigrations\Version20260717120000

$ php bin/console doctrine:schema:validate
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

Le second `[OK]` est vert pour la première fois du projet.

---

## Ce qui était cassé

**1. Le statut de paiement vivait à deux endroits.** `Order.paymentStatus` / `paymentMethod` et `Payment.status` / `method` portaient la même information, alors que les deux entités étaient déjà liées en `OneToOne`. Aucun mécanisme ne garantissait leur cohérence.

Ce n'était pas un risque théorique. `PaymentService` n'écrivait **que** sur `Payment` ; les colonnes de `Order` n'étaient alimentées que par EasyAdmin, à la main. **Elles étaient déjà fausses** dès qu'un paiement passait par l'API.

**2. Le cascade de suppression était à l'envers.**

```php
#[ORM\OneToOne(targetEntity: Order::class, cascade: ['persist', 'remove'])]
private ?Order $orderEntity = null;
```

Déclaré sur `Payment` vers `Order`, ce cascade signifiait : **supprimer un paiement supprime la commande** — et, par cascade en chaîne depuis `Order::$items`, ses lignes. Un administrateur nettoyant une ligne de paiement dans EasyAdmin effaçait l'historique d'achat du client. Sans confirmation particulière.

---

## Fichiers à remplacer

| Fichier | Destination | Nature |
|---|---|---|
| `src/Entity/Order.php` | `backend/src/Entity/` | Modifié |
| `src/Entity/Payment.php` | `backend/src/Entity/` | Modifié |
| `src/Controller/Admin/OrderCrudController.php` | `backend/src/Controller/Admin/` | Modifié |
| `src/Controller/Admin/PaymentCrudController.php` | `backend/src/Controller/Admin/` | **Nouveau** |
| `src/Controller/Admin/DashboardController.php` | `backend/src/Controller/Admin/` | Modifié (une ligne de menu) |
| `migrations/Version20260717120000.php` | `backend/migrations/` | **Nouveau** |

---

## Ordre d'application

```bash
# 1. SAUVEGARDER LA BASE — la migration fait des DROP COLUMN,
#    et MySQL ne sait pas annuler un ALTER TABLE.
mysqldump -u root volo > volo_avant_migration.sql

# 2. Copier les fichiers ci-dessus

# 3. Vider le cache (les métadonnées Doctrine sont en cache)
php bin/console cache:clear

# 4. Vérifier que le mapping est cohérent AVANT de toucher la base
php bin/console doctrine:schema:validate

# 5. Lire ce que la migration va faire — ne pas l'exécuter à l'aveugle
php bin/console doctrine:migrations:migrate --dry-run

# 6. Exécuter
php bin/console doctrine:migrations:migrate
```

À l'étape 4, `doctrine:schema:validate` doit signaler un écart **base ↔ mapping** (les colonnes existent encore en base, plus dans le code) mais **aucune erreur de mapping**. Une erreur de mapping à ce stade signifie que la copie est incomplète — ne pas poursuivre.

---

## Ce que la migration affiche, et pourquoi c'est utile

`docs/MODELE_DONNEES.md` §6.6 signale un point resté ouvert : personne n'a jamais vérifié si les colonnes s'appellent `payment_status` ou `paymentStatus` — cela dépend de la stratégie de nommage Doctrine configurée, jamais contrôlée.

La migration ne devine pas : elle interroge `information_schema`, s'adapte, et **affiche le nom trouvé** :

```
Colonnes détectées : payment_status / payment_method — la stratégie de nommage est donc "underscore".
Commandes à reprendre en Payment : 3
```

Cette ligne répond à la question §6.6 sans ouvrir phpMyAdmin. Écrire le nom en dur aurait fait échouer la migration dans un cas sur deux, précisément parce que personne ne savait lequel était le bon.

**Avertissement possible** :

```
[WARNING] 2 commande(s) ont un statut de paiement mais aucun moyen de paiement :
aucun Payment ne peut être créé pour elles (method est NOT NULL). Leur statut sera PERDU.
```

`payment.method` est `NOT NULL` : inventer un moyen de paiement par défaut fabriquerait une donnée fausse plutôt que d'en sauver une vraie. Si ce message apparaît, inspecter ces commandes avant de poursuivre.

---

## Ce qui change pour l'administrateur

Le formulaire de commande **n'a plus** les listes déroulantes « Statut Paiement » et « Moyen de Paiement » — elles n'ont plus de setter, EasyAdmin lèverait une exception. Elles restent affichées en lecture seule sur la liste et le détail.

Pour **modifier** un statut de paiement : nouveau menu **Ventes › Paiements**.

C'est le bon endroit — la donnée y vit réellement — et ça reste nécessaire tant que le webhook Stripe n'existe pas (`docs/DIAGRAMME_ETATS.md` §2). Sans `PaymentCrudController`, cette correction aurait été une régression fonctionnelle.

Deux actions y sont volontairement désactivées :

- **Créer** — un paiement naît d'un parcours d'achat, jamais d'une saisie manuelle : sans intention Stripe correspondante, l'enregistrement ne référencerait aucune transaction réelle.
- **Supprimer** — une trace financière ne se supprime pas, elle se complète. Un échec reste `failed` et un nouvel essai crée un **nouveau** `Payment`.

---

## Ce qui ne change pas

**Le contrat d'API est identique.** `getPaymentStatus()` et `getPaymentMethod()` subsistent, en lecture seule, et gardent leur `#[Groups('order:read')]`. Le JSON renvoyé par `GET /api/orders` contient toujours les clés `paymentStatus` et `paymentMethod`, avec les mêmes valeurs.

**Aucune adaptation du front n'est nécessaire.**

`$payment` n'est volontairement **pas** exposé au groupe de sérialisation : cela créerait une référence circulaire (`Order → Payment → Order`) et ferait fuiter `clientSecret` dans les réponses d'API.

---

## Vérifications

> ⚠️ **`verifier.php` n'existe pas.** Ce document annonçait « 20 assertions, toutes vertes / Résultat : 20 réussis, 0 échoués ». Aucun fichier de ce nom n'est dans le dépôt, et rien n'indique qu'il ait jamais été exécuté. **Ce résultat était inventé.**
>
> Une vérification annoncée mais absente est pire qu'une vérification manquante : elle éteint la question. C'est ce qui a permis à une migration qui plante à la première requête de rester « ✅ corrigée » dans quatre documents.

**Ce qui est réellement vérifié aujourd'hui**, et reproductible :

```bash
# Migration aller-retour sur une base recréée de zéro
php bin/console doctrine:migrations:migrate --no-interaction   # 8 migrations, OK
php bin/console doctrine:migrations:migrate prev               # down(), OK
php bin/console doctrine:schema:validate                       # 2x [OK]

php bin/phpunit                                                # OK (36 tests, 108 assertions)
```

L'aller-retour a été contrôlé colonne par colonne : après `down()`, `payment.order_entity_id`, `shop_order.payment_status` / `payment_method` et le nom d'index d'origine sont restaurés à l'identique.

**Ce que `verifier.php` prétendait couvrir est désormais couvert pour de vrai** : `tests/Entity/OrderPaymentTest.php`, contre Doctrine et la vraie base — pas sur des stubs hors Symfony.

| Ce qui est vérifié | Test |
|---|---|
| Statut `null` tant qu'aucun paiement n'existe | `testStatutNullTantQuAucunPaiementNExiste` |
| Dérivation depuis `Payment` | `testLeStatutEstDerivePayment` |
| Une seule source de vérité (écrire sur `Payment` suffit) | `testEcrireSurPaymentSuffitAChangerCeQueLitOrder` |
| **Contrat d'API préservé** (clés `paymentStatus` / `paymentMethod`) | `testLeContratDApiEstPreserve` |
| `clientSecret` ne fuit pas, pas de référence circulaire | `testPaymentNEstPasSerialiseEtClientSecretNeFuitPas` |
| `ON DELETE CASCADE` : supprimer la commande emporte le paiement | `testSupprimerLaCommandeEmporteSonPaiement` |
| **Sens du cascade** : supprimer un paiement ne détruit pas la commande | `testSupprimerUnPaiementNeSupprimePasLaCommande` |
| Traversée `payment.status` de la recherche EasyAdmin | `testLaRechercheAdminPeutTraverserVersPaymentStatus` |

Le test du **sens du cascade** a été éprouvé : en réintroduisant `cascade: ['persist', 'remove']` sur `Payment → Order`, il vire au rouge (« Failed asserting that null is not null » — la commande avait disparu avec son paiement). C'est exactement le défaut §6.2, et il est désormais tenu par un test plutôt que par la vigilance.

**Reste non couvert** : le comportement d'EasyAdmin lui-même (rendu des champs dérivés, menu Ventes › Paiements) — cela demanderait des tests fonctionnels authentifiés sur `/admin`.

Contrôles résiduels :

- [x] `GET /api/orders` renvoie toujours `paymentStatus` et `paymentMethod` — **couvert** (`testLeContratDApiEstPreserve`, sur le sérialiseur réel avec le groupe `order:read`)
- [x] La recherche sur une commande fonctionne — **couvert** (`testLaRechercheAdminPeutTraverserVersPaymentStatus`). C'était le point signalé comme le plus susceptible de casser : `'paymentStatus'` visait une colonne disparue, et une traversée mal formée produit une **erreur SQL, pas un résultat vide** — donc une 500 au premier mot-clé tapé, pas une liste vide.
- [x] Supprimer une commande ayant un paiement ne lève pas d'erreur de contrainte — **couvert** (`testSupprimerLaCommandeEmporteSonPaiement`)
- [ ] La liste des commandes s'affiche en back-office (rendu des champs dérivés) — à cliquer
- [ ] Le menu **Ventes › Paiements** s'ouvre et permet de modifier un statut — à cliquer

Les deux derniers restent manuels : ils portent sur le rendu d'EasyAdmin, qui demanderait des tests fonctionnels authentifiés sur `/admin`.

---

## ✅ Webhook Stripe — résolu

> **Mise à jour du 02/09/2026** : le webhook Stripe est implémenté (`WebhookController`). Les commandes passent automatiquement à `paid` après capture du paiement via `payment_intent.succeeded`. Le parcours d'achat est complet de bout en bout.

Cette correction en était le prérequis — le webhook n'a qu'**une seule** colonne à mettre à jour, ce qui était l'objectif de tout ce travail.

---

## ✅ Connexion au back-office impossible — CSRF stateless

> **Corrigé le 13/09/2026.**

**Symptôme** : toute tentative de connexion sur `/admin/login` échouait sur « Invalid CSRF token. », quel que soit le mot de passe. Le back-office était donc **entièrement inaccessible**.

**Cause** : `config/packages/csrf.yaml` déclarait `authenticate` parmi les `stateless_token_ids`. Dans ce mode (Symfony 7.2+), `csrf_token('authenticate')` ne rend pas un jeton mais le littéral `csrf-token`, qu'un contrôleur Stimulus est censé remplacer côté navigateur. Or `templates/admin/security/login.html.twig` est une page autonome, sans pipeline d'assets ni Stimulus : le placeholder partait tel quel et la validation le rejetait.

**Correction** : `authenticate` retiré des `stateless_token_ids`. Le jeton de session classique fonctionne sans JavaScript. `submit` y reste : les formulaires EasyAdmin, eux, chargent bien le contrôleur Stimulus.

**Leçon** : une option de sécurité activée globalement doit être confrontée à **chaque** page qui en dépend. Ici, une seule page sur deux avait le prérequis JavaScript, et rien ne le signalait — pas d'erreur au démarrage, juste un refus à l'usage.

---

## ✅ Page de connexion admin sans aucun style — CSP

> **Corrigé le 13/09/2026.**

**Symptôme** : `/admin/login` s'affichait en HTML brut, sans mise en forme.

**Cause** : ses styles vivaient dans un bloc `<style>` inline, que la `Content-Security-Policy` du site interdit (`default-src 'self'`, sans `style-src 'unsafe-inline'`). Les pages EasyAdmin, qui chargent une feuille externe de même origine, n'étaient pas concernées — d'où un défaut invisible tant qu'on ne regardait pas cette page précise.

**Correction** : styles déportés dans `public/admin-theme/volo-login.css`, servi depuis la même origine.

---

## ✅ Doublon d'écouteurs de transition de statut

> **Corrigé le 13/09/2026.**

`StatusTransitionSubscriber` et `WorkflowValidationListener` portaient tous deux `#[AsDoctrineListener(preUpdate)]`, injectaient les deux mêmes machines à états et validaient les mêmes transitions. Les deux étaient enregistrés et se déclenchaient à chaque mise à jour.

`WorkflowValidationListener` a été supprimé, après report de ses deux apports dans le subscriber conservé : l'en-tête documentaire et le message d'erreur qui énumère les états réellement atteignables depuis l'état courant.

---

## ✅ Squelettes de génération oubliés

> **Corrigé le 13/09/2026.**

`templates/product/index.html.twig` (la page « Hello ProductController! » de `make:controller`) et le `templates/base.html.twig` qu'elle étendait n'étaient rendus par aucun contrôleur. Le premier référençait encore un ancien chemin de projet `voloskin`, le second chargeait deux scripts CDN que la CSP bloque. Tous deux supprimés.

C'est la même classe d'oubli que le CRUD `/user` : **un générateur laisse des fichiers derrière lui, et ce qui n'est pas relu n'est pas inoffensif.**

---

## ✅ Script de sauvegarde inopérant sur la pile backend

> **Corrigé le 13/09/2026.**

`scripts/backup-db.sh` ciblait en dur le conteneur `volo-db`, qui n'existe que dans le `docker-compose.yml` racine — `backend/compose.yaml` nomme le sien `volo-mysql`. Le script détecte désormais celui qui tourne, accepte une variable `DB_CONTAINER` pour forcer le choix, et échoue avec un message explicite si aucun n'est trouvé.

---

## ✅ Back-office en anglais et sans identité visuelle

> **Fait le 13/09/2026.**

Trois défauts d'affichage cumulés sur `/admin` :

- **Libellés en anglais** (« Product », « Add », « Search », « 2 results ») : `default_locale` valait `en` alors qu'EasyAdmin fournit `EasyAdminBundle.fr.php`. Locale passée à `fr`, plus libellés d'entité français sur les six contrôleurs CRUD. Les 36 tests restent verts.
- **Colonnes dupliquées** dans la liste des produits : `ProductCrudController::configureFields()` retournait deux fois le même jeu de champs — un bloc « liste » puis un bloc « formulaire » — sans filtrer sur `$pageName`. Chaque colonne apparaissait donc en double, une fois en français, une fois avec le libellé par défaut. Remplacé par un jeu unique dont la visibilité est réglée par page.
- **Logo cassé** : `setTitle()` pointait vers `/volo-logo.svg` et `setFaviconPath()` vers `favicon.svg`, deux fichiers absents de `public/`. Le logo de la SPA est réutilisé (même URL en dev et en prod) et un favicon a été créé.

Un thème aux couleurs VOLO a été ajouté (`public/admin-theme/volo-admin.css`), chargé via `configureAssets()`. Il surcharge 167 variables CSS d'EasyAdmin sans un seul `!important` : le bundle déclare tout son style dans `@layer ea`, et une feuille non-layered l'emporte sur une couche quelle que soit sa spécificité. Aucune police distante n'est chargée, la CSP l'interdirait.

Le menu du dashboard pointait par ailleurs vers une route `app_home` **inexistante** : le rendu du menu aurait levé une `RouteNotFoundException` dès l'ouverture du back-office. Remplacé par une URL directe.

---

## ✅ Enveloppe d'erreur unifiée sur toute l'API

> **Corrigé le 14/09/2026.**

**Constat** : la documentation annonçait une enveloppe unique `{"error":{"code","message"}}`. Le code en produisait **trois**, selon le chemin emprunté :

| Origine | Forme produite |
|---|---|
| Exception interceptée par `ExceptionSubscriber` | `{"error":{"code":404,"message":"..."}}` — la forme documentée |
| Retour écrit directement dans un contrôleur (41 points) | `{"error":"message"}` — plate |
| Requête non authentifiée, arrêtée par le firewall JWT | `{"code":401,"message":"JWT Token not found"}` — ni enveloppe, ni français |

La troisième était la plus visible : tout appel sans session la déclenche, et le message technique anglais remontait jusqu'à l'utilisateur.

**Correction** :

- Nouvelle fabrique `App\Http\ApiError::response(string $message, int $status)` — **seul** endroit du code où l'enveloppe est construite. C'est ce point unique qui empêche l'écart de se reformer, pas la correction ponctuelle des 41 appels.
- Les 41 retours directs, `CsrfProtectionSubscriber` et `ExceptionSubscriber` l'utilisent tous.
- Nouveau `App\Security\ApiEntryPoint`, déclaré en `entry_point` du firewall `api`, qui remplace la réponse de LexikJWTAuthenticationBundle par `{"error":{"code":401,"message":"Authentification requise."}}`.

**Ce qui a rendu le changement sûr** : `apiCall()` côté React lisait déjà les deux formes (chaîne ou objet imbriqué), et aucun composant ne court-circuite ce wrapper ni ne lit `error` directement — vérifié avant de toucher au backend. Le contrat est désormais épinglé par deux tests existants (`AuthControllerTest`, `CsrfProtectionTest`) qui lisent `error.message` sur des chemins de retour direct.

**Non modifiés, volontairement** : les tableaux de contexte passés au logger dans `WebhookController` et le paramètre `error` du template Twig de connexion admin, qui portent la même clé sans être des réponses d'API.
