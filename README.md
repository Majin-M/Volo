# Volo

Bienvenue dans le dépôt de l'application **Volo** !
Volo est une application e-commerce composée d'une API **Symfony** (backend) et d'une **single-page application React** (frontend), pensée pour la vente de produits skincare/cosmétiques : catalogue par marque et par problématique de peau, routine de soin personnalisée, panier, commande, paiement en ligne (Stripe / PayPal) et back-office d'administration.

---

## 📖 Contexte

Vendre des produits de soin en ligne suppose d'aider le client à s'y retrouver (marques, types de peau, routines conseillées) tout en assurant un parcours d'achat fiable et sécurisé jusqu'au paiement. Volo répond à ce besoin avec une architecture en deux briques découplées communiquant par API REST/JSON :

- un **backend Symfony** qui expose l'API, gère la persistance (Doctrine/MySQL), l'authentification (JWT), les paiements (Stripe, PayPal) et l'administration (EasyAdmin) ;
- un **frontend React** (Vite) qui consomme cette API pour offrir la boutique et l'espace compte client.

---

## 🎯 Exigences du projet

### Catalogue & découverte produit

#### Objectif
Permettre au visiteur de parcourir le catalogue par marque, par problématique de peau (`SkinConcern`) et de consulter des routines de soin conseillées.

#### Spécifications
- **Produits** (`Product`) : fiche produit, prix, stock, association à une marque et à des problématiques de peau.
- **Marques** (`Brand`) : listing et filtrage par marque.
- **Problématiques de peau** (`SkinConcern`) : filtrage des produits adaptés à un besoin (acné, hydratation, anti-âge…).
- **Routines** (`Routine`) : ensembles de produits recommandés selon un niveau (`RoutineLevel`).
- **Sitemap** : génération dynamique pour le référencement (`SitemapController`).
- **Contact** : formulaire de contact (`ContactMessage`, `ContactService`).

---

### Parcours d'achat & compte utilisateur

#### Objectif
Permettre à un visiteur de créer un compte, constituer un panier, passer commande et payer en ligne.

#### Fonctionnalités
- **Compte utilisateur** : inscription, connexion via JWT (`AuthController`), gestion du profil (`User`, rôles via `UserRole`).
- **Panier** : côté frontend (`CartContext`), synchronisé à la commande.
- **Commande** : création et suivi (`Order`, `OrderItem`, `OrderStatus`), confirmation par email (`OrderConfirmationService`).
- **Paiement** : intégration multi-fournisseur via une interface commune (`PaymentGatewayInterface`, `PaymentGatewayResolver`) avec implémentations **Stripe** et **PayPal**, suivi du statut (`Payment`, `PaymentStatus`, `PaymentMethod`), réception des webhooks (`WebhookController`).
- **Emails transactionnels** : email de bienvenue (`WelcomeEmailService`), confirmation de commande, capture via **Mailpit** en développement.

---

### Sécurité & fiabilité

#### Objectif
Garantir l'intégrité des données et la traçabilité des actions sensibles.

#### Fonctionnalités
- **Autorisations** : voters dédiés (`OrderVoter`, `ProductVoter`) pour contrôler l'accès aux ressources.
- **Journal d'audit** : traçabilité des actions (`AuditLog`, `AuditSubscriber`).
- **Protection CSRF** et **en-têtes de sécurité HTTP** (`CsrfProtectionSubscriber`, `SecurityHeadersSubscriber`).
- **Machine à états** : validation des transitions de statut de commande (`StatusTransitionSubscriber`, `WorkflowValidationListener`).
- **Gestion des exceptions API** centralisée (`ExceptionSubscriber`).
- **Mot de passe** : politique de robustesse (`PasswordValidator`).

---

### Administration (back-office EasyAdmin)

#### Objectif
Offrir un espace de pilotage pour gérer le catalogue, les commandes et les utilisateurs.

#### Fonctionnalités
- **Dashboard** : vue d'ensemble de l'administration (`DashboardController`).
- **Gestion des utilisateurs** : CRUD complet (`UserCrudController`).
- **Gestion du catalogue** : CRUD produits, marques, problématiques de peau (`ProductCrudController`, `BrandCrudController`, `SkinConcernCrudController`).
- **Gestion des commandes & paiements** : suivi et actions (`OrderCrudController`, `PaymentCrudController`).
- **Sécurité de l'admin** : authentification dédiée (`SecurityController`).

---

## 🏗️ Architecture

```
volo/
│
├── backend/                         # API Symfony (PHP 8.2+, Symfony 7.4)
│   ├── src/
│   │   ├── Controller/               # Endpoints API (Auth, Product, Order, Payment, Contact, Webhook, Sitemap…)
│   │   │   └── Admin/                # Back-office EasyAdmin (Dashboard + CRUD)
│   │   ├── Entity/                   # Product, Brand, SkinConcern, Routine, Order, OrderItem,
│   │   │                             #   Payment, User, ContactMessage, AuditLog
│   │   ├── Enum/                     # OrderStatus, PaymentStatus, PaymentMethod, RoutineLevel, UserRole
│   │   ├── Repository/               # Repositories Doctrine
│   │   ├── Service/                  # ProductService, OrderService, PaymentService,
│   │   │   └── PaymentGateway/       #   PasswordValidator, WelcomeEmailService, ContactService
│   │   │                             #   Stripe / PayPal, résolues via PaymentGatewayResolver
│   │   ├── Security/                 # OrderVoter, ProductVoter
│   │   ├── EventSubscriber/          # Audit, CSRF, en-têtes de sécurité, exceptions, transitions de statut
│   │   ├── Doctrine/Filter/          # Filtres Doctrine (ex. soft delete / visibilité)
│   │   ├── DataFixtures/             # Jeux de données de démo (produits, marques, utilisateurs…)
│   │   └── Command/                  # Commandes console (ex. CreateAdminCommand)
│   ├── migrations/                   # Migrations Doctrine
│   ├── config/                       # Configuration Symfony (packages, routes, sécurité)
│   ├── public/                       # Point d'entrée web (front controller PHP-FPM)
│   ├── tests/                        # Tests PHPUnit
│   └── Dockerfile
│
├── frontend/                         # SPA React (Vite)
│   ├── src/
│   │   ├── api/                      # Client API + appels REST par domaine (api.js, productApi.js, contactApi.js)
│   │   ├── components/               # Composants réutilisables (NavBar, ProductCard, PaymentForm, FormField…)
│   │   ├── contexts/                 # AuthContext, CartContext, ToastContext
│   │   ├── pages/                    # Écrans (Home, ProductList/Detail, Cart, Checkout, Account,
│   │   │                             #   OrderHistory/Confirmation, Login/Register, CGV, mentions légales…)
│   │   ├── utils/                    # Fonctions utilitaires (validators…)
│   │   └── test/                     # Configuration des tests (Vitest + Testing Library)
│   └── Dockerfile
│
├── docker/                           # Configuration Nginx (reverse proxy)
├── scripts/                          # Scripts d'exploitation (ex. backup-db.sh)
├── docs/                             # Documentation détaillée (cf. tableau ci-dessous)
└── docker-compose.yml                # Orchestration complète (nginx + backend + frontend + db + mailer)
```

### Documentation

| Document | Contenu |
|---|---|
| [docs/PRESENTATION.md](docs/PRESENTATION.md) | Présentation générale du projet |
| [docs/architecture.md](docs/architecture.md) | Architecture technique |
| [docs/MODELE_DONNEES.md](docs/MODELE_DONNEES.md) | Modèle de données |
| [docs/DIAGRAMME_CLASSES.md](docs/DIAGRAMME_CLASSES.md) | Diagramme de classes |
| [docs/DIAGRAMME_ETATS.md](docs/DIAGRAMME_ETATS.md) | Diagrammes d'états-transitions |
| [docs/DIAGRAMME_CAS_UTILISATION.md](docs/DIAGRAMME_CAS_UTILISATION.md) | Diagramme de cas d'utilisation |
| [docs/DIAGRAMME_DEPLOIEMENT.md](docs/DIAGRAMME_DEPLOIEMENT.md) | Diagramme de déploiement |
| [docs/CONTRAT_API.md](docs/CONTRAT_API.md) + [docs/api_specification.md](docs/api_specification.md) | Contrat et spécification de l'API |
| [docs/TECHNOLOGIES.md](docs/TECHNOLOGIES.md) | Technologies utilisées et justification |
| [docs/STRATEGIE_TESTS.md](docs/STRATEGIE_TESTS.md) | Stratégie de tests |
| [docs/convention_de_nommage.md](docs/convention_de_nommage.md) | Convention de nommage |
| [docs/roadmap.md](docs/roadmap.md) | Feuille de route |
| [docs/CORRECTION.md](docs/CORRECTION.md) | Suivi des corrections |

---

## ⚙️ Installation et utilisation

### Option A — Avec Docker (recommandé)

La pile complète (Nginx + backend Symfony + frontend React + MySQL + Mailpit) est orchestrée par [docker-compose.yml](docker-compose.yml).

```bash
git clone <url-du-repo>
cd Volo

# Variables d'environnement (à adapter : APP_SECRET, clés Stripe, etc.)
cp backend/.env backend/.env.local   # si nécessaire

docker compose up -d --build
```

- Application : http://localhost (proxy Nginx)
- Interface Mailpit (emails capturés en dev) : http://localhost:8025
- Base de données MySQL : `localhost:3306` (`volo_user` / `volo_password`)

Appliquer les migrations et charger les fixtures :
```bash
docker compose exec backend php bin/console doctrine:migrations:migrate
docker compose exec backend php bin/console doctrine:fixtures:load
```

Arrêter la pile :
```bash
docker compose down       # -v pour supprimer aussi les volumes (données)
```

### Option B — En local, sans Docker

#### Backend (Symfony, PHP 8.2+)
```bash
cd backend
composer install
cp .env .env.local            # renseigner DATABASE_URL, APP_SECRET, STRIPE_*, JWT_PASSPHRASE…
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony server:start
# ou
php -S localhost:8000 -t public
```

Variables clés à renseigner (`backend/.env.local`) :
- `APP_SECRET`
- `DATABASE_URL`
- `MAILER_DSN`
- `JWT_PASSPHRASE` (authentification JWT)
- `STRIPE_SECRET_KEY` / `STRIPE_PUBLIC_KEY` / `STRIPE_WEBHOOK_SECRET`
- `CORS_ALLOW_ORIGIN`
- `ADMIN_EMAIL` / `MAILER_FROM`

#### Frontend (React + Vite)
```bash
cd frontend
npm install
npm run dev
```
L'application front tourne alors sur http://localhost:5173 et consomme l'API backend (à démarrer en parallèle).

### Tests

```bash
# Backend (PHPUnit)
cd backend
php bin/phpunit

# Frontend (Vitest + React Testing Library)
cd frontend
npm test          # exécution unique
npm run test:watch
```

### Qualité de code

```bash
cd backend
composer phpstan     # analyse statique (si script défini)
composer rector       # migrations/refactors automatisés (dry-run par défaut)

cd frontend
npm run lint
```

---

## 🛡️ Licence

Ce dépôt est privé et propriétaire. Tous droits réservés — aucune reproduction, modification ou redistribution du code n'est autorisée sans accord préalable des propriétaires du projet.
