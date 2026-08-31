# Roadmap — Projet VOLO

> ⚠️ **Ce document ne suit aucun état d'avancement** — les tableaux ci-dessous n'ont qu'une colonne *Priorité*.
>
> Or six documents le citent avec des marqueurs de statut : « la tâche 2.15 de roadmap.md est ⬜ 🟠 », « roadmap 2.5 ⬜ 🟠 », « roadmap 3.13 ⬜ 🔴 ». **Ces ⬜ n'existent pas ici.** Ils ont été inférés par les documents qui les citent, chacun de son côté, sans source commune. C'est ce qui a permis à des tâches faites d'être citées comme à faire (2.15 : des tests existaient), et à des tâches non faites d'être annoncées corrigées ailleurs.
>
> **Ce qui a été vérifié au 17/07/2026**, contre le code :
>
> | # | Tâche | État réel |
> |---|---|---|
> | 1.1 | `docker-compose.yml` complet | 🟠 Partiel — `backend/compose.yaml` n'a que db + mailer |
> | 1.2 | Nginx reverse proxy | ⬜ Jamais exécuté — le proxy Vite tient ce rôle en dev |
> | 2.5 | Voters (`ProductVoter`, `OrderVoter`) | ⬜ `src/Security/` est vide |
> | 2.6 | API REST produits | 🟠 `GET` seulement — pas de `POST`/`PUT`/`DELETE` |
> | 2.9 | API REST routines | ⬜ Aucun `RoutineController` |
> | 2.11 | API REST compte utilisateur | 🟠 `GET /api/auth/me` seul — pas de `PATCH` |
> | 2.15 | Tests unitaires et fonctionnels | 🟠 26 tests / 88 assertions (`AuthController`, `Order`↔`Payment`, CSRF, contact), verts — **pas zéro** |
> | 3.13 | `OrderConfirmationPage` | ⬜ Le parcours s'arrête sur une `alert()` |
> | 4.1 | Stripe + **webhook** | 🟠 Intention de paiement OK, **webhook absent** — aucune commande ne passe à `paid` |
> | 4.2 | Email de confirmation | ⬜ `src/Event/` est vide, aucun email envoyé |
> | 5.5 | CI/CD | ⬜ Absent — PHPStan et PHPUnit existent mais rien ne les exécute |
>
> **Tâches à ajouter**, issues des révisions de juillet 2026 :
>
> - ~~Appliquer `Version20260717120000` sur la base de dev~~ — ✅ **fait le 17/07/2026** (sauvegarde, dry-run, migration, `schema:validate` vert sur `volo` et `volo_test`).
> - **Contraindre les transitions de statut** — composant Workflow de Symfony ([DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §4). 🟠
> - **Trancher `REFUNDED` / `CANCELLED`** — implémenter l'annulation ou retirer les valeurs ([DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §5). 🟡
> - ~~Lire les messages de contact~~ — ✅ **résolu le 17/07/2026** par la notification email (`ContactService`), et non par un écran d'administration. RG12 / `processed_by_user_id` abandonnés en conséquence ([MODELE_DONNEES.md](MODELE_DONNEES.md) §6.5). L'*envoi* était cassé aussi (403 pour tout visiteur anonyme) — corrigé et testé.
> - **Mettre en place un worker Messenger** — `SendEmailMessage` a dû être retiré du routage `async` : aucun worker n'existe, donc un email en file n'en serait jamais sorti. Les emails partent en synchrone en attendant. **Prérequis de 4.2** ([TECHNOLOGIES.md](TECHNOLOGIES.md) §2). 🟠
> - **`openapi.yaml`** — le seul mécanisme qui empêcherait `api_specification.md` de dériver à nouveau ([CONTRAT_API.md](CONTRAT_API.md) §8). 🟠
> - **Unifier le SGBD** — dev sur MariaDB 10.4, cible Compose sur MySQL 8 ([TECHNOLOGIES.md](TECHNOLOGIES.md) §2). 🟠
> - **Trancher les trois chemins d'images** — `/images/products`, `/media/products`, `/media/brands` coexistent ([TECHNOLOGIES.md](TECHNOLOGIES.md) §2). 🟡

## Table des matières

1. [Ordre de développement recommandé](#1-ordre-de-développement-recommandé)
2. [Phase 1 — Infrastructure](#2-phase-1--infrastructure)
3. [Phase 2 — Back-end Symfony](#3-phase-2--back-end-symfony)
4. [Phase 3 — Front-end React](#4-phase-3--front-end-react)
5. [Phase 4 — Fonctionnalités avancées](#5-phase-4--fonctionnalités-avancées)
6. [Phase 5 — Déploiement](#6-phase-5--déploiement)
7. [Backlog](#7-backlog)

---

## 1. Ordre de développement recommandé

```
Phase 1 — Infrastructure
    └── Docker + Nginx + MySQL + Symfony skeleton + React skeleton

Phase 2 — Back-end Symfony
    ├── Entités Doctrine + Migrations
    ├── Authentification JWT
    ├── API REST (API Platform)
    ├── Fixtures
    └── EasyAdmin (back-office)

Phase 3 — Front-end React
    ├── Routing + Layouts
    ├── Pages publiques (catalogue, produit, routines)
    ├── Authentification (login, register, contexte JWT)
    └── Pages utilisateur (panier, commande, compte)

Phase 4 — Fonctionnalités avancées
    ├── Paiement Stripe
    ├── Envoi d'emails (confirmation commande, contact)
    └── Upload d'images produits

Phase 5 — Déploiement
    └── VPS / hébergement cloud + CI/CD
```

Principe : **le front-end React ne démarre qu'une fois le contrat API stabilisé.** Construire le back-end en premier évite de refactoriser les appels API côté React.

---

## 2. Phase 1 — Infrastructure

### Objectif
Avoir un environnement de développement fonctionnel avec tous les services démarrés.

### Tâches

| # | Tâche | Branche Git | Priorité |
|---|---|---|---|
| 1.1 | Initialiser `docker-compose.yml` avec `volo-api`, `volo-react`, `volo-db`, `volo-nginx`, `volo-mailer` | `chore/docker-setup` | 🔴 Critique |
| 1.2 | Configurer Nginx comme reverse proxy (`/api/*` → Symfony, `/*` → React) | `chore/docker-setup` | 🔴 Critique |
| 1.3 | Initialiser le projet Symfony 7 dans `backend/` | `chore/symfony-init` | 🔴 Critique |
| 1.4 | Initialiser le projet React + Vite dans `frontend/` | `chore/react-init` | 🔴 Critique |
| 1.5 | Configurer les variables d'environnement (`.env`, `secrets.toml`) | `chore/env-config` | 🔴 Critique |
| 1.6 | Vérifier la communication `volo-react` → `volo-api` (CORS) | `chore/cors-config` | 🟠 Haute |

### Critère de validation
```
docker compose up --build  →  aucune erreur
GET http://localhost:8000/api  →  200 OK
GET http://localhost:5173  →  page React vide
```

---

## 3. Phase 2 — Back-end Symfony

### Objectif
Exposer une API REST complète, sécurisée, testée, avec back-office.

### Tâches

| # | Tâche | Branche Git | Priorité |
|---|---|---|---|
| 2.1 | Créer les entités Doctrine (`User`, `Product`, `Brand`, `SkinConcern`, `Routine`, `Order`, `OrderItem`, `Payment`, `ContactMessage`) | `feature/entities` | 🔴 Critique |
| 2.2 | Créer les Enums (`OrderStatus`, `PaymentStatus`, `PaymentMethod`, `UserRole`, `RoutineLevel`) | `feature/entities` | 🔴 Critique |
| 2.3 | Générer et exécuter les migrations Doctrine | `feature/entities` | 🔴 Critique |
| 2.4 | Implémenter l'authentification JWT (LexikJWTBundle) | `feature/auth-jwt` | 🔴 Critique |
| 2.5 | Implémenter les Voters Symfony (`ProductVoter`, `OrderVoter`) | `feature/auth-jwt` | 🟠 Haute |
| 2.6 | Exposer l'API REST produits (`ProductController` + `ProductService`) | `feature/product-crud` | 🔴 Critique |
| 2.7 | Exposer l'API REST marques (`BrandController`) | `feature/brand-crud` | 🟠 Haute |
| 2.8 | Exposer l'API REST problématiques (`SkinConcernController`) | `feature/skin-concern-crud` | 🟠 Haute |
| 2.9 | Exposer l'API REST routines (`RoutineController`) | `feature/routine-crud` | 🟡 Moyenne |
| 2.10 | Exposer l'API REST commandes (`OrderController` + `OrderService`) | `feature/order-api` | 🔴 Critique |
| 2.11 | Exposer l'API REST compte utilisateur (`UserController`) | `feature/user-api` | 🟠 Haute |
| 2.12 | Exposer l'endpoint contact (`ContactMessageController`) | `feature/contact-api` | 🟡 Moyenne |
| 2.13 | Créer les DataFixtures (produits, marques, utilisateurs de test) | `feature/fixtures` | 🟠 Haute |
| 2.14 | Configurer EasyAdmin pour le back-office | `feature/easyadmin` | 🟡 Moyenne |
| 2.15 | Écrire les tests unitaires et fonctionnels | `test/api` | 🟠 Haute |

### Critère de validation
```
GET  /api/products         →  200 OK (liste paginée)
POST /api/auth/login       →  200 OK + token JWT
POST /api/orders (+ JWT)   →  201 Created
GET  /api/admin (EasyAdmin) →  back-office accessible
```

---

## 4. Phase 3 — Front-end React

### Objectif
Construire l'interface utilisateur connectée à l'API, responsive et accessible.

### Tâches

| # | Tâche | Branche Git | Priorité |
|---|---|---|---|
| 3.1 | Configurer React Router (routes publiques + routes protégées) | `feature/routing` | 🔴 Critique |
| 3.2 | Créer `MainLayout` et `AdminLayout` | `feature/layouts` | 🔴 Critique |
| 3.3 | Implémenter `AuthContext` (JWT, login, logout, persistance) | `feature/auth-context` | 🔴 Critique |
| 3.4 | Implémenter `CartContext` (ajout, suppression, persistance sessionStorage) | `feature/cart-context` | 🔴 Critique |
| 3.5 | Page catalogue (`CataloguePage` + `ProductGrid` + `ProductCard`) | `feature/catalogue` | 🔴 Critique |
| 3.6 | Page détail produit (`ProductPage`) | `feature/product-detail` | 🔴 Critique |
| 3.7 | Page problématiques (`SkinConcernPage`) | `feature/skin-concerns` | 🟠 Haute |
| 3.8 | Page routines (`RoutinesPage`) | `feature/routines` | 🟡 Moyenne |
| 3.9 | Page panier (`CartPage`) | `feature/cart` | 🔴 Critique |
| 3.10 | Formulaire de connexion (`LoginPage`) | `feature/auth-pages` | 🔴 Critique |
| 3.11 | Formulaire d'inscription (`RegisterPage`) | `feature/auth-pages` | 🔴 Critique |
| 3.12 | Page commande (`CheckoutPage`) | `feature/checkout` | 🔴 Critique |
| 3.13 | Page confirmation de commande (`OrderConfirmationPage`) | `feature/checkout` | 🔴 Critique |
| 3.14 | Page historique des commandes (`OrderHistoryPage`) | `feature/orders` | 🟠 Haute |
| 3.15 | Page compte utilisateur (`AccountPage`) | `feature/account` | 🟠 Haute |
| 3.16 | Page contact | `feature/contact` | 🟡 Moyenne |

### Critère de validation
```
Parcours complet : catalogue → produit → panier → login → commande → confirmation
```

---

## 5. Phase 4 — Fonctionnalités avancées

| # | Tâche | Branche Git | Priorité |
|---|---|---|---|
| 4.1 | Intégration Stripe (Stripe Elements + webhook confirmation paiement) | `feature/payment-stripe` | 🟠 Haute |
| 4.2 | Envoi d'email de confirmation de commande (Symfony Mailer + template) | `feature/email-order` | 🟠 Haute |
| 4.3 | Upload d'images produits (Symfony + stockage local ou S3) | `feature/product-upload` | 🟡 Moyenne |
| 4.4 | Recherche produits (fulltext MySQL ou Elasticsearch) | `feature/search` | 🟡 Moyenne |
| 4.5 | Avis produits (entité `Review`, note, commentaire) | `feature/reviews` | 🔵 Faible |
| 4.6 | Wishlist (entité `WishlistItem`) | `feature/wishlist` | 🔵 Faible |

---

## 6. Phase 5 — Déploiement

| # | Tâche | Branche Git | Priorité |
|---|---|---|---|
| 5.1 | Configurer les variables d'environnement de production | `chore/prod-env` | 🔴 Critique |
| 5.2 | Build React optimisé (`npm run build`) | `chore/prod-build` | 🔴 Critique |
| 5.3 | Configurer Nginx pour servir le build React et proxifier l'API | `chore/nginx-prod` | 🔴 Critique |
| 5.4 | Configurer HTTPS (Certbot / Let's Encrypt) | `chore/https` | 🟠 Haute |
| 5.5 | Mettre en place une pipeline CI/CD (GitHub Actions) | `chore/ci-cd` | 🟡 Moyenne |
| 5.6 | Configurer les sauvegardes BDD | `chore/db-backup` | 🟠 Haute |

---

## 7. Backlog

Fonctionnalités hors scope v1, à envisager pour une v2 :

| Fonctionnalité | Description |
|---|---|
| **Programme de fidélité** | Points accumulés par commande, paliers de récompenses |
| **Diagnostic peau** | Questionnaire → recommandation de routine personnalisée |
| **Blog skincare** | Articles liés aux problématiques et produits |
| **Application mobile** | React Native consommant la même API |
| **Multi-langue** | Interface en anglais pour l'international |
| **Notifications push** | Suivi de commande en temps réel |
