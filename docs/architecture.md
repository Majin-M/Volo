# Architecture — Projet VOLO

> ⚠️ **Ce document décrivait la conception d'origine, pas le projet construit.** Il a été repris le 17/07/2026 après confrontation au code réel. Plusieurs briques qu'il présentait au présent n'ont jamais existé : API Platform, les Voters, la couche `DTO/`, les événements métier.
>
> Les écarts sont désormais signalés ⬜ **prévu** ou ❌ **abandonné** plutôt que décrits comme acquis. Quand ce document et [CONTRAT_API.md](CONTRAT_API.md) divergent, **c'est CONTRAT_API qui fait foi** : il a été écrit à partir du code.

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
| **Base de données** | MariaDB 10.4 en dev (XAMPP) | Persistance des données |
| **Auth** | JWT (LexikJWTBundle), en cookie `HttpOnly` | Authentification stateless |
| **Infra** | XAMPP (Apache) + proxy Vite | Développement local |

> **❌ API Platform n'est pas utilisé.** Il ne figure pas dans `composer.json`. Chaque endpoint est un contrôleur Symfony écrit à la main qui construit sa réponse avec `JsonResponse`. Toute affirmation contraire — y compris [CONTRAT_API.md](CONTRAT_API.md) §8, qui suggère de générer le contrat depuis les attributs d'entités — repose sur une brique absente.
>
> **La base est MariaDB, pas MySQL 8.** XAMPP livre MariaDB (10.4.32 sur le poste de dev), alors que `backend/compose.yaml` épingle `mysql:8.0` et que [TECHNOLOGIES.md](TECHNOLOGIES.md) §2 argumente le choix de « MySQL 8 ». Ce sont trois moteurs pour un même projet. Ce n'est pas théorique : `RENAME INDEX` existe en MySQL 5.7+ et seulement à partir de MariaDB 10.5.2 — une migration l'a appris en échouant.
>
> **Docker et Nginx ne servent à rien aujourd'hui.** Voir §6.

Principe fondamental : **le front-end ne contient aucune logique métier.** Toute règle métier (prix, validation de commande) appartient aux Services Symfony.

> Attention : « stock » figurait dans cette phrase. Il n'y a **aucune gestion de stock** dans VOLO, seulement un booléen `isAvailable` — cf. [MODELE_DONNEES.md](MODELE_DONNEES.md) §6.4.

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
│   │   ├── Event/               # VIDE — ⬜ aucun événement métier
│   │   ├── EventSubscriber/     # CsrfProtection, SecurityHeaders
│   │   ├── Form/                # VIDE
│   │   ├── Repository/          # Requêtes BDD
│   │   ├── Security/            # VIDE — ⬜ aucun Voter (roadmap 2.5)
│   │   └── Service/             # Logique métier + PaymentGateway/
│   │
│   ├── migrations/              # Migrations Doctrine
│   ├── tests/                   # 26 tests / 88 assertions au total
│   │   ├── Controller/          # AuthControllerTest
│   │   ├── Entity/              # OrderPaymentTest
│   │   ├── Security/            # CsrfProtectionTest
│   │   └── Service/             # ContactNotificationTest
│   │
│   ├── compose.yaml             # volo-db + mailer SEULEMENT (cf. §6)
│   ├── phpstan.neon             # niveau max + baseline
│   ├── config/  public/  .env
│
├── frontend/
│   ├── src/
│   │   ├── api/                 # api.js, contactApi.js, productApi.js
│   │   ├── assets/
│   │   ├── components/          # NavBar, Footer, ProductCard, PaymentForm, Skeleton
│   │   ├── contexts/            # AuthContext, CartContext
│   │   ├── pages/               # Une page par route
│   │   └── utils/               # validators.js
│   │
│   ├── index.html
│   └── vite.config.js           # proxy /api → 127.0.0.1:8000 (pièce d'architecture)
│
├── docs/
└── .gitignore                   # .env / .env.* / !.env.example
```

**Dossiers annoncés qui n'existent pas** :

| Annoncé | Réalité |
|---|---|
| `backend/src/DTO/` | ❌ Jamais créé. Les contrôleurs construisent leurs tableaux à la main et les passent à `JsonResponse` — il n'existe aucun `ResponseDTO` (cf. §3) |
| `backend/src/Security/` | Le dossier existe mais est **vide** : aucun Voter |
| `backend/src/Event/` | Idem : aucun événement métier |
| `backend/tests/{Unit,Integration}/` | ⬜ Seul `tests/Controller/` existe |
| `frontend/src/{hooks,layouts,services,tests}/` | ❌ Aucun des quatre |
| `docker-compose.yml` (racine) | ❌ Seul `backend/compose.yaml` existe, et il est partiel (§6) |

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

> ⚠️ **Les Voters n'existent pas** (roadmap 2.5 ⬜ 🟠). `src/Security/` est vide. Ce document affirmait l'inverse.
>
> Conséquence : rien ne vérifie la **propriété** d'une ressource. `access_control` sait dire « il faut `ROLE_USER` », pas « il faut être le propriétaire de cette commande ». Chaque contrôleur doit le faire à la main — et une route qui l'oublie est une fuite de données. Voir [CONTRAT_API.md](CONTRAT_API.md) §4.
>
> ⚠️ **`POST`/`PUT`/`DELETE /api/products` n'existent pas non plus** (roadmap 2.6 🔴). `security.yaml` déclare des règles `ROLE_ADMIN` pour ces méthodes, et `api_specification.md` §3 les documente — mais aucun contrôleur ne les implémente. Les produits ne se créent aujourd'hui que par EasyAdmin. Une règle d'`access_control` qui protège une route inexistante ne protège rien : elle donne juste l'impression que la route existe.

**Une leçon payée comptant** : un CRUD Twig généré par `make:crud` traînait sur `/user`, hors des périmètres `^/admin` et `^/api`. Aucune règle ne le couvrait, son formulaire exposait `roles` et `password` en clair : n'importe qui pouvait se créer un compte administrateur. Supprimé le 17/07/2026, avec une règle `^/user → ROLE_ADMIN` en filet. Ce qu'il faut en retenir : **`access_control` est une liste d'autorisations, pas une politique par défaut.** Tout chemin non listé est ouvert.

---

## 4. Architecture front-end (React)

### Organisation des composants

Réalité au 17/07/2026. Le motif « un dossier par composant avec son `index.js` » n'a pas été suivi : les composants sont des fichiers plats, avec leur CSS Module à côté.

```
components/                      pages/                     (⬜ = prévu, absent)
├── NavBar.jsx                   ├── HomePage.jsx           ⬜ OrderConfirmationPage
├── Footer.jsx                   ├── ProductListPage.jsx    ⬜ AccountPage
├── ProductCard.jsx              ├── ProductDetailPage.jsx  ⬜ OrderHistoryPage
├── ProductCard.module.css       ├── CartPage.jsx           ⬜ SkinConcernPage
├── PaymentForm.jsx              ├── CheckoutPage.jsx       ⬜ RoutinesPage
└── Skeleton.jsx                 ├── LoginPage.jsx
                                 ├── RegisterPage.jsx
utils/                           └── ContactPage.jsx
└── validators.js
```

**Les noms diffèrent de ceux annoncés** : `CataloguePage` s'appelle `ProductListPage`, `ProductPage` s'appelle `ProductDetailPage`. Les routes réelles, déclarées dans `App.jsx`, sont en français : `/`, `/soins`, `/soins/:id`, `/panier`, `/connexion`, `/inscription`, `/commande`, `/contact`.

**Il n'y a aucun `layouts/`** : ni `MainLayout`, ni `AdminLayout`. Le back-office est du Twig servi par Symfony ([CONTRAT_API.md](CONTRAT_API.md) §7) — un `AdminLayout` React n'a donc pas d'objet.

`OrderConfirmationPage` est la plus coûteuse des absences : le parcours d'achat s'arrête aujourd'hui sur une `alert()` (roadmap 3.13 ⬜ 🔴), ce qui empêche aussi d'écrire le test E2E du parcours complet ([STRATEGIE_TESTS.md](STRATEGIE_TESTS.md) §6).

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

## 6. Architecture Docker — ⬜ largement inexistante

> ⚠️ **Rien de ce qui suivait n'existe, sauf deux services.** Ce document décrivait cinq conteneurs et un reverse proxy Nginx. Le développement se fait sur **XAMPP**, et le seul fichier Compose du projet est `backend/compose.yaml` :

```yaml
# backend/compose.yaml — ce qui existe VRAIMENT
services:
  volo-db:       # MySQL 8.0 (image Docker) — port 3306
  mailer:        # Mailpit — ports 1025 / 8025
```

| Service annoncé | État |
|---|---|
| `volo-db` | ✅ Existe — mais **inutilisé en pratique** : le dev tape sur le MariaDB de XAMPP |
| `volo-mailer` | ✅ Existe (nommé `mailer`) — **et désormais utilisé** : `MAILER_DSN` vise Mailpit pour les notifications de contact (§7, [TECHNOLOGIES.md](TECHNOLOGIES.md) §2) |
| `volo-api` | ❌ Absent — Symfony tourne sous l'Apache de XAMPP |
| `volo-react` | ❌ Absent — Vite tourne en direct sur le poste |
| `volo-nginx` | ❌ Absent — **aucun reverse proxy n'a jamais tourné** |

Le rôle attribué ici à Nginx est tenu en développement par le **proxy Vite**, en trois lignes de `vite.config.js`. Ce n'est pas un pis-aller : c'est ce qui fait tenir les cookies `HttpOnly` en dev, en ramenant React et l'API à une **origine unique**. Le détail est dans [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §1.

```
Navigateur → localhost:5173 (Vite)
                ├── /api/*  → proxy → 127.0.0.1:8000 (Apache/Symfony) → MariaDB
                └── /*      → React
```

**La conséquence à retenir** : les configurations Nginx du projet n'ont **jamais été exécutées une seule fois**. Pour de la configuration d'infrastructure, cela revient à dire qu'elles sont probablement fausses par endroits — voir [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §3.

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
    │  RECALCULE le total côté serveur (RG4) — un total reçu du client est ignoré
    │  crée Order + OrderItems
    ▼
[Doctrine / EntityManager]
    │  persiste en BDD
    ▼
[OrderController]
    │  compose son tableau à la main → JsonResponse 201
    ▼
[React]  ⬜ alert() — OrderConfirmationPage n'existe pas (roadmap 3.13)
```

Quatre étapes du flux d'origine n'existent pas :

| Étape annoncée | Réalité |
|---|---|
| « JWT dans header » | ❌ Le jeton est dans un cookie `HttpOnly` ([CONTRAT_API.md](CONTRAT_API.md) §1) |
| « valide le stock » | ❌ Il n'y a pas de stock ([MODELE_DONNEES.md](MODELE_DONNEES.md) §6.4) |
| « déclenche `OrderCreatedEvent` » → « envoie l'email » | ❌ `src/Event/` est vide. **Aucun email de confirmation n'est envoyé** (roadmap 4.2 ⬜ 🟠) |
| « retourne `OrderResponseDTO` » | ❌ Aucun DTO — tableau construit à la main |

Ce que le schéma d'origine **ne montrait pas** et qui est pourtant l'essentiel : le firewall et le contrôle CSRF, c'est-à-dire les deux étapes qui décident si la requête a le droit d'exister.

Et le flux s'arrête là. **Aucune commande ne passe jamais à `paid`** : le webhook Stripe n'existe pas, donc VOLO ne saura jamais que le client a payé ([DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2). C'est le manque fonctionnel le plus important du projet.
