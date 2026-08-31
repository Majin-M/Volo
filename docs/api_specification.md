# Spécification API — Projet VOLO

> ⚠️ **Plus de la moitié de ce document décrit une cible, pas l'API.** Confronté aux routes réelles (`php bin/console debug:router`) le 17/07/2026 : sur ~20 endpoints documentés, **11 existent**.
>
> Chaque section porte désormais ✅ **implémenté** ou ⬜ **prévu**. Un ⬜ signifie que la route renvoie **404** — pas qu'elle est incomplète.
>
> C'est exactement la dérive annoncée en [CONTRAT_API.md](CONTRAT_API.md) §8 : « `api_specification.md` peut mentir sans que rien ne le signale ». L'exemple qui y était donné (l'enveloppe d'erreur) était de loin le plus petit des écarts.

## Ce qui existe réellement — vue d'ensemble

| Endpoint | État |
|---|---|
| `POST /api/auth/register` · `login` · `logout` | ✅ |
| `GET /api/auth/me` | ✅ — **et non `/api/users/me`** (§9) |
| `GET /api/products` · `GET /api/products/{id}` | ✅ |
| `GET /api/brands` | ✅ |
| `GET /api/skin-concerns` | ✅ |
| `GET /api/orders` · `POST /api/orders` | ✅ |
| `POST /api/payments` | ✅ |
| `POST /api/contact` | ✅ |
| `GET /sitemap.xml` | ✅ (hors contrat d'API — [CONTRAT_API.md](CONTRAT_API.md) §7) |
| `POST` · `PUT` · `DELETE /api/products` | ⬜ roadmap 2.6 🔴 |
| `POST` · `PUT` · `DELETE /api/brands` | ⬜ |
| `GET /api/products/{id}` détaillé, `GET /api/brands/{id}/products` | ⬜ |
| `GET /api/skin-concerns/{slug}/products` | ⬜ |
| `GET /api/routines` | ⬜ roadmap 2.9 — aucun `RoutineController` |
| `GET /api/orders/{id}` · `PATCH /api/orders/{id}` | ⬜ |
| `GET` · `PATCH /api/users/me` | ⬜ |
| **Tout le §11 Administration** (`/api/admin/*`) | ⬜ — **aucune** de ces routes n'existe |

Deux pièges que cette liste rend visibles :

- **`security.yaml` protège des routes inexistantes** (`^/api/routines`, `POST /api/products`). Une règle d'`access_control` sur une route absente ne protège rien — elle fait croire que la route existe.
- **Le back-office n'est pas une API.** Produits, marques et commandes se gèrent aujourd'hui par EasyAdmin (Twig, `/admin/*`), pas par `/api/admin/*`. Le §11 n'a jamais été construit parce qu'EasyAdmin l'a rendu inutile.

## Table des matières

1. [Conventions générales](#1-conventions-générales)
2. [Authentification](#2-authentification)
3. [Produits](#3-produits)
4. [Marques](#4-marques)
5. [Problématiques peau](#5-problématiques-peau)
6. [Routines](#6-routines)
7. [Panier & Commandes](#7-panier--commandes)
8. [Paiement](#8-paiement)
9. [Compte utilisateur](#9-compte-utilisateur)
10. [Contact](#10-contact)
11. [Administration](#11-administration)

---

## 1. Conventions générales

### URL de base

```
http://localhost:8000/api     (développement)
https://api.volo.fr/api       (production)
```

### Format des réponses

Toutes les réponses suivent la même enveloppe :

**Succès — ressource unique :**
```json
{
  "data": {
    "id": 1,
    "name": "Hydrating Cleanser"
  }
}
```

**Succès — liste paginée :**
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

**Erreur :**
```json
{
  "error": {
    "code": "PRODUCT_NOT_FOUND",
    "message": "Aucun produit trouvé avec l'identifiant 42."
  }
}
```

### Authentification

Les routes protégées nécessitent un header :

```
Authorization: Bearer <jwt_token>
```

### Codes de réponse

| Code | Signification | Usage |
|---|---|---|
| `200 OK` | Succès | GET, PUT, PATCH |
| `201 Created` | Ressource créée | POST |
| `204 No Content` | Succès sans corps | DELETE |
| `400 Bad Request` | Données invalides | Validation échouée |
| `401 Unauthorized` | Non authentifié | JWT manquant ou expiré |
| `403 Forbidden` | Non autorisé | Rôle insuffisant |
| `404 Not Found` | Ressource introuvable | ID inexistant |
| `422 Unprocessable Entity` | Erreur métier | Contrainte BDD |
| `500 Internal Server Error` | Erreur serveur | Exception non gérée |

---

## 2. Authentification

> ⚠️ **Les exemples de cette section sont périmés sur un point central : le jeton n'est pas dans le corps de la réponse.**
>
> Ils montrent `{"data": {"token": "eyJ0eXAi...", "user": {...}}}`. La réponse réelle est `{"data": {"user": {...}}}` : le JWT est posé dans un cookie `HttpOnly` `volo_token`, accompagné d'un cookie `volo_csrf` lisible en JS. C'est le choix documenté en [CONTRAT_API.md](CONTRAT_API.md) §1, et **suivre ces exemples reviendrait à rendre le jeton lisible par le JavaScript** — précisément ce qu'on a voulu éviter.
>
> `AuthControllerTest::testRegister_Success` vérifie désormais l'absence de `token` dans le corps et la présence des deux cookies avec les bons drapeaux. La forme réelle est donc tenue par un test, plus seulement par ce document.
>
> Le champ `"role"` des exemples n'est pas renvoyé par `register` (il l'est par `GET /api/auth/me`, sous la clé `role`, et vaut un **tableau**).

### POST /api/auth/register

Création d'un compte client.

**Accès :** Public

**Corps de la requête :**
```json
{
  "email": "user@example.com",
  "password": "motdepasse123",
  "firstName": "Sophie",
  "lastName": "Martin"
}
```

**Réponse 201 :**
```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1Q...",
    "user": {
      "id": 12,
      "email": "user@example.com",
      "firstName": "Sophie",
      "lastName": "Martin",
      "role": "ROLE_USER"
    }
  }
}
```

> **Note :** un JWT est renvoyé immédiatement après l'inscription (comme pour `POST /api/auth/login`), afin de permettre un auto-login côté front sans appel supplémentaire.

---

### POST /api/auth/login

Authentification et récupération du token JWT.

**Accès :** Public

**Corps de la requête :**
```json
{
  "email": "user@example.com",
  "password": "motdepasse123"
}
```

**Réponse 200 :**
```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1Q...",
    "user": {
      "id": 12,
      "email": "user@example.com",
      "firstName": "Sophie",
      "role": "ROLE_USER"
    }
  }
}
```

---

### POST /api/auth/logout

Invalidation du token côté client.

**Accès :** `ROLE_USER`

**Réponse 204** (pas de corps)

---

## 3. Produits

### GET /api/products

Liste paginée des produits.

**Accès :** Public

**Query parameters :**

| Paramètre | Type | Description | Exemple |
|---|---|---|---|
| `page` | int | Numéro de page (défaut : 1) | `?page=2` |
| `limit` | int | Items par page (défaut : 20, max : 50) | `?limit=10` |
| `brand` | int | Filtrer par ID de marque | `?brand=3` |
| `skin_concern` | string | Filtrer par slug de problématique | `?skin_concern=acne` |
| `available` | bool | Filtrer les produits disponibles | `?available=true` |

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Hydrating Cleanser",
      "price": 24.90,
      "imageUrl": "/media/products/hydrating-cleanser.webp",
      "isAvailable": true,
      "brand": {
        "id": 2,
        "name": "CeraVe"
      },
      "skinConcerns": [
        { "id": 1, "name": "Sécheresse", "slug": "secheresse" }
      ]
    }
  ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 87
  }
}
```

---

### GET /api/products/{id}

Détail d'un produit.

**Accès :** Public

**Réponse 200 :**
```json
{
  "data": {
    "id": 1,
    "name": "Hydrating Cleanser",
    "description": "Nettoyant doux pour peaux sèches...",
    "price": 24.90,
    "imageUrl": "/media/products/hydrating-cleanser.webp",
    "isAvailable": true,
    "brand": {
      "id": 2,
      "name": "CeraVe",
      "logoUrl": "/media/brands/cerave-logo.svg"
    },
    "skinConcerns": [
      { "id": 1, "name": "Sécheresse", "slug": "secheresse" }
    ],
    "routines": [
      { "id": 3, "name": "Routine hydratation débutant", "level": "beginner" }
    ],
    "createdAt": "2026-01-15T10:30:00Z"
  }
}
```

**Réponse 404 :**
```json
{
  "error": {
    "code": "PRODUCT_NOT_FOUND",
    "message": "Aucun produit trouvé avec l'identifiant 99."
  }
}
```

---

### POST /api/products — ⬜ N'EXISTE PAS

> Renvoie **404**. Aucun contrôleur ne l'implémente (roadmap 2.6 🔴). `security.yaml` déclare pourtant une règle `ROLE_ADMIN` pour cette méthode. Les produits se créent uniquement via EasyAdmin (`/admin/product/new`).

Création d'un produit.

**Accès :** `ROLE_ADMIN`

**Corps de la requête :**
```json
{
  "name": "Vitamin C Serum",
  "description": "Sérum à la vitamine C...",
  "price": 34.90,
  "isAvailable": true,
  "brandId": 2,
  "skinConcernIds": [1, 4]
}
```

**Réponse 201 :** même structure que GET /api/products/{id}

---

### PUT /api/products/{id} — ⬜ N'EXISTE PAS

> Renvoie **404**. Même situation que `POST` ci-dessus.

Remplacement complet d'un produit.

**Accès :** `ROLE_ADMIN`

**Corps de la requête :** même structure que POST

**Réponse 200 :** même structure que GET /api/products/{id}

---

### DELETE /api/products/{id} — ⬜ N'EXISTE PAS

> Renvoie **404**. Même situation que `POST` et `PUT` ci-dessus.

Suppression d'un produit.

**Accès :** `ROLE_ADMIN`

**Réponse 204** (pas de corps)

---

## 4. Marques

### GET /api/brands

Liste de toutes les marques.

**Accès :** Public

**Réponse 200 :**
```json
{
  "data": [
    { "id": 1, "name": "La Roche-Posay", "logoUrl": "/media/brands/lrp-logo.svg" },
    { "id": 2, "name": "CeraVe", "logoUrl": "/media/brands/cerave-logo.svg" }
  ]
}
```

---

### GET /api/brands/{id}/products — ⬜ N'EXISTE PAS

> Renvoie **404**. Le filtrage par marque passe aujourd'hui par `GET /api/products?brand={id}`, qui est implémenté.

Produits d'une marque donnée.

**Accès :** Public

**Réponse 200 :** même structure que GET /api/products (paginée)

---

## 5. Problématiques peau

### GET /api/skin-concerns

Liste de toutes les problématiques.

**Accès :** Public

**Réponse 200 :**
```json
{
  "data": [
    { "id": 1, "name": "Sécheresse", "slug": "secheresse", "description": "Peaux manquant d'hydratation..." },
    { "id": 2, "name": "Acné", "slug": "acne", "description": "Peaux à tendance acnéique..." }
  ]
}
```

---

### GET /api/skin-concerns/{slug}/products — ⬜ N'EXISTE PAS

> Renvoie **404**. Le filtrage passe par `GET /api/products?skin_concern={slug}`, qui est implémenté. C'est cet usage que sert le `slug` de RG9.

Produits recommandés pour une problématique.

**Accès :** Public

**Réponse 200 :** même structure que GET /api/products (paginée)

---

## 6. Routines — ⬜ SECTION ENTIÈREMENT NON IMPLÉMENTÉE

> Renvoie **404** : il n'existe aucun `RoutineController` (roadmap 2.9 ⬜ 🟡). L'entité `Routine` et la table `routine_product` existent en base, mais rien ne les expose. `security.yaml` déclare une règle `PUBLIC_ACCESS` pour `^/api/routines` — une règle sur une route absente.
>
> L'exemple ci-dessous montre par ailleurs une clé `skinConcern` sur une routine : **cette relation n'existe pas** au modèle. `Routine` est liée aux produits (N-N), pas aux problématiques ([MODELE_DONNEES.md](MODELE_DONNEES.md) §3).

### GET /api/routines

Liste des routines disponibles.

**Accès :** Public

**Query parameters :**

| Paramètre | Type | Description |
|---|---|---|
| `level` | string | `beginner`, `intermediate`, `advanced` |
| `skin_concern` | string | Slug de la problématique |

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Routine hydratation débutant",
      "level": "beginner",
      "skinConcern": { "id": 1, "name": "Sécheresse" },
      "products": [
        { "id": 1, "name": "Hydrating Cleanser", "price": 24.90 }
      ]
    }
  ]
}
```

---

## 7. Panier & Commandes

### POST /api/orders

Création d'une commande depuis le panier.

**Accès :** `ROLE_USER`

**Corps de la requête :**
```json
{
  "items": [
    { "productId": 1, "quantity": 2 },
    { "productId": 5, "quantity": 1 }
  ],
  "shippingAddress": {
    "street": "12 rue de la Paix",
    "city": "Paris",
    "postalCode": "75001",
    "country": "France"
  }
}
```

**Réponse 201 :**
```json
{
  "data": {
    "id": 42,
    "status": "pending",
    "total": 74.70,
    "items": [
      { "productId": 1, "productName": "Hydrating Cleanser", "quantity": 2, "unitPrice": 24.90 },
      { "productId": 5, "productName": "Vitamin C Serum", "quantity": 1, "unitPrice": 34.90 }
    ],
    "createdAt": "2026-06-10T14:30:00Z"
  }
}
```

---

### GET /api/orders

Historique des commandes de l'utilisateur connecté.

**Accès :** `ROLE_USER`

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": 42,
      "status": "delivered",
      "total": 74.70,
      "createdAt": "2026-06-10T14:30:00Z"
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 5 }
}
```

---

### GET /api/orders/{id} — ⬜ N'EXISTE PAS

> Renvoie **404**. À noter pour qui lit [STRATEGIE_TESTS.md](STRATEGIE_TESTS.md) : §1 et §10 désignent cette route comme la fuite de données la plus probable (« un client lit la commande d'un autre ») et proposent d'écrire le test qui la révélerait. **Ce test ne peut pas échouer : la route n'existe pas.** Le risque décrit est réel, mais il ne se matérialise pas ici. `GET /api/orders` ne renvoie que les commandes de l'utilisateur courant (`findByUser`).

Détail d'une commande.

**Accès :** `ROLE_USER` (propriétaire uniquement) ou `ROLE_ADMIN`

**Réponse 200 :** même structure que POST /api/orders

---

### PATCH /api/orders/{id} — ⬜ N'EXISTE PAS

> Renvoie **404**. Le statut d'une commande se change uniquement par EasyAdmin. C'est aussi ce qui limite la portée du défaut décrit en [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §4 (aucune transition contrainte) : il n'y a pas d'endpoint HTTP par lequel forcer un statut arbitraire.

Modification partielle du statut d'une commande.

**Accès :** `ROLE_ADMIN`

**Corps de la requête :**
```json
{
  "status": "shipped"
}
```

**Réponse 200 :** commande mise à jour

---

## 8. Paiement

### POST /api/payments

Initiation d'un paiement pour une commande.

**Accès :** `ROLE_USER`

**Corps de la requête :**
```json
{
  "orderId": 42,
  "method": "card"
}
```

**Réponse 201 :**
```json
{
  "data": {
    "paymentId": 18,
    "status": "pending",
    "clientSecret": "pi_3Oxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx_secret_xxx"
  }
}
```

---

## 9. Compte utilisateur

> ⚠️ **La route s'appelle `GET /api/auth/me`, pas `/api/users/me`.** C'est un piège concret : un développeur front qui suit ce document reçoit un 404 sans comprendre pourquoi. `AuthContext` l'utilise pour restaurer la session au montage ([CONTRAT_API.md](CONTRAT_API.md) §1).
>
> `PATCH /api/users/me` **n'existe pas** (roadmap 2.11 ⬜ 🟠) : le profil n'est pas modifiable par l'API.

### GET /api/users/me → en réalité `GET /api/auth/me`

Profil de l'utilisateur connecté.

**Accès :** `ROLE_USER`

**Réponse 200 :**
```json
{
  "data": {
    "id": 12,
    "email": "user@example.com",
    "firstName": "Sophie",
    "lastName": "Martin",
    "createdAt": "2026-01-01T00:00:00Z"
  }
}
```

---

### PATCH /api/users/me — ⬜ N'EXISTE PAS

> Renvoie **404** (roadmap 2.11).

Mise à jour du profil.

**Accès :** `ROLE_USER`

**Corps de la requête :**
```json
{
  "firstName": "Sophie",
  "lastName": "Dupont"
}
```

**Réponse 200 :** profil mis à jour

---

## 10. Contact

### POST /api/contact

Envoi d'un message de contact.

**Accès :** Public

**Corps de la requête :**
```json
{
  "firstName": "Sophie",
  "email": "user@example.com",
  "subject": "Question sur une commande",
  "message": "Bonjour, je souhaite..."
}
```

**Réponse 201 :**
```json
{
  "data": {
    "message": "Votre message a bien été envoyé."
  }
}
```

---

## 11. Administration — ⬜ SECTION ENTIÈREMENT NON IMPLÉMENTÉE

> ⚠️ **Aucune des dix routes ci-dessous n'existe.** Toutes renvoient **404**. C'est la section la plus trompeuse de ce document : elle décrit une API d'administration qui n'a jamais été écrite.
>
> **Et elle ne le sera probablement pas.** L'administration passe par **EasyAdmin** — du Twig rendu serveur sous `/admin/*`, hors du contrat d'API par construction ([CONTRAT_API.md](CONTRAT_API.md) §7). Les écrans existent déjà : produits, marques, problématiques, commandes, paiements, utilisateurs. Construire `/api/admin/*` en plus serait une seconde implémentation du même besoin.
>
> Ce qu'il faut trancher : **retirer cette section** (l'administration est un choix d'architecture assumé, pas une dette), ou la conserver comme cible explicite pour un futur client d'administration découplé. La garder telle quelle est le pire des trois — elle laisse croire que ces routes répondent.

| Méthode | Route | Description | État |
|---|---|---|---|
| `GET` | `/api/admin/orders` | Toutes les commandes | ⬜ → `/admin/order` (EasyAdmin) |
| `GET` | `/api/admin/users` | Tous les utilisateurs | ⬜ → `/admin/user` |
| `PATCH` | `/api/admin/orders/{id}` | Modifier le statut | ⬜ → `/admin/order/{id}/edit` |
| `POST` | `/api/products` | Créer un produit | ⬜ roadmap 2.6 🔴 |
| `PUT` | `/api/products/{id}` | Modifier un produit | ⬜ |
| `DELETE` | `/api/products/{id}` | Supprimer un produit | ⬜ |
| `POST` | `/api/brands` | Créer une marque | ⬜ |
| `PUT` | `/api/brands/{id}` | Modifier une marque | ⬜ |
| `DELETE` | `/api/brands/{id}` | Supprimer une marque | ⬜ |
| `GET` | `/api/admin/contact-messages` | Messages non traités | ⬜ — aucun écran EasyAdmin non plus |

La dernière ligne (`GET /api/admin/contact-messages`) **ne sera pas implémentée**, et c'est délibéré.

> ✅ **Le formulaire de contact fonctionne depuis le 17/07/2026** — il était cassé des deux bouts :
>
> 1. **Rien n'entrait** : `POST /api/contact` est `PUBLIC_ACCESS` mais n'était pas exempté du contrôle CSRF, or le cookie `volo_csrf` n'est posé qu'au login. Tout visiteur anonyme recevait **403**.
> 2. **Rien n'était lu** : aucun endpoint, aucun écran d'administration, aucune notification. Les messages s'empilaient en base.
>
> Les deux sont corrigés. `ContactService` **persiste le message et notifie l'administrateur par email** : la base est la trace durable (un envoi raté ne perd rien), l'email est ce qui fait arriver le message à un humain.
>
> **C'est pourquoi cette route d'administration n'a plus d'objet** : l'administrateur traite dans sa boîte mail, où il a déjà « lu / non lu », archives et réponses. RG12 et `processed_by_user_id` sont abandonnés pour la même raison — voir [MODELE_DONNEES.md](MODELE_DONNEES.md) §6.5. `ContactMessage` est une **archive**, pas un outil de travail.
>
> Couvert par `tests/Service/ContactNotificationTest.php` (6 tests) et `tests/Security/CsrfProtectionTest.php`.
