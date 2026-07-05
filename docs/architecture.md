# Architecture — Projet VOLO

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
| **API REST** | Symfony 7 + API Platform | Exposition des données et logique métier |
| **Front-end SPA** | React + Vite | Interface utilisateur |
| **Base de données** | MySQL 8 | Persistance des données |
| **Auth** | JWT (LexikJWTBundle) | Authentification stateless |
| **Infra** | Docker + Nginx | Conteneurisation et reverse proxy |

Principe fondamental : **le front-end ne contient aucune logique métier.** Toute règle métier (prix, stock, validation de commande) appartient aux Services Symfony.

---

## 2. Structure du projet

```
volo/
│
├── backend/
│   ├── src/
│   │   ├── Controller/          # Points d'entrée REST — aucune logique métier
│   │   ├── Entity/              # Entités Doctrine — représentation des données
│   │   ├── Enum/                # Enums PHP 8.1 (OrderStatus, UserRole…)
│   │   ├── Repository/          # Requêtes BDD — générées par Doctrine
│   │   ├── Service/             # Logique métier — cœur de l'application
│   │   ├── DTO/                 # Objets de transfert (entrée et sortie API)
│   │   ├── Event/               # Événements métier
│   │   ├── EventSubscriber/     # Listeners d'événements
│   │   ├── Security/            # Voters, providers, guards JWT
│   │   └── DataFixtures/        # Données de test
│   │
│   ├── migrations/              # Migrations Doctrine (jamais modifiées après exécution)
│   ├── tests/
│   │   ├── Unit/
│   │   ├── Integration/
│   │   └── Functional/
│   │
│   ├── config/
│   ├── public/
│   └── .env
│
├── frontend/
│   ├── src/
│   │   ├── api/                 # Appels HTTP vers l'API Symfony
│   │   ├── assets/              # Images, fonts, icônes
│   │   ├── components/          # Composants réutilisables
│   │   ├── contexts/            # Contextes React (Auth, Cart)
│   │   ├── hooks/               # Hooks personnalisés
│   │   ├── layouts/             # Gabarits de page (MainLayout, AdminLayout)
│   │   ├── pages/               # Une page par route
│   │   ├── services/            # Logique front (cartService, storageService)
│   │   ├── utils/               # Fonctions pures (formatPrice, formatDate)
│   │   └── tests/
│   │
│   ├── index.html
│   └── vite.config.js
│
├── docs/
│   ├── convention_de_nommage.md
│   ├── architecture.md
│   ├── api_specification.md
│   └── roadmap.md
│
├── docker-compose.yml
└── .env
```

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

Règle absolue : **un Controller ne contient jamais de logique métier.** Il appelle un Service, récupère un résultat, et retourne une réponse JSON via un `ResponseDTO`.

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

- Authentification via **JWT** (LexikJWTBundle)
- Autorisation via **Voters** Symfony
- Routes publiques : `GET /api/products`, `GET /api/brands`, `POST /api/auth/login`
- Routes protégées `ROLE_USER` : `POST /api/orders`, `GET /api/orders`
- Routes protégées `ROLE_ADMIN` : `POST /api/products`, `PUT`, `DELETE`

---

## 4. Architecture front-end (React)

### Organisation des composants

```
components/
├── ProductCard/
│   ├── ProductCard.jsx
│   ├── product-card.css
│   └── index.js
├── ProductGrid/
├── CartItem/
├── CheckoutForm/
└── Pagination/

pages/
├── HomePage.jsx
├── CataloguePage.jsx
├── ProductPage.jsx
├── CartPage.jsx
├── CheckoutPage.jsx
├── OrderConfirmationPage.jsx
├── AccountPage.jsx
├── OrderHistoryPage.jsx
├── SkinConcernPage.jsx
├── LoginPage.jsx
└── RegisterPage.jsx

layouts/
├── MainLayout.jsx
└── AdminLayout.jsx
```

### Gestion d'état

| Contexte | Rôle |
|---|---|
| `AuthContext` | Token JWT, utilisateur connecté, login/logout |
| `CartContext` | Panier local (sessionStorage), ajout/suppression |

Règle : **pas de prop drilling au-delà de 2 niveaux** — utiliser les contextes ou des hooks dédiés.

### Couche API

Chaque ressource possède son fichier dédié dans `api/` :

```js
// api/productApi.js
export const fetchProducts = (params) => ...
export const fetchProductById = (id) => ...

// api/orderApi.js
export const createOrder = (payload) => ...
export const fetchOrders = () => ...
```

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

### Colonnes techniques standard

Toutes les entités persistées incluent :

```sql
id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

---

## 6. Architecture Docker

```yaml
# docker-compose.yml
services:
  volo-nginx:    # Reverse proxy — port 80/443
  volo-api:      # Symfony PHP-FPM — port 9000
  volo-react:    # React Vite — port 5173
  volo-db:       # MySQL 8 — port 3306
  volo-mailer:   # Mailpit (dev) — port 8025
```

Flux réseau :

```
Navigateur
    │
    ▼
volo-nginx (80)
    ├── /api/*    → volo-api:9000
    └── /*        → volo-react:5173
```

---

## 7. Flux de données

### Flux d'une commande (exemple complet)

```
[React CartPage]
    │  POST /api/orders  (JWT dans header)
    ▼
[OrderController]
    │  délègue à
    ▼
[OrderService]
    │  valide le stock, calcule le total
    │  crée Order + OrderItems
    │  déclenche OrderCreatedEvent
    ▼
[OrderRepository]
    │  persiste en BDD
    ▼
[EventSubscriber]
    │  envoie l'email de confirmation
    ▼
[OrderController]
    │  retourne OrderResponseDTO
    ▼
[React OrderConfirmationPage]
```
