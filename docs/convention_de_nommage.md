# Convention de nommage — Projet VOLO

## Table des matières

1. [Principes généraux](#1-principes-généraux)
2. [Symfony — Back-end](#2-symfony--back-end)
   - [Entités](#entités)
   - [Contrôleurs](#contrôleurs)
   - [Services](#services)
   - [Repositories](#repositories)
   - [DTO](#dto)
   - [Variables et constantes](#variables-et-constantes)
3. [React — Front-end](#3-react--front-end)
   - [Composants](#composants)
   - [Pages](#pages)
   - [Hooks](#hooks)
   - [Contexts](#contexts)
   - [Fichiers utilitaires](#fichiers-utilitaires)
4. [Base de données — MySQL](#4-base-de-données--mysql)
   - [Tables](#tables)
   - [Colonnes](#colonnes)
   - [Clés primaires et étrangères](#clés-primaires-et-étrangères)
5. [API REST](#5-api-rest)
   - [Routes](#routes)
   - [Méthodes HTTP](#méthodes-http)
   - [Codes de réponse](#codes-de-réponse)
6. [Git](#6-git)
   - [Branches](#branches)
   - [Messages de commit](#messages-de-commit)
7. [Docker](#7-docker)
8. [Fichiers et assets](#8-fichiers-et-assets)
9. [Convention de commentaires](#9-convention-de-commentaires)
   - [Entité Doctrine](#entité-doctrine)
   - [Service](#service)
   - [Contrôleur API](#contrôleur-api)
   - [Composant React](#composant-react)

---

## 1. Principes généraux

| Règle | Détail |
|---|---|
| **Langue du code** | Anglais — noms de variables, classes, fonctions, routes, colonnes BDD |
| **Langue des contenus** | Français — labels UI, messages d'erreur affichés à l'utilisateur |
| **Mots réservés SQL** | À éviter absolument comme noms de tables ou colonnes (`order`, `key`, `value`…) — préférer `shop_order`, `setting_key` |
| **Abréviations** | Interdites sauf acronymes établis (`id`, `url`, `jwt`, `dto`) |
| **Cohérence** | Une même entité porte le même nom dans toutes les couches (`Product` en PHP, `product` en BDD, `ProductCard` en React, `/api/products` en REST) |

### Synthèse des casses par contexte

| Contexte | Convention | Exemple |
|---|---|---|
| Classes PHP / Entités | `PascalCase` | `Product`, `OrderItem` |
| Variables PHP / JS | `camelCase` | `productName`, `cartTotal` |
| Constantes | `UPPER_SNAKE_CASE` | `MAX_CART_ITEMS` |
| Tables BDD | `snake_case` singulier | `product`, `order_item` |
| Colonnes BDD | `snake_case` | `first_name`, `created_at` |
| Routes API | `kebab-case` pluriel | `/api/products`, `/api/order-items` |
| Composants React | `PascalCase` | `ProductCard`, `CheckoutForm` |
| Fichiers CSS | `kebab-case` | `product-card.css` |
| Branches Git | `kebab-case` avec préfixe | `feature/cart-system` |
| Containers Docker | `kebab-case` | `volo-api`, `volo-db` |

---

## 2. Symfony — Back-end

### Entités

Convention : `PascalCase`, nom singulier, correspondant à la table BDD.

```php
// GOOD
Product
Brand
SkinConcern
Order
OrderItem
Payment
User
ContactMessage

// BAD
Products       // pluriel
produit        // français
product_entity // suffixe inutile
```

### Contrôleurs

Convention : `PascalCase + Controller`.  
Un contrôleur par ressource principale. Les méthodes reflètent l'action HTTP.

```php
ProductController
BrandController
OrderController
PaymentController
SkinConcernController
AuthController
```

Méthodes internes :

```php
// GOOD
public function index()   // GET /api/products
public function show()    // GET /api/products/{id}
public function create()  // POST /api/products
public function update()  // PUT /api/products/{id}
public function delete()  // DELETE /api/products/{id}

// BAD
public function getProducts()
public function fetchOne()
public function addProduct()
```

### Services

Convention : `PascalCase + Service`.  
Contiennent la logique métier — jamais de logique dans les contrôleurs.

```php
ProductService
CartService
PaymentService
OrderService
SkinConcernService
AuthService
```

### Repositories

Convention : `PascalCase + Repository`.  
Générés automatiquement par Doctrine, ne contiennent que des requêtes BDD.

```php
ProductRepository
OrderRepository
UserRepository
SkinConcernRepository
```

### DTO

Convention : `Action + Entité + DTO`.  
Un DTO par opération de création ou mise à jour.

```php
// GOOD
CreateProductDTO
UpdateProductDTO
CreateOrderDTO
RegisterUserDTO

// BAD
ProductDTO      // action non précisée
ProductData     // suffixe trompeur
```

### Variables et constantes

```php
// Variables — camelCase
$productName
$orderTotal
$currentUser
$isAvailable
$createdAt

// Constantes — UPPER_SNAKE_CASE
const DEFAULT_PAGE_SIZE = 20;
const MAX_CART_ITEMS    = 50;
const JWT_EXPIRY        = 3600;
```

---

## 3. React — Front-end

### Composants

Convention : `PascalCase`, fichier `.jsx`.  
Un composant par fichier, nom identique au fichier.

```
// GOOD
ProductCard.jsx
ProductGrid.jsx
CheckoutForm.jsx
SkinConcernBadge.jsx
CartItem.jsx

// BAD
productCard.jsx     // camelCase
product_card.jsx    // snake_case
ProductCardComp.jsx // suffixe inutile
```

### Pages

Convention : `PascalCase + Page`, fichier `.jsx`.  
Chaque route de l'application correspond à une page.

```
HomePage.jsx
CataloguePage.jsx
ProductPage.jsx
CartPage.jsx
CheckoutPage.jsx
OrderConfirmationPage.jsx
AccountPage.jsx
OrderHistoryPage.jsx
SkinConcernPage.jsx
LoginPage.jsx
RegisterPage.jsx
```

### Hooks

Convention : `use + PascalCase`, fichier `.js`.

```js
// GOOD
useCart.js
useAuth.js
useProducts.js
useOrder.js
usePagination.js

// BAD
cart.js          // préfixe "use" manquant
UseCart.js       // PascalCase sur le "U"
fetchProducts.js // ce n'est pas un hook
```

### Contexts

Convention : `PascalCase + Context`, fichier `.jsx`.

```
AuthContext.jsx
CartContext.jsx
```

Usage interne :

```js
// GOOD
const AuthContext   = createContext();
const CartContext   = createContext();

// Provider associé dans le même fichier
export const AuthProvider = ({ children }) => { ... }
```

### Fichiers utilitaires

```
// utils/
formatPrice.js
formatDate.js
validateEmail.js
slugify.js

// api/
productApi.js
orderApi.js
authApi.js

// services/
cartService.js
storageService.js
```

---

## 4. Base de données — MySQL

### Tables

Convention : `snake_case`, **singulier**, anglais.

```sql
-- GOOD
product
brand
skin_concern
shop_order       -- "order" est réservé en SQL
order_item
payment
user
routine
contact_message

-- BAD
Products         -- pluriel
Produit          -- français
productTable     -- camelCase + suffixe
```

### Colonnes

Convention : `snake_case`, anglais.

```sql
-- GOOD
first_name
last_name
created_at
updated_at
is_available
unit_price
password_hash

-- BAD
firstName        -- camelCase
prenom           -- français
creation_date_of_the_product  -- trop verbeux
```

Colonnes techniques standard à inclure sur toutes les entités persistées :

```sql
created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### Clés primaires et étrangères

```sql
-- Clé primaire
id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY

-- Clés étrangères : <table_référencée>_id
brand_id
user_id
order_id
skin_concern_id
routine_id

-- GOOD
product.brand_id -> brand.id
shop_order.user_id -> user.id
order_item.order_id -> shop_order.id

-- BAD
product.id_brand
product.brandFK
product.brand
```

---

## 5. API REST

### Routes

Convention : `kebab-case`, **pluriel**, préfixe `/api/`.  
Pas de verbes dans les routes — l'action est portée par la méthode HTTP.

```
// GOOD
GET    /api/products
GET    /api/products/{id}
POST   /api/products
PUT    /api/products/{id}
DELETE /api/products/{id}

GET    /api/skin-concerns
GET    /api/skin-concerns/{slug}/products

GET    /api/orders
POST   /api/orders
GET    /api/orders/{id}

POST   /api/auth/login
POST   /api/auth/register
POST   /api/auth/logout

// BAD
GET    /api/getProducts        // verbe dans la route
GET    /api/product            // singulier
GET    /api/Products           // majuscule
POST   /api/createOrder        // verbe
```

### Méthodes HTTP

| Méthode | Usage | Exemple |
|---|---|---|
| `GET` | Lecture, liste ou détail | `GET /api/products` |
| `POST` | Création d'une ressource | `POST /api/orders` |
| `PUT` | Remplacement complet | `PUT /api/products/{id}` |
| `PATCH` | Modification partielle | `PATCH /api/orders/{id}` |
| `DELETE` | Suppression | `DELETE /api/products/{id}` |

### Codes de réponse

| Code | Signification | Usage typique |
|---|---|---|
| `200 OK` | Succès | GET, PUT, PATCH |
| `201 Created` | Ressource créée | POST |
| `204 No Content` | Succès sans corps | DELETE |
| `400 Bad Request` | Données invalides | Validation échouée |
| `401 Unauthorized` | Non authentifié | JWT manquant ou expiré |
| `403 Forbidden` | Non autorisé | Rôle insuffisant |
| `404 Not Found` | Ressource introuvable | ID inexistant |
| `422 Unprocessable Entity` | Erreur de validation métier | Contrainte BDD |
| `500 Internal Server Error` | Erreur serveur | Exception non gérée |

---

## 6. Git

### Branches

```
main       — production uniquement, protégée
develop    — branche d'intégration

feature/*  — nouvelle fonctionnalité
fix/*      — correction de bug
hotfix/*   — correction urgente en production
chore/*    — tâche technique sans impact fonctionnel (deps, config)

// Exemples
feature/product-crud
feature/cart-system
feature/payment-stripe
feature/skin-concern-filter
feature/auth-jwt

fix/order-validation
fix/cart-quantity-update

hotfix/payment-crash-prod

chore/docker-setup
chore/eslint-config
```

Règle : **une branche = une fonctionnalité**. Jamais de travail direct sur `main` ou `develop`.

### Messages de commit

Convention : `type(scope): description courte` — inspiré de [Conventional Commits](https://www.conventionalcommits.org).

```
// Format
type(scope): description en minuscules, présent, sans point final

// Types
feat      — nouvelle fonctionnalité
fix       — correction de bug
refactor  — refactoring sans changement de comportement
style     — formatage, indentation (pas de logique)
test      — ajout ou modification de tests
docs      — documentation uniquement
chore     — configuration, dépendances, CI/CD
perf      — amélioration de performance

// Exemples GOOD
feat(product): add filter by skin concern
feat(auth): implement JWT login endpoint
fix(cart): correct quantity update on item removal
refactor(order): extract payment logic to PaymentService
docs(api): add endpoint documentation for /api/orders
chore(docker): add nginx configuration
test(product): add unit tests for ProductService

// Exemples BAD
update stuff
WIP
fix
feat: added the product thing and also fixed the cart bug and updated readme
```

---

## 7. Docker

### Containers

Convention : `kebab-case`, préfixe `volo-`.

```yaml
# docker-compose.yml
services:
  volo-api:       # Symfony
  volo-react:     # React
  volo-db:        # MySQL
  volo-nginx:     # Reverse proxy
  volo-mailer:    # Mailpit (dev)
```

### Images

```
volo-api-image
volo-react-image
```

### Variables d'environnement

Convention : `UPPER_SNAKE_CASE`, préfixe selon le service.

```env
# Symfony
APP_ENV=dev
APP_SECRET=...
DATABASE_URL=mysql://user:password@volo-db:3306/volo
JWT_SECRET_KEY=...
JWT_PUBLIC_KEY=...
JWT_PASSPHRASE=...
CORS_ALLOW_ORIGIN=http://localhost:5173

# React (Vite)
VITE_API_BASE_URL=http://localhost:8000
```

---

## 8. Fichiers et assets

### Fichiers PHP

```
PascalCase.php

Product.php
ProductController.php
ProductService.php
ProductRepository.php
CreateProductDTO.php
```

### Fichiers React

```
PascalCase.jsx       — composants et pages
camelCase.js         — hooks, utils, services, api

ProductCard.jsx
HomePage.jsx
useCart.js
formatPrice.js
productApi.js
```

### Fichiers CSS / styles

```
kebab-case.css

product-card.css
checkout-form.css
homepage.css
```

### Images et médias

```
kebab-case.webp      — format WebP privilégié
kebab-case.svg       — icônes et logos

// GOOD
cleanser-gel-100ml.webp
volo-logo.svg
placeholder-product.webp

// BAD
CleanserGel.jpg
produit1.png
IMG_20240101.jpeg
```

---

## 9. Convention de commentaires

Chaque fichier métier important commence par un bloc de documentation standardisé.  
Objectif : comprendre le rôle du fichier sans lire son code.

### Entité Doctrine

```php
<?php

/*
===============================================================================
Entity : Product
===============================================================================
Purpose:
    Represents a skincare product available on the VOLO platform.

Responsibilities:
    - Store product information (name, description, price, availability).
    - Define the relationship with Brand (many-to-one).
    - Define the relationship with SkinConcern (many-to-many).
    - Define the relationship with OrderItem (one-to-many).
    - Be persisted and managed by Doctrine ORM.

Main Properties:
    id
    name
    description
    price
    isAvailable
    brand         (ManyToOne -> Brand)
    skinConcerns  (ManyToMany -> SkinConcern)
    createdAt
    updatedAt

Related Entities:
    Brand, SkinConcern, OrderItem, Routine
===============================================================================
*/

namespace App\Entity;
```

### Service

```php
<?php

/*
===============================================================================
Service : ProductService
===============================================================================
Purpose:
    Centralizes all business logic related to products.

Responsibilities:
    - Create, update, and delete products.
    - Validate business constraints (price > 0, name not empty…).
    - Filter products by skin concern, brand, availability.
    - Prepare paginated data for API responses.

Dependencies:
    - ProductRepository
    - EntityManagerInterface

Used By:
    - ProductController

Throws:
    - InvalidArgumentException  if validation fails
    - EntityNotFoundException   if product not found
===============================================================================
*/

namespace App\Service;
```

### Contrôleur API

```php
<?php

/*
===============================================================================
Controller : ProductController
===============================================================================
Purpose:
    Exposes REST endpoints for product management.

Available Endpoints:
    GET     /api/products                    List all products (paginated)
    GET     /api/products/{id}               Get a single product
    POST    /api/products                    Create a product
    PUT     /api/products/{id}               Replace a product
    DELETE  /api/products/{id}               Delete a product

Query Parameters (GET /api/products):
    ?skin_concern={slug}   Filter by skin concern slug
    ?brand={id}            Filter by brand id
    ?page={n}              Pagination (default: 1)
    ?limit={n}             Items per page (default: 20)

Security:
    Public   : GET endpoints
    Admin    : POST, PUT, DELETE (role ROLE_ADMIN required)

Dependencies:
    - ProductService
    - SerializerInterface
===============================================================================
*/

namespace App\Controller;
```

### Composant React

```jsx
/**
 * ProductCard
 * -----------
 * Purpose:
 *   Displays a single product in the catalogue grid.
 *
 * Props:
 *   @param {Object}   product           - Product data object
 *   @param {string}   product.id        - Product unique identifier
 *   @param {string}   product.name      - Product name
 *   @param {string}   product.price     - Formatted price string
 *   @param {string}   product.imageUrl  - Product image URL
 *   @param {boolean}  product.isAvailable
 *   @param {Function} onAddToCart       - Callback triggered on CTA click
 *
 * Used By:
 *   ProductGrid, SkinConcernPage, SearchResultsPage
 *
 * Notes:
 *   - Displays an "unavailable" badge when isAvailable is false.
 *   - Lazy-loads the product image.
 */

const ProductCard = ({ product, onAddToCart }) => {
```
---

## 10. Enums Symfony

Convention : `PascalCase`, fichier dans `src/Enum/`.  
Utiliser des enums natifs PHP 8.1 pour toutes les valeurs à choix contraint.

```php
// GOOD
enum OrderStatus: string
{
    case PENDING   = 'pending';
    case PAID      = 'paid';
    case SHIPPED   = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}

enum PaymentStatus: string
{
    case PENDING   = 'pending';
    case CAPTURED  = 'captured';
    case FAILED    = 'failed';
    case REFUNDED  = 'refunded';
}

enum PaymentMethod: string
{
    case CARD   = 'card';
    case PAYPAL = 'paypal';
}

enum UserRole: string
{
    case USER  = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';
}

enum RoutineLevel: string
{
    case BEGINNER     = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED     = 'advanced';
}
```

Règle : **jamais stocker de chaînes libres** pour des valeurs à ensemble fini.

---

## 11. DTO — Lecture et écriture

Convention : `Action + Entité + DTO` pour l'écriture, `Entité + ResponseDTO` pour la lecture.

```php
// DTO d'écriture
CreateProductDTO
UpdateProductDTO
CreateOrderDTO
RegisterUserDTO

// DTO de lecture
ProductResponseDTO
OrderResponseDTO
UserResponseDTO
```

Règle : **les entités Doctrine ne sont jamais exposées directement dans les réponses API.**  
Toujours sérialiser via un `ResponseDTO`.

---

## 12. Tests

Convention : `NomDeLaClasse + Test`, méthode au format `testMethode_Scenario_ResultatAttendu`.

```
tests/
├── Unit/
│   ├── ProductServiceTest.php
│   └── OrderServiceTest.php
├── Integration/
│   └── ProductRepositoryTest.php
└── Functional/
    ├── ProductControllerTest.php
    └── CheckoutWorkflowTest.php
```

Méthodes :

```php
// GOOD
testCreateProduct_WithValidData_ShouldPersistProduct()
testCreateProduct_WithNegativePrice_ShouldThrowException()
testUpdateOrder_WhenAlreadyCancelled_ShouldThrowException()

// BAD
testCreate()
test1()
checkProduct()
```

---

## 13. Migrations Doctrine

```
migrations/
└── Version20260610143000.php   ← horodatage automatique
```

Règles :
- **Jamais modifier une migration déjà exécutée.**
- **Toujours créer une nouvelle migration** pour corriger une erreur de schéma.
- Générer via : `php bin/console make:migration`
- Appliquer via : `php bin/console doctrine:migrations:migrate`

---

## 14. Format des réponses API

Toutes les réponses de l'API suivent le même enveloppe JSON.

Succès :

```json
{
  "data": {
    "id": 1,
    "name": "Hydrating Cleanser"
  }
}
```

Liste paginée :

```json
{
  "data": [...],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 143
  }
}
```

Erreur :

```json
{
  "error": {
    "code": "PRODUCT_NOT_FOUND",
    "message": "Aucun produit trouvé avec l'identifiant 42."
  }
}
```

Règle : React consomme toujours `response.data` pour les données, `response.error` pour les erreurs.
