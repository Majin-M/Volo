# Stratégie de tests

> ⚠️ **Ce document décrit très majoritairement une cible.** Mais son affirmation d'ouverture — « VOLO n'a aucun test automatisé » — **était fausse** : `backend/tests/Controller/AuthControllerTest.php` existait déjà, tout comme `phpunit.dist.xml`. La tâche 2.15 de [roadmap.md](roadmap.md) est ⬜ 🟠, à raison : 3 tests sur un seul contrôleur.
>
> **État au 17/07/2026** : PHPUnit 13, **26 tests, 88 assertions**, verts à cache chaud comme à froid. Répartis sur quatre fichiers : `AuthControllerTest` (inscription, cookies), `OrderPaymentTest` (dérivation du statut, contrat d'API, cascade), `CsrfProtectionTest` (double-submit), `ContactNotificationTest` (persistance + notification email). Aucun test front (Vitest absent). PHPStan `level: max` présent, mais aucune CI ne l'exécute.
>
> Ce document existe pour deux raisons : dire quoi écrire quand on s'y mettra, et **nommer précisément ce qui est aujourd'hui non vérifié** — parce que « ça marche quand je clique » n'est pas une vérification.
>
> **Trois obstacles ont dû être levés pour que ces 3 tests tournent** — à connaître avant d'en écrire d'autres :
>
> 1. **La base `volo_test` n'existait pas** (Doctrine ajoute le suffixe `_test` via `dbname_suffix`). Il a fallu la créer à la main : `doctrine:database:create` échoue ici, il tente de se connecter à la base avant de la créer.
> 2. **La migration `Version20260717120000` était cassée** et n'avait jamais pu s'appliquer nulle part — donc aucun schéma de test conforme n'était possible avant de la réparer.
> 3. **`.env.local` n'est jamais chargé en environnement `test`.** Symfony l'ignore par conception, pour que les tests donnent le même résultat chez tout le monde. Une variable définie uniquement dans `.env.local` est absente des tests — c'est voulu, et ça surprend une fois.

Il s'appuie sur ce qui a déjà été conçu : les interfaces de [DIAGRAMME_CLASSES.md](DIAGRAMME_CLASSES.md) rendent le mock possible, [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) donne les transitions à couvrir, [CONTRAT_API.md](CONTRAT_API.md) donne ce qu'il faut valider.

---

## 1. Ce qui est aujourd'hui non vérifié

C'est la section la plus importante du document. Chacun de ces mécanismes est **écrit**, aucun n'est **prouvé** :

| Mécanisme | Ce qu'on croit | Ce qu'on sait |
|---|---|---|
| Protection CSRF | Un `POST /api/orders` sans `X-Csrf-Token` → 403 | ✅ **Testé** — `CsrfProtectionTest`, 8 tests |
| Rate limiting | La 6ᵉ tentative → 429 | ✅ **Vérifié** — constaté à l'usage (voir ci-dessous) |
| Hachage EasyAdmin | Le mot de passe créé en back-office est bien haché | ⚠️ Toujours jamais vérifié en base |
| Cookie `HttpOnly` | Le JWT est inaccessible au JS | ✅ **Testé** — `testRegister_Success` |
| Autorisation par ressource | Un client ne lit pas la commande d'un autre | 🟡 **Sans objet aujourd'hui** — voir ci-dessous |
| Plafond de pagination | `?limit=100000` est ramené à 50 | ⚠️ Jamais testé, mais le code existe (`ProductController:53`) |

**Sur le rate limiting** : il fonctionne. On l'a appris sans le vouloir — en relançant la suite plusieurs fois, `register_attempts` (5/heure) a rendu des 429 et fait échouer les tests. `setUp()` réinitialise désormais les compteurs. Le limiteur n'est délibérément **pas** neutralisé en environnement test : le neutraliser rendrait impossible de tester le 429 lui-même (§5).

**Sur l'autorisation par ressource** : cette ligne disait « probablement faux » et désignait `GET /api/orders/{id}`. Vérification faite, **cette route n'existe pas** — le test proposé en §10 n'aurait donc rien pu révéler. `GET /api/orders` ne renvoie que les commandes de l'utilisateur courant (`findByUser`). L'absence de Voters reste un risque réel, mais il ne se matérialise nulle part aujourd'hui, faute d'endpoint exposant une ressource par identifiant.

> C'est la leçon la plus utile de cette révision : **ce document désignait la mauvaise porte.** Pendant qu'il surveillait `GET /api/orders/{id}`, un CRUD Twig anonyme traînait sur `/user` et permettait à quiconque de se créer un compte `ROLE_ADMIN`, mot de passe en clair. Aucun test ne le couvrait, et aucune ligne de ce tableau ne le mentionnait. Un raisonnement sur ce qui est *probablement* cassé ne remplace pas un inventaire de ce qui est *réellement* exposé — `debug:router` confronté à `access_control` l'aurait montré en une minute.

**Cette section reste un plan de travail, mais court** : deux mécanismes seulement restent non prouvés par un test — le **hachage EasyAdmin** (vérifié une fois à la main après correction, jamais figé) et le **plafond de pagination** (le code existe, `ProductController:53`, aucun test ne l'exerce). Le rate limiting est constaté à l'usage mais mériterait aussi son test dédié (§5). Les trois autres lignes sont désormais couvertes.

---

## 2. Pyramide

```mermaid
flowchart TB
    E2E["E2E — ~10%<br/>Parcours d'achat complet"]
    INT["Intégration — ~20%<br/>Endpoints, repositories, sécurité"]
    UNIT["Unitaires — ~70%<br/>Services, validators, gateways"]
    E2E --- INT --- UNIT
```

Plus on descend, plus les tests sont rapides et isolés. C'est ce que le DIP de [DIAGRAMME_CLASSES.md](DIAGRAMME_CLASSES.md) §6 rend possible — mais **seulement sur le paiement** : `PaymentService` dépend d'une interface, donc testable sans réseau. `OrderService` dépend directement de `OrderRepository`, donc il lui faudra une vraie base. La pyramide de VOLO sera donc plus lourde en intégration qu'un projet où DIP est appliqué partout. C'est la contrepartie assumée de l'arbitrage.

---

## 3. Tests unitaires (PHPUnit)

Aucune dépendance réelle. Exemples concrets à couvrir :

| Classe | Ce qu'on vérifie | Dépendance mockée |
|---|---|---|
| `PasswordValidator` | `"password"` refusé, `"P@ssw0rd"` accepté, `"Aa1!"` refusé (trop court) | Aucune — test pur |
| `PaymentGatewayResolver` | `CARD` → `StripePaymentGateway` ; une méthode sans passerelle → exception | Deux faux gateways |
| `PaymentService` | Le montant envoyé à la passerelle vaut bien `order.total × 100` en centimes | `FakePaymentGateway` |
| `PaymentService` | Une exception de la passerelle ne laisse pas de `Payment` en base à moitié écrit | `FakePaymentGateway` en échec |
| `OrderService` | Le `total` est recalculé serveur — un total falsifié dans la requête est ignoré (RG4) | `EntityManager` mocké |
| `CsrfProtectionSubscriber` | En-tête absent → 403 ; en-tête ≠ cookie → 403 ; `/api/auth/login` → passe | `Request` construite à la main |

La ligne `OrderService` / RG4 est la plus importante : c'est la règle qui empêche un client de s'offrir une commande à 0,01 €. Elle est aujourd'hui appliquée par du code que personne n'a jamais mis à l'épreuve.

---

## 4. Tests des machines à états

[DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §4 le dit : **aucune transition n'est contrainte** dans le code. Il n'y a donc rien à tester aujourd'hui — un test de transition passerait toujours, puisque tout est permis.

L'ordre est donc : **d'abord implémenter les contraintes, puis les tester**. Une fois fait, le test s'écrit comme un produit cartésien :

```php
// Structure — pas une implémentation finale
public static function transitionsAutorisees(): array
{
    return [
        [OrderStatus::PENDING, OrderStatus::PAID],
        [OrderStatus::PENDING, OrderStatus::CANCELLED],
        [OrderStatus::PAID, OrderStatus::SHIPPED],
        [OrderStatus::PAID, OrderStatus::CANCELLED],
        [OrderStatus::SHIPPED, OrderStatus::DELIVERED],
        [OrderStatus::SHIPPED, OrderStatus::CANCELLED],
    ];
}

#[DataProvider('toutesLesPairesDeStatuts')]
public function testTransition(OrderStatus $depuis, OrderStatus $vers): void
{
    $order = (new Order())->setStatus($depuis);
    $autorisee = in_array([$depuis, $vers], self::transitionsAutorisees(), true);

    if ($autorisee) {
        $this->expectNotToPerformAssertions();
        $order->transitionner($vers);
    } else {
        $this->expectException(TransitionInterditeException::class);
        $order->transitionner($vers);
    }
}
```

L'intérêt de la forme cartésienne : ce test **échoue automatiquement** si quelqu'un ajoute un statut à `OrderStatus` sans mettre à jour le tableau des transitions autorisées. Il force l'énumération, le diagramme et le code à rester synchronisés — exactement le type de dérive constatée en §5 de `DIAGRAMME_ETATS.md` (`REFUNDED`/`CANCELLED` présents dans l'énumération, jamais posés nulle part).

---

## 5. Tests d'intégration

Contre une **vraie** base (SQLite en mémoire, ou MySQL éphémère), via `WebTestCase` :

- **`POST /api/orders` sans `X-Csrf-Token` → 403.** Le test qui manque le plus.
- **6ᵉ `POST /api/auth/login` en échec → 429.** Idem.
- **`GET /api/orders/{id}` d'un autre utilisateur → 403.** Écrira le test *et* révélera qu'il échoue — c'est le but.
- **`POST /api/auth/register` avec `{"roles": ["ROLE_ADMIN"]}` → l'utilisateur créé a `["ROLE_USER"]`.** Vérifie RG11.
- **Contrainte `UNIQUE` sur `user.email`** — deux inscriptions au même e-mail. Un mock ne le détecterait pas : il faut une vraie tentative d'insertion.
- **`GET /api/products?limit=100000` → 50 résultats maximum.**
- **`/admin` sans `ROLE_ADMIN` → redirection vers `/admin/login`**, pas une page rendue.

Ces tests ne vérifient pas de la logique métier isolée : ils vérifient le **routage, les firewalls, les subscribers, la sérialisation** — c'est-à-dire précisément la sécurité, qui vit dans la plomberie Symfony et pas dans les services.

---

## 6. Tests end-to-end (Playwright)

Trois parcours prioritaires, correspondant aux diagrammes de séquence existants :

1. **Inscription → connexion → déconnexion.** Y compris le rate limiting réel (blocage après 5 échecs).
2. **Catalogue → détail produit → panier → connexion → commande.** Le parcours de conversion complet.
3. **Connexion admin → création d'un produit → visibilité côté client.**

Le parcours 2 s'arrête aujourd'hui à une `alert()` : la page de confirmation n'existe pas (roadmap 3.13 ⬜ 🔴). Le test E2E ne pourra donc pas assurer sa dernière étape avant que cette page soit écrite.

Le paiement Stripe complet n'est pas testable E2E tant que le webhook n'existe pas — [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2.

---

## 7. Front-end (Vitest + React Testing Library)

Cohérent avec l'outillage Vite déjà présent. Composants prioritaires, dans cet ordre :

| Composant | Pourquoi |
|---|---|
| `CartContext` | Touche à l'argent. Ajout, suppression, quantités, persistance `localStorage`, calcul du total |
| `AuthContext` | Restauration de session via `/auth/me`, déconnexion, état de chargement |
| `CheckoutPage` | Validation avant envoi, gestion des erreurs de paiement |
| `LoginPage` / `RegisterPage` | Validation, affichage du blocage 429, confirmation de mot de passe |
| `validators.js` | Fonctions pures — les moins chères à tester, à écrire en premier |

Pas d'objectif de couverture globale : la priorité va à ce qui touche l'argent et l'authentification. Mais tout nouveau composant critique doit arriver avec son test.

**Piège à connaître** : `CartContext` s'initialise directement depuis `localStorage` (initialisation paresseuse du `useState`, pour éviter un scintillement au montage). Un test qui ne nettoie pas `localStorage` entre deux cas verra le panier du test précédent. `beforeEach(() => localStorage.clear())` n'est pas optionnel.

---

## 8. Couverture cible

| Périmètre | Cible | Justification |
|---|---|---|
| `src/Service/` | 80% lignes | Le métier vit là |
| `PaymentService`, `PaymentGatewayResolver`, `OrderService` | 100% des branches | Flux d'argent — zéro tolérance |
| `src/Controller/` | Couvert par les tests d'intégration, pas de seuil de ligne | Un contrôleur ne doit rien contenir à couvrir (SRP) |
| Front | Pas de seuil | Priorité aux composants critiques (§7) |

Viser 100% partout produit un faux sentiment de sécurité : on finit par tester des getters. Viser 100% **des branches sur trois classes précises** est vérifiable et défendable.

---

## 9. Pipeline CI (à créer)

Il n'existe aucune CI sur ce projet (roadmap 5.5 ⬜ 🟡). Étapes cibles, GitHub Actions :

1. Checkout.
2. `composer install` + `npm ci` (avec cache).
3. Lint : `php-cs-fixer --dry-run`, ESLint.
4. Analyse statique : PHPStan niveau 6 minimum.
5. **Tests unitaires** — rapides, échouent vite.
6. MySQL éphémère (service container).
7. `doctrine:migrations:migrate` puis **tests d'intégration**.
8. Vérification de couverture — bloquante sur `PaymentService` / `OrderService`.
9. `npm run build` (front) — vérifie qu'il compile.

Le point 4 mérite d'être noté : PHPStan aurait probablement signalé seul le cascade inversé de [MODELE_DONNEES.md](MODELE_DONNEES.md) §6.2, ou du moins l'aurait rendu visible. C'est l'outil au meilleur rapport effort/trouvailles pour un projet qui part de zéro test.

---

## 10. Par où commencer, concrètement

La suite tourne désormais (`php vendor/bin/phpunit` — 26 tests, 88 assertions). Reste, dans cet ordre :

1. ~~`POST /api/orders` sans `X-Csrf-Token` → 403~~ — ✅ **fait** (`CsrfProtectionTest`). Ce test a payé immédiatement : il a révélé que `/api/contact`, route publique, était bloquée en 403 pour tout visiteur anonyme. **Le formulaire de contact ne fonctionnait pas.**
2. **`PasswordValidator`** — test pur, aucune infrastructure, écrit en 10 minutes. Désormais le moins cher des tests restants.
3. **Rate limiting** : la 6ᵉ tentative → 429. Constaté à l'usage, jamais figé par un test.
4. **Un inventaire, pas un test** : `debug:router` confronté à l'`access_control` de `security.yaml`. Quelle route ne tombe sous aucune règle ? C'est ce qui aurait attrapé `/user`, et rien dans la pyramide de tests ne le remplace.

> **Ce que l'écriture du test CSRF a appris**, et qui vaut pour tous les suivants :
>
> - **Un test de rejet seul ne prouve rien.** « Sans en-tête → 403 » passerait aussi avec un subscriber qui renvoie 403 à *tout le monde*. Il faut le pendant : « avec le bon jeton → ça passe ».
> - **Vérifier qu'un test échoue quand le code est cassé.** En remplaçant la condition du subscriber par `if (false)`, 4 tests virent au rouge. Sans cette manipulation, on ne sait pas si l'assertion mord.
> - **L'ordre des écouteurs compte** : `RouterListener` (32) et le firewall (8) passent avant le contrôle CSRF (0). Une route inexistante rend 404, une requête anonyme rend 401 — dans les deux cas, jamais 403. Un test mal construit prouve que le routeur fonctionne.

> Le point 3 de la version précédente proposait `GET /api/orders/{id}` d'un autre utilisateur, « le plus utile précisément parce qu'il échouera ». Il n'aurait pas échoué : **il aurait produit un 404**, la route n'existant pas. On aurait conclu à tort que la propriété était vérifiée.
>
> L'intuition restait juste : un test qui échoue apprend quelque chose, un test qui passe du premier coup confirme ce qu'on croyait déjà. Mais elle valait pour une route imaginée. **Vérifier qu'une route existe coûte trente secondes et doit précéder le raisonnement sur ce qu'elle protège.**
