# Architecture — Projet VOLO

> **Repris le 13/09/2026, après confrontation ligne à ligne au code.** La révision précédente datait du 17/07 et décrivait un projet nettement moins avancé : elle niait l'existence des Voters, des écritures sur `/api/products`, de la pile Docker, du webhook Stripe, des emails de confirmation et de la gestion de stock. Ces briques existent toutes désormais et sont décrites ici au présent.
>
> Ce qui reste marqué ❌ **abandonné** ou ⬜ **prévu** l'est en connaissance de cause : API Platform et la couche `DTO/` n'existent toujours pas.
>
> Quand ce document et [CONTRAT_API.md](CONTRAT_API.md) divergent, **c'est CONTRAT_API qui fait foi** : il est écrit à partir du code.

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Structure du projet](#2-structure-du-projet)
3. [Architecture back-end (Symfony)](#3-architecture-back-end-symfony)
4. [Architecture front-end (React)](#4-architecture-front-end-react)
5. [Modèle de données](#5-modèle-de-données)
6. [Architecture Docker](#6-architecture-docker)
7. [Flux de données](#7-flux-de-données)

---

## 1. Vue d'ensemble

VOLO est une application e-commerce skincare construite sur une architecture **découplée** :

| Couche | Technologie | Rôle |
|---|---|---|
| **API REST** | Symfony 7, contrôleurs écrits à la main | Exposition des données et logique métier |
| **Front-end SPA** | React 19 + Vite | Interface utilisateur |
| **Base de données** | MySQL 8.0 | Persistance des données |
| **Auth** | JWT (LexikJWTBundle), en cookie `HttpOnly` | Authentification stateless |
| **Infra** | XAMPP (Apache) + proxy Vite en dev ; Nginx + Docker en cible | Développement local et production |

> **❌ API Platform n'est pas utilisé.** Il ne figure pas dans `composer.json`. Chaque endpoint est un contrôleur Symfony écrit à la main qui construit sa réponse avec `JsonResponse`. Toute affirmation contraire — y compris [CONTRAT_API.md](CONTRAT_API.md) §8, qui suggère de générer le contrat depuis les attributs d'entités — repose sur une brique absente.
>
> **Le SGBD est unifié sur MySQL 8.0 depuis le 01/09/2026.** `DATABASE_URL` cible explicitement `serverVersion=8.0`, et les deux fichiers Compose épinglent `mysql:8.0`. Auparavant XAMPP livrait MariaDB 10.4 en dev face à MySQL 8 en conteneur : ce n'était pas théorique, `RENAME INDEX` existe en MySQL 5.7+ mais seulement à partir de MariaDB 10.5.2 — une migration l'a appris en échouant.

Principe fondamental : **le front-end ne contient aucune logique métier.** Toute règle métier (prix, stock, validation de commande) appartient aux Services Symfony.

> La gestion de stock existe depuis la migration `Version20260901120000` : colonne `stock` sur `Product`, vérifiée et décrémentée par `OrderService` à la création de commande. `isAvailable` subsiste comme interrupteur manuel côté admin — cf. [MODELE_DONNEES.md](MODELE_DONNEES.md) §6.4.

---

## 2. Structure du projet

Arborescence **réelle**. Les dossiers marqués ⬜ figuraient dans la version d'origine de ce document et n'existent pas.

```
volo/
│
├── backend/
│   ├── src/
│   │   ├── Command/             # app:create-admin (seule voie vers ROLE_ADMIN)
│   │   ├── Controller/          # Points d'entrée REST + Admin/ (EasyAdmin)
│   │   ├── DataFixtures/        # Données de test
│   │   ├── Entity/              # Entités Doctrine
│   │   ├── Enum/                # OrderStatus, PaymentStatus, UserRole…
│   │   ├── Doctrine/Filter/     # SoftDeleteFilter (exclut les enregistrements supprimés)
│   │   ├── Event/               # VIDE — ⬜ aucun événement métier
│   │   ├── EventSubscriber/     # Audit, CsrfProtection, Exception,
│   │   │                        #   SecurityHeaders, StatusTransition
│   │   ├── Repository/          # Requêtes BDD
│   │   ├── Security/            # OrderVoter, ProductVoter
│   │   └── Service/             # Logique métier + PaymentGateway/
│   │
│   ├── migrations/              # Migrations Doctrine
│   ├── public/admin-theme/      # Thème VOLO du back-office (CSS + favicon)
│   ├── tests/                   # 36 tests / 108 assertions au total
│   │   ├── Controller/          # AuthControllerTest, WebhookStripeTest
│   │   ├── Entity/              # OrderPaymentTest
│   │   ├── Security/            # CsrfProtectionTest
│   │   └── Service/             # ContactNotificationTest
│   │
│   ├── compose.yaml             # db + mailer seulement — la pile complète
│   │                            #   est à la racine du dépôt (cf. §6)
│   ├── phpstan.neon             # niveau max + baseline
│   ├── config/  public/  .env
│
├── frontend/
│   ├── src/
│   │   ├── api/                 # api.js, contactApi.js, productApi.js
│   │   ├── assets/
│   │   ├── components/          # NavBar, Footer, ProductCard, PaymentForm,
│   │   │                        #   PrivateRoute, ConfirmDialog, ErrorBoundary,
│   │   │                        #   FormField, PasswordStrength, Skeleton
│   │   ├── contexts/            # AuthContext, CartContext, ToastContext
│   │   ├── pages/               # Une page par route
│   │   ├── test/                # Configuration Vitest
│   │   └── utils/               # validators.js
│   │
│   ├── index.html
│   └── vite.config.js           # proxy /api, /admin, /bundles, /admin-theme
│                                #   → 127.0.0.1:8000 (pièce d'architecture)
│
├── docs/
└── .gitignore                   # .env / .env.* / !.env.example
```

**Dossiers annoncés qui n'existent pas** :

| Annoncé | Réalité |
|---|---|
| `backend/src/DTO/` | ❌ Jamais créé. Les contrôleurs construisent leurs tableaux à la main et les passent à `JsonResponse` — il n'existe aucun `ResponseDTO` (cf. §3). [convention_de_nommage.md](convention_de_nommage.md) §11 prescrit pourtant la règle inverse : c'est la convention qui est à corriger, pas le code |
| `backend/src/Form/` | ❌ Le dossier n'existe pas. Les formulaires du back-office sont générés par EasyAdmin depuis les entités |
| `backend/src/Event/` | Le dossier existe mais est **vide** : aucun événement métier |
| `backend/tests/{Unit,Integration,Functional}/` | ❌ L'arborescence réelle suit les couches : `Controller/`, `Entity/`, `Security/`, `Service/`. [convention_de_nommage.md](convention_de_nommage.md) §12 décrit l'autre découpage |
| `frontend/src/{hooks,layouts,services}/` | ❌ Aucun des trois |

Il n'y a pas non plus de `.env` à la racine : la configuration vit dans `backend/.env`.

---

## 3. Architecture back-end (Symfony)

### Principe de séparation des couches

```
Request HTTP
     │
     ▼
Controller          ← reçoit, délègue, retourne
     │
     ▼
Service             ← logique métier, validation
     │
     ▼
Repository          ← requêtes BDD uniquement
     │
     ▼
Entity              ← données, relations Doctrine
```

Règle absolue : **un Controller ne contient jamais de logique métier.** Il appelle un Service, récupère un résultat, et retourne une réponse JSON.

> ❌ **Il n'y a pas de `ResponseDTO`.** Chaque contrôleur compose son tableau de réponse à la main, ligne par ligne. Conséquence à connaître : la forme du JSON n'est écrite nulle part de façon centralisée — elle est dupliquée dans chaque méthode. C'est précisément ce qui permet à [api_specification.md](api_specification.md) de diverger sans que rien ne le signale, et ce qu'un `openapi.yaml` réglerait ([CONTRAT_API.md](CONTRAT_API.md) §8).

### Entités métier

| Entité | Table BDD | Description |
|---|---|---|
| `User` | `user` | Compte client et administrateur |
| `Product` | `product` | Produit skincare |
| `Brand` | `brand` | Marque du produit |
| `SkinConcern` | `skin_concern` | Problématique peau (ex-`Problematic`) |
| `Routine` | `routine` | Routine de soin recommandée |
| `Order` | `shop_order` | Commande client |
| `OrderItem` | `order_item` | Ligne de commande |
| `Payment` | `payment` | Paiement associé à une commande |
| `ContactMessage` | `contact_message` | Message du formulaire de contact |
| `AuditLog` | `audit_log` | Trace des changements de statut et des modifications sensibles, alimentée par `AuditSubscriber` |

### Enums

| Enum | Valeurs |
|---|---|
| `OrderStatus` | `pending`, `paid`, `shipped`, `delivered`, `cancelled` |
| `PaymentStatus` | `pending`, `captured`, `failed`, `refunded` |
| `PaymentMethod` | `card`, `paypal` |
| `UserRole` | `ROLE_USER`, `ROLE_ADMIN` |
| `RoutineLevel` | `beginner`, `intermediate`, `advanced` |

### Sécurité

- Authentification via **JWT** (LexikJWTBundle), transporté par un cookie `HttpOnly` `volo_token` — **jamais** un en-tête `Authorization`. Le raisonnement complet est dans [CONTRAT_API.md](CONTRAT_API.md) §1.
- Autorisation par `access_control` dans `security.yaml`, à gros grain uniquement.
- Routes publiques : `GET /api/products`, `GET /api/brands`, `GET /api/skin-concerns`, `POST /api/auth/login`, `POST /api/auth/register`, `POST /api/contact`
- Routes protégées `ROLE_USER` : `POST /api/orders`, `GET /api/orders`, `POST /api/payments`, `GET /api/auth/me`
- Deux firewalls disjoints (`api` stateless, `admin` par session) — [CONTRAT_API.md](CONTRAT_API.md) §3

- Autorisation fine par **Voters** : `OrderVoter` (VIEW réservé au propriétaire ou à un admin, CREATE à tout authentifié, EDIT à l'admin) et `ProductVoter` (VIEW public, CREATE/EDIT/DELETE réservés à `ROLE_ADMIN`). C'est ce qui manquait à l'`access_control`, incapable d'exprimer « être le propriétaire de cette commande ».
- **`POST`/`PUT`/`DELETE /api/products`** sont implémentés (`ProductController::create/update/delete`), doublement protégés par `access_control` et par `ProductVoter`.

- **Transitions de statut** contraintes par `StatusTransitionSubscriber` sur l'événement Doctrine `preUpdate` : c'est le seul point de passage commun à toutes les écritures, donc le seul endroit qu'EasyAdmin ne peut pas contourner. Une transition interdite lève une `LogicException` dont le message énumère les états réellement atteignables.

> **Nettoyage du 13/09/2026** : un second écouteur, `WorkflowValidationListener`, faisait exactement le même travail sur le même événement — les deux étaient enregistrés et se déclenchaient à chaque mise à jour. Il a été supprimé, après avoir reporté ses deux apports (message d'erreur détaillé, en-tête documentaire) dans `StatusTransitionSubscriber`.

**Une leçon payée comptant** : un CRUD Twig généré par `make:crud` traînait sur `/user`, hors des périmètres `^/admin` et `^/api`. Aucune règle ne le couvrait, son formulaire exposait `roles` et `password` en clair : n'importe qui pouvait se créer un compte administrateur. Supprimé le 17/07/2026, avec une règle `^/user → ROLE_ADMIN` en filet. Ce qu'il faut en retenir : **`access_control` est une liste d'autorisations, pas une politique par défaut.** Tout chemin non listé est ouvert.

---

## 4. Architecture front-end (React)

### Organisation des composants

Réalité au 13/09/2026. Le motif « un dossier par composant avec son `index.js` » n'a pas été suivi : les composants sont des fichiers plats, avec leur CSS Module à côté.

```
components/                      pages/
├── NavBar.jsx                   ├── HomePage.jsx
├── Footer.jsx                   ├── ProductListPage.jsx
├── ProductCard.jsx              ├── ProductDetailPage.jsx
├── PaymentForm.jsx              ├── CartPage.jsx
├── PrivateRoute.jsx             ├── CheckoutPage.jsx
├── ConfirmDialog.jsx            ├── OrderConfirmationPage.jsx
├── ErrorBoundary.jsx            ├── OrderHistoryPage.jsx
├── FormField.jsx                ├── AccountPage.jsx
├── PasswordStrength.jsx         ├── LoginPage.jsx
└── Skeleton.jsx                 ├── RegisterPage.jsx
                                 ├── ContactPage.jsx
utils/                           ├── MentionsLegalesPage.jsx
└── validators.js                ├── CGVPage.jsx
                                 └── NotFoundPage.jsx
```

**Les noms diffèrent de ceux annoncés à l'origine** : `CataloguePage` s'appelle `ProductListPage`, `ProductPage` s'appelle `ProductDetailPage`. Les routes réelles, déclarées dans `App.jsx`, sont en français : `/`, `/soins`, `/soins/:id`, `/panier`, `/connexion`, `/inscription`, `/commande`, `/confirmation`, `/mes-commandes`, `/mon-compte`, `/contact`, plus les pages légales.

**Il n'y a aucun `layouts/`** : ni `MainLayout`, ni `AdminLayout`. Le back-office est du Twig servi par Symfony ([CONTRAT_API.md](CONTRAT_API.md) §7) — un `AdminLayout` React n'a donc pas d'objet.

Les quatre routes privées (`/commande`, `/confirmation`, `/mes-commandes`, `/mon-compte`) sont enveloppées dans `PrivateRoute`, qui consulte `useAuth()` et redirige vers `/connexion`. Cette garde est un confort d'affichage : **la protection réelle reste côté serveur**, le firewall JWT refusant la requête quoi qu'il arrive.

⬜ Restent absentes : `SkinConcernPage` et `RoutinesPage` — le filtrage par problématique se fait via `/soins?skin_concern=`.

### Gestion d'état

| Contexte | Rôle |
|---|---|
| `AuthContext` | Utilisateur connecté, login/logout. **Ne stocke aucun jeton** |
| `CartContext` | Panier local (**localStorage**), ajout/suppression |

> ⚠️ **`AuthContext` ne détient pas le JWT** — ce document affirmait le contraire, et c'est l'erreur la plus trompeuse qu'il contenait. Le jeton vit dans un cookie `HttpOnly` inaccessible au JavaScript ; le contexte ne connaît que l'utilisateur, restauré au montage via `GET /api/auth/me`. Un `AuthContext` qui porterait le jeton signifierait qu'il est lisible en JS — c'est-à-dire exactement la vulnérabilité que [CONTRAT_API.md](CONTRAT_API.md) §1 décrit avoir voulu écarter.
>
> **Le panier est en `localStorage`, pas `sessionStorage`.** Le code fait foi (initialisation paresseuse du `useState`, pour éviter un scintillement au montage). La roadmap 3.4 dit `sessionStorage`, [STRATEGIE_TESTS.md](STRATEGIE_TESTS.md) §7 dit `localStorage` : c'est ce dernier qui a raison. La différence n'est pas cosmétique — le panier survit à la fermeture de l'onglet.

Règle : **pas de prop drilling au-delà de 2 niveaux** — utiliser les contextes.

### Couche API

`api/` contient un module transverse et un fichier par ressource — mais **seulement deux ressources sur neuf** :

```
api/
├── api.js          # Socle : credentials: 'include', en-tête X-Csrf-Token
├── productApi.js
└── contactApi.js   # ⬜ pas d'orderApi.js, ni authApi.js, ni brandApi.js
```

`api.js` est la pièce importante : il pose `credentials: 'include'` sur chaque appel et recopie le cookie `volo_csrf` dans l'en-tête `X-Csrf-Token` pour tout `POST`/`PUT`/`PATCH`/`DELETE`. C'est la moitié cliente du double-submit décrit en [CONTRAT_API.md](CONTRAT_API.md) §2.

---

## 5. Modèle de données

### Diagramme des relations principales

```
User ──────────────────────── shop_order (1,n)
                                    │
                              order_item (1,n)
                                    │
Product ────────────────────────────┘
   │
Brand (n,1)
   │
skin_concern (n,n) ←── product_skin_concern (table pivot)
   │
Routine (n,n) ←──────── routine_product (table pivot)
```

### Colonnes techniques

Ce que la base contient **réellement** (vérifié via `SHOW CREATE TABLE`) :

```sql
id          INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY   -- signé, pas UNSIGNED
created_at  DATETIME NOT NULL                             -- aucun DEFAULT
updated_at  DATETIME NOT NULL                             -- aucun ON UPDATE
```

Deux écarts avec ce que ce document annonçait :

- **`id` n'est pas `UNSIGNED`.** Doctrine génère `INT` signé par défaut. La moitié négative de l'intervalle est perdue — sans conséquence à cette échelle, mais autant ne pas décrire une contrainte qui n'existe pas.
- **Il n'y a ni `DEFAULT CURRENT_TIMESTAMP` ni `ON UPDATE`.** Ces valeurs sont posées par le code PHP, pas par la base. Un `INSERT` en SQL direct qui les omettrait échouerait sur `NOT NULL` au lieu d'être complété automatiquement.

En revanche « **toutes les entités persistées** » est presque exact — vérifié table par table sur `information_schema` : `brand`, `contact_message`, `payment`, `product`, `routine`, `shop_order`, `skin_concern` et `user` portent toutes `created_at` et `updated_at`.

**Deux exceptions**, et elles ont chacune leur raison :

| Table | Pourquoi |
|---|---|
| `order_item` | Ni `created_at` ni `updated_at`. Une ligne de commande n'a pas de vie propre : elle naît et meurt avec sa commande, dont l'horodatage fait foi |
| `product_skin_concern`, `routine_product` | Tables de jointure pures (§4 du [MODELE_DONNEES.md](MODELE_DONNEES.md)) — deux clés étrangères, rien d'autre à dater |

`product.updated_at` est le seul `NULL`-able du lot : il reste vide tant que le produit n'a pas été modifié.

> **Une correction de la correction, pour mémoire.** Une première révision de ce document affirmait ici que « `skin_concern` et `routine` n'ont ni `created_at` ni `updated_at` ». C'était **faux** : les deux les ont. L'erreur venait de s'être fié au dictionnaire de [MODELE_DONNEES.md](MODELE_DONNEES.md) — qui, lui, les omet — au lieu d'interroger la base.
>
> C'est précisément le travers que cette révision documentaire cherchait à corriger, commis en la corrigeant. La règle vaut d'être écrite : **sur une question de schéma, la base fait foi ; aucun document n'est une source.**

Le nommage des colonnes est bien en `snake_case` (`image_url`, `postal_code`, `created_at`) : la stratégie Doctrine configurée est `underscore`. Ce point était resté ouvert dans [MODELE_DONNEES.md](MODELE_DONNEES.md) §6.6 ; il est désormais tranché, la migration `Version20260717120000` l'ayant affiché à l'exécution.

---

## 6. Architecture Docker

Deux fichiers Compose coexistent, avec des rôles distincts :

| Fichier | Contenu | Usage |
|---|---|---|
| `docker-compose.yml` (racine) | 5 services : `nginx`, `backend`, `frontend`, `db`, `mailer` | Pile complète, cible de production |
| `backend/compose.yaml` | `db` + `mailer` seulement | Dépendances d'appoint quand on développe le back sur XAMPP |

> ⚠️ **Les noms de services ne sont pas préfixés `volo-`** — c'est le `container_name` qui l'est. [convention_de_nommage.md](convention_de_nommage.md) §7 et [roadmap.md](roadmap.md) 1.1 annoncent `volo-api`, `volo-react`, `volo-nginx` : ces noms n'existent nulle part. Conséquence concrète : `scripts/backup-db.sh` cible `volo-db`, alors que `backend/compose.yaml` nomme son conteneur `volo-mysql` — la sauvegarde ne fonctionne que sur la pile racine.

En développement, le rôle de Nginx est tenu par le **proxy Vite**, en quelques lignes de `vite.config.js`. Ce n'est pas un pis-aller : c'est ce qui fait tenir les cookies `HttpOnly`, en ramenant React et l'API à une **origine unique**. Le détail est dans [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §1.

```
Navigateur → localhost:5173 (Vite)
                ├── /api/*          → proxy → 127.0.0.1:8000 (Symfony) → MySQL 8
                ├── /admin, /bundles, /admin-theme → proxy → back-office EasyAdmin
                └── /*              → React
```

En production, Nginx tient ce rôle avec le même découpage : `/api`, `/admin`, `/bundles`, `/admin-theme`, `/sitemap.xml` et les images uploadées vont à PHP-FPM, tout le reste à la SPA.

> **Réserve à énoncer** : la pile Docker est définie et cohérente, mais elle n'a pas été déployée sur un serveur réel. Pour de la configuration d'infrastructure, cela signifie qu'elle reste à éprouver — voir [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §3.

---

## 7. Flux de données

### Flux d'une commande — ce qui se passe réellement

```
[React CheckoutPage]
    │  POST /api/orders
    │  cookie volo_token (HttpOnly) + en-tête X-Csrf-Token
    ▼
[proxy Vite] ──> [Apache/Symfony]
    ▼
[firewall api]  vérifie le JWT du cookie — stateless
[CsrfProtectionSubscriber]  compare l'en-tête et le cookie, sinon 403
    ▼
[OrderController]
    │  vérifie $this->getUser(), désérialise, délègue
    ▼
[OrderService]
    │  vérifie le stock disponible, RECALCULE le total côté serveur (RG4)
    │  — un total reçu du client est ignoré
    │  crée Order + OrderItems, décrémente le stock
    ▼
[Doctrine / EntityManager]
    │  persiste en BDD
    ▼
[OrderController]
    │  compose son tableau à la main → JsonResponse 201
    ▼
[React CheckoutPage]  → paiement Stripe Elements → /confirmation
    ▼
[Stripe]  webhook payment_intent.succeeded
    ▼
[WebhookController]  signature HMAC vérifiée, idempotent
    │  Payment → CAPTURED, Order → PAID (via les machines à états)
    │  email de confirmation (OrderConfirmationService, best-effort)
```

Deux étapes du flux d'origine n'existent toujours pas :

| Étape annoncée | Réalité |
|---|---|
| « JWT dans header » | ❌ Le jeton est dans un cookie `HttpOnly` ([CONTRAT_API.md](CONTRAT_API.md) §1) |
| « déclenche `OrderCreatedEvent` » | ❌ `src/Event/` est vide : l'email part directement depuis `WebhookController`, sans événement métier intermédiaire |
| « retourne `OrderResponseDTO` » | ❌ Aucun DTO — tableau construit à la main |

Ce que le schéma d'origine **ne montrait pas** et qui est pourtant l'essentiel : le firewall et le contrôle CSRF, c'est-à-dire les deux étapes qui décident si la requête a le droit d'exister.

Le parcours va désormais jusqu'au bout : une commande payée passe bien à `paid` par le webhook, et le passage est couvert par `WebhookStripeTest` (10 tests, dont l'idempotence du rejeu).
