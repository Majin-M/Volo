# Spécification API — Projet VOLO

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

### POST /api/products

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

### PUT /api/products/{id}

Remplacement complet d'un produit.

**Accès :** `ROLE_ADMIN`

**Corps de la requête :** même structure que POST

**Réponse 200 :** même structure que GET /api/products/{id}

---

### DELETE /api/products/{id}

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

### GET /api/brands/{id}/products

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

### GET /api/skin-concerns/{slug}/products

Produits recommandés pour une problématique.

**Accès :** Public

**Réponse 200 :** même structure que GET /api/products (paginée)

---

## 6. Routines

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

### GET /api/orders/{id}

Détail d'une commande.

**Accès :** `ROLE_USER` (propriétaire uniquement) ou `ROLE_ADMIN`

**Réponse 200 :** même structure que POST /api/orders

---

### PATCH /api/orders/{id}

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

### GET /api/users/me

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

### PATCH /api/users/me

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

## 11. Administration

Toutes les routes d'administration nécessitent `ROLE_ADMIN`.

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/api/admin/orders` | Toutes les commandes (paginées, filtrables) |
| `GET` | `/api/admin/users` | Tous les utilisateurs |
| `PATCH` | `/api/admin/orders/{id}` | Modifier le statut d'une commande |
| `POST` | `/api/products` | Créer un produit |
| `PUT` | `/api/products/{id}` | Modifier un produit |
| `DELETE` | `/api/products/{id}` | Supprimer un produit |
| `POST` | `/api/brands` | Créer une marque |
| `PUT` | `/api/brands/{id}` | Modifier une marque |
| `DELETE` | `/api/brands/{id}` | Supprimer une marque |
| `GET` | `/api/admin/contact-messages` | Messages de contact non traités |
