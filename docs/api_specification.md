# Spécification API — Projet VOLO

> ⚠️ **Mise à jour du 30/08/2026** : plusieurs routes auparavant marquées ⬜ sont désormais implémentées — `POST`/`PUT`/`DELETE /api/products` (via `ProductVoter`), `PATCH /api/auth/me`, `POST /api/webhooks/stripe`. Les marqueurs ✅/⬜ ont été recalés sur l'état réel du code.
>
> Chaque section porte ✅ **implémenté** ou ⬜ **prévu**. Un ⬜ signifie que la route renvoie **404** — pas qu'elle est incomplète.

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
| `POST /api/webhooks/stripe` | ✅ — webhook Stripe (signature HMAC) |
| `GET /sitemap.xml` | ✅ (hors contrat d'API — [CONTRAT_API.md](CONTRAT_API.md) §7) |
| `POST` · `PUT` · `DELETE /api/products` | ✅ — `ROLE_ADMIN` via `ProductVoter` |
| `POST` · `PUT` · `DELETE /api/brands` | ⬜ |
| `GET /api/products/{id}` détaillé, `GET /api/brands/{id}/products` | ⬜ |
| `GET /api/skin-concerns/{slug}/products` | ⬜ |
| `GET /api/routines` | ✅ Implémenté le 14/09/2026 (`RoutineController`), filtrable par `level` et `skin_concern` |
| `GET /api/orders/{id}` · `PATCH /api/orders/{id}` | ⬜ |
| `GET /api/auth/me` · `PATCH /api/auth/me` | ✅ — profil + mise à jour (rate limited) |
| **Tout le §11 Administration** (`/api/admin/*`) | ⬜ — **aucune** de ces routes n'existe |

Deux pièges que cette liste rend visibles :

- **`security.yaml` protégeait des routes inexistantes.** Les deux cas cités — `^/api/routines` et `POST /api/products` — sont désormais implémentés, la règle correspond donc à quelque chose. Le principe reste à retenir : une règle d'`access_control` sur une route absente ne protège rien, elle fait seulement croire que la route existe.
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
    "code": 404,
    "message": "Ressource introuvable."
  }
}
```

> `code` est le **statut HTTP en entier**, pas un symbole métier : il n'existe aucun identifiant du
> type `PRODUCT_NOT_FOUND` dans le code.
>
> **Cette enveloppe est universelle sur `/api/*`** depuis le 14/09/2026, quelle que soit l'origine
> de l'erreur : retour direct d'un contrôleur, exception interceptée par `ExceptionSubscriber`,
> rejet CSRF, ou requête non authentifiée arrêtée par le firewall. Toutes passent par la même
> fabrique, `App\Http\ApiError` — c'est le seul endroit du code où la forme est décidée.
>
> En production, les messages des erreurs 500 sont remplacés par un texte générique.

### Authentification

Le jeton JWT voyage dans un **cookie `HttpOnly` nommé `volo_token`**, posé par l'API à la connexion et renvoyé automatiquement par le navigateur.

```
Cookie: volo_token=<jwt>          ← posé et lu par le serveur, invisible en JavaScript
X-Csrf-Token: <valeur du cookie volo_csrf>   ← requis sur POST/PUT/PATCH/DELETE
```

> ⚠️ **L'en-tête `Authorization: Bearer` ne fonctionne pas.** `lexik_jwt_authentication.yaml` déclare
> `authorization_header.enabled: false` et `cookie.enabled: true` : un Bearer est silencieusement ignoré
> et la requête repart en `401`. Le raisonnement derrière ce choix — et pourquoi `localStorage` a été
> écarté — est dans [CONTRAT_API.md](CONTRAT_API.md) §1.
>
> En pratique, avec curl : `curl -c jar -b jar` pour conserver le cookie, jamais `-H "Authorization: ..."`.

### Codes de réponse

| Code | Signification | Usage |
|---|---|---|
| `200 OK` | Succès | GET, PUT, PATCH, et `DELETE /api/products/{id}` (qui renvoie un message) |
| `201 Created` | Ressource créée | POST |
| `400 Bad Request` | Données invalides | Validation échouée, corps qui n'est pas un objet JSON, champ de type inattendu |
| `401 Unauthorized` | Non authentifié | Cookie JWT manquant ou expiré |
| `403 Forbidden` | Non autorisé | Rôle insuffisant, ou jeton CSRF absent/incorrect |
| `404 Not Found` | Ressource introuvable | ID inexistant, ou identifiant impossible (`/api/products/abc`) |
| `409 Conflict` | État incompatible | `POST /api/payments` sur une commande déjà payée, annulée, ou dont le paiement est finalisé |
| `429 Too Many Requests` | Quota dépassé | Limitation de débit (connexion, inscription, contact, profil) |
| `500 Internal Server Error` | Erreur serveur | Défaillance interne — **jamais** une faute du client (voir ci-dessous) |

> **Corrigé le 14/09/2026 :** ce tableau annonçait un `422 Unprocessable Entity` pour les « contraintes BDD ». Aucune route ne renvoie ce code : une violation de contrainte produisait en réalité un **500**, désormais évité par la validation en amont (400).

### Validation des entrées

Règles communes à toutes les routes, vérifiées le 14/09/2026 par un test de robustesse de 478 requêtes malformées (0 erreur 500) et figées dans `ApiInputRobustnessTest` :

| Entrée | Comportement |
|---|---|
| Corps de requête | Doit être un **objet JSON**. Corps vide, JSON invalide, scalaire (`"x"`, `123`) ou liste → `400` |
| Champ attendu en chaîne reçu dans un autre type (tableau, nombre…) | `400` |
| Entier attendu (quantité, identifiant, stock) | Entier JSON ou chaîne de chiffres uniquement. `"12,50"`, `2.5`, `true` → `400` (ils étaient auparavant convertis silencieusement : `"12,50"` devenait 12) |
| `{id}` de `/api/products/{id}` | Entier positif de 18 chiffres au plus ; sinon aucune route ne correspond → `404` |
| Mot de passe (inscription, changement) | 4 096 caractères au plus → sinon `400` |

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

> Un filtre de type invalide (`?brand=abc`, `?brand[]=1`, `?skin_concern[]=x`) ne correspond à aucun produit : **`200` avec une liste vide**. Il rendait auparavant un `500`.

**Réponse 200** (forme réelle, relevée le 14/09/2026) :
```json
{
  "data": [
    {
      "id": 1,
      "name": "Hydrating Cleanser",
      "description": "Nettoyant doux pour peaux sèches...",
      "price": "24.90",
      "isAvailable": true,
      "stock": 12,
      "brand": {
        "id": 2,
        "name": "CeraVe",
        "logoUrl": "cerave-logo.svg"
      },
      "skinConcerns": [
        { "id": 1, "name": "Sécheresse", "slug": "secheresse" }
      ],
      "createdAt": "2026-01-15T10:30:00+00:00",
      "imageUrl": "hydrating-cleanser.webp"
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

**Réponse 200** (forme réelle, relevée le 14/09/2026) :
```json
{
  "data": {
    "id": 1,
    "name": "Hydrating Cleanser",
    "description": "Nettoyant doux pour peaux sèches...",
    "price": "24.90",
    "isAvailable": true,
    "stock": 12,
    "brand": {
      "id": 2,
      "name": "CeraVe",
      "logoUrl": "cerave-logo.svg"
    },
    "skinConcerns": [
      { "id": 1, "name": "Sécheresse", "slug": "secheresse" }
    ],
    "createdAt": "2026-01-15T10:30:00+00:00",
    "imageUrl": "hydrating-cleanser.webp"
  }
}
```

> **Corrigé le 14/09/2026 :** l'exemple précédent montrait un `price` **numérique** et un champ **`routines`**. Le prix est une **chaîne** (Doctrine mappe `decimal` sur une `string` PHP), et aucun champ `routines` n'est exposé — il faut passer par `GET /api/routines`. Le champ `stock`, bien exposé, n'était pas mentionné.

**Réponse 404 :**
```json
{
  "error": {
    "code": 404,
    "message": "Produit introuvable."
  }
}
```

---

### POST /api/products — ✅ IMPLÉMENTÉ

Création d'un produit. Protégé par `ProductVoter::CREATE` (`ROLE_ADMIN`).

**Accès :** `ROLE_ADMIN`

**Corps de la requête :**
```json
{
  "name": "Vitamin C Serum",
  "description": "Sérum à la vitamine C...",
  "price": "34.90",
  "stock": 25,
  "isAvailable": true,
  "brandId": 2,
  "skinConcernIds": [1, 4]
}
```

**Règles de validation** (toute violation → `400`) :

| Champ | Règle |
|---|---|
| `name` | **Obligatoire** à la création. Chaîne non vide, 255 caractères au plus |
| `price` | **Obligatoire** à la création. Nombre ou chaîne numérique, entre 0,01 et 99 999 999,99 (colonne `DECIMAL(10,2)`) |
| `brandId` | **Obligatoire** à la création. Entier désignant une marque existante |
| `stock` | Entier entre 0 et 2 147 483 647 |
| `isAvailable` | Booléen strict : `"false"` en chaîne est refusé (`(bool) "false"` valait `true`) |
| `description` | Chaîne ou `null` |
| `skinConcernIds` | Liste d'entiers ; un identifiant inconnu est ignoré |

**Réponse 201 :** même structure que GET /api/products/{id}

---

### PUT /api/products/{id} — ✅ IMPLÉMENTÉ

Mise à jour d'un produit. Protégé par `ProductVoter::EDIT` (`ROLE_ADMIN`).

**Accès :** `ROLE_ADMIN`

**Corps de la requête :** mêmes champs et mêmes règles que POST, **tous facultatifs** : seuls les champs fournis sont modifiés. Malgré le verbe `PUT`, la mise à jour est donc partielle, comme un `PATCH`.

**Réponse 200 :** même structure que GET /api/products/{id}

---

### DELETE /api/products/{id} — ✅ IMPLÉMENTÉ

Suppression d'un produit. Protégé par `ProductVoter::DELETE` (`ROLE_ADMIN`).

**Accès :** `ROLE_ADMIN`

**Réponse 200 :**
```json
{ "message": "Produit supprime avec succes." }
```

> Un `204 No Content` serait plus conforme pour une suppression ; le contrôleur renvoie aujourd'hui
> un `200` avec un message, hors de l'enveloppe d'erreur standard. Écart connu, à harmoniser.

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
    { "id": 2, "name": "CeraVe", "logoUrl": "cerave-logo.svg" }
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

## 6. Routines — ✅ IMPLÉMENTÉ

> **Implémenté le 14/09/2026.** L'entité, son enum, son repository et sa table existaient depuis l'origine, mais rien ne les exposait ni ne les remplissait : la table était vide et le domaine inatteignable. Trois pièces manquaient et ont été ajoutées — `RoutineController` (cette section), `RoutineCrudController` (gestion en back-office) et `RoutineFixtures` (jeu de données).
>
> **Attention au modèle** : une routine n'est **pas** liée directement à une problématique de peau. Le lien passe par ses produits — `RoutineRepository::findByFilters()` joint `routine → produits → problématiques`. Filtrer sur `?skin_concern=acne` retourne donc les routines contenant au moins un produit qui cible l'acné. Une clé `skinConcern` directement sur une routine n'existe pas au modèle ([MODELE_DONNEES.md](MODELE_DONNEES.md) §3).

### GET /api/routines

Liste des routines disponibles.

**Accès :** Public

**Query parameters :**

| Paramètre | Type | Description |
|---|---|---|
| `level` | string | `beginner`, `intermediate`, `advanced` |
| `skin_concern` | string | Slug de la problématique |

**Réponse 200** (forme réelle, relevée le 14/09/2026) :
```json
{
  "data": [
    {
      "id": 1,
      "name": "Routine hydratation débutant",
      "level": "beginner",
      "description": "Deux étapes pour hydrater sans alourdir.",
      "products": [
        {
          "id": 1,
          "name": "Hydrating Cleanser",
          "description": "Nettoyant doux pour peaux sèches...",
          "price": "24.90",
          "isAvailable": true,
          "stock": 12,
          "brand": { "id": 2, "name": "CeraVe", "logoUrl": "cerave-logo.svg" },
          "skinConcerns": [
            { "id": 1, "name": "Sécheresse", "slug": "secheresse" }
          ],
          "createdAt": "2026-01-15T10:30:00+00:00",
          "imageUrl": "hydrating-cleanser.webp"
        }
      ]
    }
  ]
}
```

> **Corrigé le 14/09/2026 :** l'exemple précédent montrait un champ `skinConcern` qui **n'existe pas** dans la réponse, omettait `description`, et réduisait chaque produit à trois champs avec un prix numérique. Chaque produit est en réalité **l'objet complet** de `GET /api/products/{id}`, prix en chaîne. Le filtre `?skin_concern=` agit sur les problématiques des produits de la routine, pas sur un champ de la routine elle-même.

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

**Règles** (toute violation → `400`) : `items` est une liste non vide ; `productId` et `quantity` sont des entiers stricts, la quantité entre 1 et 1 000 ; le produit doit exister, être disponible et avoir assez de stock. `street`, `city` et `postalCode` sont obligatoires ; `country` vaut `France` s'il est absent — ou s'il n'est pas une chaîne, ce qui est un laxisme connu. Le total est **recalculé côté serveur** : un prix envoyé par le client est ignoré.

> **Commande identique réutilisée (14/09/2026).** Si le client a déjà, depuis moins de **30 minutes**, une commande `pending` contenant exactement les mêmes produits et quantités (agrégées par produit), la route **renvoie cette commande**, adresse mise à jour, au lieu d'en créer une seconde — et le stock n'est **pas** réservé une seconde fois. Recharger la page, cliquer deux fois ou ouvrir deux onglets ne duplique donc plus la commande. Le code reste `201`. La fenêtre de 30 minutes est volontairement plus courte que le délai d'annulation automatique (60 minutes), pour ne jamais renvoyer une commande sur le point d'être annulée.

**Réponse 201** (forme réelle, relevée le 14/09/2026) :
```json
{
  "data": {
    "id": 42,
    "status": "pending",
    "total": "74.70",
    "user": [],
    "notes": null,
    "street": "12 rue de la Paix",
    "city": "Paris",
    "postalCode": "75001",
    "country": "France",
    "items": [
      { "id": 81, "quantity": 2, "unitPrice": "24.90", "productName": "Hydrating Cleanser" },
      { "id": 82, "quantity": 1, "unitPrice": "34.90", "productName": "Vitamin C Serum" }
    ],
    "reference": "dc529f08-046b-41aa-9cb5-dd058c1210a7",
    "createdAt": "2026-06-10T14:30:00+00:00",
    "updatedAt": "2026-06-10T14:30:00+00:00",
    "paymentStatus": null,
    "paymentMethod": null
  }
}
```

> **Corrigé le 14/09/2026 :** l'exemple précédent montrait des montants **numériques**, un `productId` dans chaque ligne et aucune adresse. En réalité, les montants sont des **chaînes**, l'adresse est **à plat** (pas d'objet `shippingAddress` en réponse, contrairement à la requête), les lignes n'exposent **pas** `productId`, et la réponse porte `reference` (UUID), `paymentStatus` et `paymentMethod`.
>
> **Bizarrerie connue :** `"user": []`. L'utilisateur est inclus dans le groupe de sérialisation sans qu'aucun de ses champs n'en fasse partie : il sort vide. Sans conséquence — aucune donnée n'est exposée — mais à retirer du groupe `order:read`.

---

### GET /api/orders

Historique des commandes de l'utilisateur connecté.

**Accès :** `ROLE_USER`

**Query parameters :** `page` (défaut 1, plancher 1) et `limit` (défaut 20, borné entre 1 et 100).

**Réponse 200 :** chaque élément de `data` a **exactement la forme de la réponse de `POST /api/orders`** (même groupe de sérialisation `order:read`), avec son statut de paiement réel.
```json
{
  "data": [
    {
      "id": 42,
      "status": "delivered",
      "total": "74.70",
      "user": [],
      "notes": null,
      "street": "12 rue de la Paix",
      "city": "Paris",
      "postalCode": "75001",
      "country": "France",
      "items": [
        { "id": 81, "quantity": 2, "unitPrice": "24.90", "productName": "Hydrating Cleanser" }
      ],
      "reference": "dc529f08-046b-41aa-9cb5-dd058c1210a7",
      "createdAt": "2026-06-10T14:30:00+00:00",
      "updatedAt": "2026-06-12T09:00:00+00:00",
      "paymentStatus": "captured",
      "paymentMethod": "card"
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 5 }
}
```

> **Corrigé le 14/09/2026 :** l'exemple précédent montrait un résumé de quatre champs avec un total numérique. La liste renvoie en réalité l'objet commande **complet** — la route utilise le même groupe de sérialisation que la création.

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

Initiation d'un paiement pour une commande — ou reprise du paiement déjà ouvert.

> **Idempotent depuis le 14/09/2026.** Si la commande a déjà un paiement `pending`, la route **renvoie ce même paiement** (même `clientSecret`, même PaymentIntent Stripe) au lieu d'en créer un second. Auparavant, un second appel — un client qui recharge la page après un refus de carte — violait l'unicité de `payment.order_id` et rendait **500** : le client ne pouvait plus payer, et chaque tentative laissait un PaymentIntent orphelin chez Stripe.

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

**Réponse 409** — la commande n'est plus payable (déjà payée, annulée) ou son paiement est déjà finalisé :
```json
{
  "error": { "code": 409, "message": "Cette commande ne peut plus etre payee." }
}
```

> Un refus de carte **ne ferme pas** le paiement : Stripe laisse le PaymentIntent ouvert, et le client réessaie avec le même `clientSecret`. Seule l'annulation de la commande le ferme — voir `POST /api/webhooks/stripe`.

---

## 9. Compte utilisateur

> ⚠️ **La route s'appelle `GET /api/auth/me`, pas `/api/users/me`.** C'est un piège concret : un développeur front qui suit ce document reçoit un 404 sans comprendre pourquoi. `AuthContext` l'utilise pour restaurer la session au montage ([CONTRAT_API.md](CONTRAT_API.md) §1).
>
> `PATCH /api/users/me` n'existe pas non plus sous ce nom — **la route réelle est `PATCH /api/auth/me`**, documentée plus bas, et le profil est bien modifiable par l'API (prénom, nom, mot de passe), avec une limitation à 10 tentatives par 15 minutes.

### GET /api/users/me → en réalité `GET /api/auth/me`

Profil de l'utilisateur connecté.

**Accès :** `ROLE_USER`

**Réponse 200 :**
```json
{
  "data": {
    "user": {
      "id": 12,
      "email": "user@example.com",
      "firstName": "Sophie",
      "lastName": "Martin",
      "role": ["ROLE_USER"]
    }
  }
}
```

> Deux détails que le front doit connaître : le profil est **imbriqué sous `user`** (et non à plat sous
> `data`), et la clé des rôles est `role` — au singulier — alors qu'elle contient un tableau.
> `createdAt` n'est pas renvoyé.

---

### PATCH /api/auth/me — ✅ IMPLÉMENTÉ

Mise à jour du profil (prénom, nom, mot de passe). Rate limited (10 tentatives / 15 min).

**Accès :** `ROLE_USER`

**Corps de la requête :**
```json
{
  "firstName": "Sophie",
  "lastName": "Dupont",
  "currentPassword": "AncienMdp123!",
  "newPassword": "NouveauMdp456!"
}
```

Tous les champs sont optionnels. `currentPassword` est requis uniquement si `newPassword` est fourni.

**Réponse 200 :**
```json
{
  "data": {
    "user": {
      "id": 12,
      "email": "user@example.com",
      "firstName": "Sophie",
      "lastName": "Dupont",
      "role": ["ROLE_USER"]
    }
  }
}
```

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
| `POST` | `/api/products` | Créer un produit | ✅ `ProductController` + `ProductVoter::CREATE` |
| `PUT` | `/api/products/{id}` | Modifier un produit | ✅ `ProductController` + `ProductVoter::EDIT` |
| `DELETE` | `/api/products/{id}` | Supprimer un produit | ✅ `ProductController` + `ProductVoter::DELETE` |
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
