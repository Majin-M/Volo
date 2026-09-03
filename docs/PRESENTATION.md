# Plan de présentation — Projet VOLO

---

## 1. Introduction (Mini CV)

*Section personnelle — à adapter avec tes informations :*

- Nom, prénom
- Formation actuelle (titre professionnel visé : Concepteur Développeur d'Applications)
- Parcours (reconversion, formation initiale...)
- Compétences techniques principales : PHP/Symfony, JavaScript/React, MySQL, Docker, Git
- Objectif professionnel

---

## 2. Contexte

**VOLO** est une application e-commerce spécialisée dans les **produits skincare** (soins de la peau).

- **Problématique** : le marché du skincare explose mais les sites existants (The Ordinary, Sephora) manquent de personnalisation. Les consommateurs peinent à trouver des produits adaptés à leurs problématiques de peau spécifiques (acné, sécheresse, hyperpigmentation).
- **Solution** : VOLO propose une plateforme e-commerce inclusive qui filtre les produits par **problématique de peau** (`SkinConcern`) et par **marques** (`Brand`), avec des recommandations de **routines** personnalisées selon le niveau (débutant, intermédiaire, avancé).
- **Cible** : consommateurs soucieux de leur peau, principalement en France (interface en français, compatibilité hébergements mutualisés français).
- **Positionnement** : l'identité visuelle (brun cacao `#5F4C42`, ivoire `#F8F0E8`, beige `#E9D7C3`, vert sage `#9CB997`) se veut un différenciateur face aux concurrents comme The Ordinary.

---

## 3. Cadre du projet — Méthode des 5W2H

| Question | Réponse |
|---|---|
| **Who** (Qui ?) | Développeur full-stack unique. Client : projet de formation simulant un besoin réel |
| **What** (Quoi ?) | Application e-commerce skincare complète : catalogue filtrable par marque et problématique de peau, panier, tunnel de commande avec paiement Stripe, back-office d'administration, formulaire de contact avec notification email |
| **Where** (Où ?) | Application web accessible via navigateur. Développement local sur XAMPP (Windows). Cible : VPS avec Nginx + HTTPS (Certbot / Let's Encrypt) |
| **When** (Quand ?) | Développement de janvier à août 2026 (environ 8 mois) — 5 phases : infrastructure, back-end, front-end, fonctionnalités avancées, déploiement |
| **Why** (Pourquoi ?) | Répondre au besoin d'une plateforme skincare inclusive qui guide le consommateur selon sa problématique de peau. Projet validant les compétences full-stack |
| **How** (Comment ?) | Architecture découplée SPA React + API REST Symfony. Méthodologie Agile/Kanban. Paiement sécurisé via Stripe. JWT en cookie HttpOnly |
| **How much** (Combien ?) | Projet individuel. Stack open-source (coût 0). Seul coût de production : VPS + nom de domaine + certificat SSL (Let's Encrypt gratuit) |

---

## 4. Aperçu du cahier des charges

### 4.1 Besoins du client

**Besoins fonctionnels :**

- Catalogue de produits skincare avec filtrage par **marque** et **problématique de peau** (slug)
- Fiche produit détaillée (nom, description, prix, image, marque, problématiques associées)
- Système d'inscription / connexion sécurisé (JWT en cookie HttpOnly)
- Panier persistant (localStorage) avec ajout, modification de quantité, suppression
- Tunnel de commande complet : adresse de livraison, validation, paiement Stripe
- Confirmation de commande par email
- Historique de commandes pour l'utilisateur connecté
- Page de profil utilisateur modifiable
- Formulaire de contact avec notification email à l'administrateur
- Back-office d'administration (EasyAdmin) : gestion des produits, marques, problématiques, commandes, paiements, utilisateurs
- Sitemap XML dynamique pour le référencement
- Pages légales (mentions légales, politique de confidentialité, CGV)

**Besoins non fonctionnels :**

- Sécurité : CSRF (double-submit cookie), rate limiting, headers de sécurité (CSP, X-Frame-Options, HSTS…), hachage bcrypt, protection XSS
- Performance : API stateless, pagination avec plafond (max 50), pas de session serveur côté API
- Responsive : CSS Modules, adaptation mobile
- SEO : react-helmet-async (meta par page), sitemap XML
- Maintenabilité : architecture multicouches, principes SOLID, PHPStan level max

### 4.2 Benchmark

| Critère | The Ordinary | Sephora | CeraVe | **VOLO** |
|---|---|---|---|---|
| Filtrage par problématique | Basique | Avancé | Basique | **Par slug SkinConcern** |
| Routines personnalisées | Non | Non | Non | **Oui (3 niveaux)** |
| Identité visuelle différenciante | Minimaliste | Corporate | Médical | **Chaleureuse, inclusive** |
| Paiement en ligne | Oui | Oui | Via revendeurs | **Stripe** |
| Back-office intégré | — | — | — | **EasyAdmin** |

### 4.3 Persona

- **Nom** : Sophie, 27 ans
- **Profil** : jeune active, soucieuse de sa peau, souffrant d'acné adulte
- **Frustration** : ne sait pas quels produits choisir parmi des centaines de références. Les sites existants ne filtrent pas par problématique de peau
- **Besoin** : trouver rapidement des produits adaptés à son type de peau, dans un environnement de confiance
- **Parcours VOLO** : arrive sur la home → filtre par « acné » → consulte la fiche La Roche-Posay Effaclar → ajoute au panier → crée un compte → passe commande → paie par carte (Stripe)

### 4.4 Arborescence du site

```
VOLO (/)
├── Catalogue (/soins)
│   └── Fiche produit (/soins/:id)
├── Panier (/panier)
├── Connexion (/connexion)
├── Inscription (/inscription)
├── Commande (/commande)
│   └── Confirmation (/confirmation)
├── Mes commandes (/mes-commandes)
├── Mon compte (/mon-compte)
├── Contact (/contact)
├── Mentions légales (/mentions-legales)
├── Politique de confidentialité (/politique-confidentialite)
├── CGV (/cgv)
└── Admin (/admin) — EasyAdmin (Twig, hors SPA)
    ├── Dashboard
    ├── Produits
    ├── Marques
    ├── Problématiques
    ├── Commandes
    ├── Paiements
    └── Clients
```

### 4.5 Planification (Diagramme de Gantt)

| Phase | Période | Durée | Tâches principales |
|---|---|---|---|
| **Phase 1 — Infrastructure** | Semaine 1 | ~1 sem | Initialisation Symfony + React/Vite, configuration XAMPP, proxy Vite, variables d'environnement, CORS |
| **Phase 2 — Back-end** | Semaines 2-4 | ~3 sem | Entités Doctrine + migrations, auth JWT (Lexik), contrôleurs API REST, fixtures, EasyAdmin, Voters, services métier |
| **Phase 3 — Front-end** | Semaines 5-7 | ~3 sem | React Router, pages (catalogue, détail, panier, auth, commande, contact, profil, historique), contextes (Auth, Cart), intégration API, CSS Modules |
| **Phase 4 — Fonctionnalités avancées** | Semaines 8-9 | ~2 sem | Stripe (PaymentGateway, webhook), upload images (VichUploader), emails (Mailer + Mailpit), rate limiting, CSRF, security headers |
| **Phase 5 — Finalisation** | Semaines 10-12 | ~2-3 sem | Tests (PHPUnit 26 tests / Vitest), PHPStan, documentation, corrections, Docker production |

---

## 5. UML

### 5.1 Diagramme de Use Case

**Acteurs :**

- **Visiteur** : consulter le catalogue, filtrer par problématique/marque, voir le détail d'un produit, envoyer un message de contact, s'inscrire
- **Client (ROLE_USER)** : se connecter, se déconnecter, ajouter au panier, passer commande, payer par carte (Stripe), consulter son historique de commandes, modifier son profil
- **Administrateur (ROLE_ADMIN)** : gérer les produits (CRUD), gérer les marques, gérer les problématiques, gérer les commandes (statut), gérer les paiements, gérer les utilisateurs — créé uniquement via `php bin/console app:create-admin` (RG11)
- **Système Stripe** : notifier le paiement réussi/échoué via webhook (`POST /api/webhooks/stripe`)

**Cas d'utilisation par acteur :**

| Acteur | Use Case |
|---|---|
| Visiteur | Consulter le catalogue (filtrer par marque, problématique), Voir fiche produit, Envoyer un message de contact, S'inscrire |
| Client | Se connecter, Ajouter au panier, Modifier le panier, Passer commande, Payer par carte, Consulter historique, Modifier profil, Se déconnecter |
| Admin | CRUD Produits, CRUD Marques, CRUD Problématiques, Gérer commandes, Gérer paiements, Gérer utilisateurs |
| Stripe | Webhook `payment_intent.succeeded` → Payment CAPTURED + Order PAID |
| Stripe | Webhook `payment_intent.payment_failed` → Payment FAILED |

### 5.2 Diagramme de séquence — Parcours d'achat

```
Client           React SPA          Proxy Vite/Nginx       Symfony API          Stripe         BDD
  |                  |                    |                    |                   |              |
  |-- Ajouter au panier -->              |                    |                   |              |
  |  (CartContext, localStorage)         |                    |                   |              |
  |                  |                    |                    |                   |              |
  |-- POST /api/orders ----------------->|                    |                   |              |
  |  (cookie volo_token + X-Csrf-Token)  |                    |                   |              |
  |                  |                    |--> Firewall (JWT)  |                   |              |
  |                  |                    |--> CsrfSubscriber  |                   |              |
  |                  |                    |--> OrderController |                   |              |
  |                  |                    |    --> OrderService |                   |              |
  |                  |                    |    (recalcul total côté serveur, RG4)  |              |
  |                  |                    |    --> persist(Order + OrderItems) --------------->   |
  |                  |                    |<-- 201 {orderId}   |                   |              |
  |                  |                    |                    |                   |              |
  |-- POST /api/payments --------------->|                    |                   |              |
  |                  |                    |--> PaymentController                   |              |
  |                  |                    |    --> PaymentService                  |              |
  |                  |                    |    --> PaymentGatewayResolver          |              |
  |                  |                    |    --> StripePaymentGateway ---------> |              |
  |                  |                    |                    | PaymentIntent     |              |
  |                  |                    |                    | .create(cts EUR)  |              |
  |                  |                    |<-- {clientSecret}  |<-----------------|              |
  |                  |                    |                    |                   |              |
  |-- stripe.confirmCardPayment -------->|                    |                   |              |
  |  (Stripe Elements iframe)           |                    |   confirmation--->|              |
  |                  |                    |                    |                   |              |
  |                  |                    |                    |<-- webhook --------|              |
  |                  |                    |                    | payment_intent.succeeded         |
  |                  |                    |                    | HMAC verification |              |
  |                  |                    |                    | Payment → CAPTURED|              |
  |                  |                    |                    | Order → PAID      |              |
  |                  |                    |                    | Email confirmation|              |
  |                  |                    |                    |                   |              |
  |<-- Page confirmation ----------------|                    |                   |              |
```

---

## 6. Modélisation de la base de données — MCD / MLD / MPD

### 6.1 MCD (Modèle Conceptuel de Données) — Notation Merise

```
UTILISATEUR ────< PASSE >──── COMMANDE
   (0,N)                        (1,1)

COMMANDE ────< DETAILLE >──── LIGNE_COMMANDE
  (1,N)                          (1,1)

LIGNE_COMMANDE ────< CONCERNE >──── PRODUIT
     (1,1)                           (0,N)

COMMANDE ────< REGLE >──── PAIEMENT
  (0,1)                     (1,1)

MARQUE ────< PROPOSE >──── PRODUIT
 (0,N)                      (1,1)

PRODUIT ────< CIBLE >──── PROBLEMATIQUE
 (0,N)                       (0,N)

PRODUIT ────< INCLUT >──── ROUTINE
 (0,N)                      (0,N)

MESSAGE_CONTACT            (entité isolée — archive)
```

**9 entités, 7 associations, 2 tables de jointure** (`product_skin_concern`, `routine_product`).

### 6.2 MLD (Modèle Logique de Données)

```
UTILISATEUR (#id, email UNIQUE, password, roles, firstName, lastName, createdAt, updatedAt)

MARQUE (#id, name, logoUrl, createdAt, updatedAt)

PRODUIT (#id, name, description, price DECIMAL(10,2), stock, isAvailable, imageUrl,
         createdAt, updatedAt, brand_id→MARQUE)

PROBLEMATIQUE (#id, name, slug UNIQUE, description, createdAt, updatedAt)

ROUTINE (#id, name, level, description, createdAt, updatedAt)

PRODUIT_PROBLEMATIQUE (#product_id→PRODUIT, #skin_concern_id→PROBLEMATIQUE)

ROUTINE_PRODUIT (#routine_id→ROUTINE, #product_id→PRODUIT)

COMMANDE (#id, status, total DECIMAL(10,2), street, city, postalCode, country, notes,
          createdAt, updatedAt, user_id→UTILISATEUR)

LIGNE_COMMANDE (#id, quantity, unitPrice DECIMAL(10,2), productName,
                order_id→COMMANDE, product_id→PRODUIT)

PAIEMENT (#id, status, method, clientSecret, stripePaymentIntentId UNIQUE,
          amount DECIMAL(10,2), createdAt, updatedAt, order_id→COMMANDE UNIQUE)

MESSAGE_CONTACT (#id, firstName, email, subject, message, isProcessed,
                 createdAt, updatedAt)
```

### 6.3 MPD — Décisions techniques

| Décision | Justification |
|---|---|
| `DECIMAL(10,2)` et jamais `FLOAT` | `float` est un binaire à virgule flottante : `0.1 + 0.2 ≠ 0.3`. Pour de l'argent, l'erreur s'accumule. `DECIMAL` est exact |
| Table `shop_order` et non `order` | `ORDER` est un mot réservé SQL — évite les backticks dans toute requête manuelle |
| Adresse **copiée** dans la commande | La commande est un document historique. Si le client déménage, la commande garde l'adresse de livraison réelle |
| `OrderItem.unitPrice` + `productName` = **snapshot** | Une promotion ultérieure ne doit pas modifier le montant d'une facture émise |
| `Payment` = source de vérité unique du statut de paiement | Les colonnes `paymentStatus` / `paymentMethod` sur `Order` ont été supprimées (migration 20260717) pour éliminer le doublon |
| `ON DELETE CASCADE` de `shop_order` vers `payment` | Supprimer une commande emporte son paiement, mais jamais l'inverse |
| Enum PHP (pas MySQL) | Les énumérations sont des `VARCHAR` en base, la contrainte est portée par PHP (`OrderStatus`, `PaymentStatus`, etc.) |

### 6.4 Règles de gestion

| # | Règle |
|---|---|
| **RG1** | Un utilisateur possède 0 à N commandes ; une commande appartient à exactement 1 utilisateur |
| **RG2** | Une commande contient au moins 1 ligne de commande |
| **RG3** | Une ligne de commande référence exactement 1 produit et **copie** son nom et son prix à l'instant de l'achat |
| **RG4** | Le `total` d'une commande est **toujours recalculé côté serveur** — un total reçu du client est ignoré |
| **RG5** | Une marque propose 0 à N produits ; un produit appartient à exactement 1 marque |
| **RG6** | Un produit cible 0 à N problématiques ; une problématique concerne 0 à N produits |
| **RG7** | Une routine inclut 0 à N produits ; un produit entre dans 0 à N routines |
| **RG8** | Une commande donne lieu à 0 ou 1 paiement |
| **RG9** | Un `slug` de problématique est unique et sert d'identifiant public dans les URL |
| **RG10** | Le mot de passe est stocké haché, jamais en clair, **quelle que soit la voie d'écriture** (API, EasyAdmin, commande console) |
| **RG11** | Le rôle `ROLE_ADMIN` ne peut être attribué que par la commande console `app:create-admin` — aucun endpoint HTTP |

---

## 7. Charte graphique et typographie

### Palette de couleurs

| Couleur | Code hex | Usage |
|---|---|---|
| **Brun cacao** | `#5F4C42` | Couleur principale, textes, headers |
| **Ivoire** | `#F8F0E8` | Fond principal, arrière-plans |
| **Beige** | `#E9D7C3` | Fonds secondaires, cartes, sections |
| **Vert sage** | `#9CB997` | Accents, boutons CTA, badges disponibilité |

### Approche visuelle

- Palette chaleureuse et naturelle, évoquant le soin et la douceur
- Positionnement **inclusif** — différenciateur face au minimalisme froid de The Ordinary ou au côté corporate de Sephora
- Variables CSS centralisées (pas de framework CSS type Tailwind) — chaque composant gère son style via **CSS Modules** (classes locales, pas de conflit entre composants)

### Choix de CSS Modules vs Tailwind

Sur un projet où l'identité visuelle est un différenciateur revendiqué, garder le CSS lisible et centralisé a été jugé plus important que la vitesse de frappe. Les classes sont locales au composant : impossible qu'un `.button` d'une page en écrase un autre.

---

## 8. Conception technique (Zoning, wireframe et maquette)

### Composants front-end clés

#### Barre de navigation (`NavBar.jsx`)

Composant présent sur toutes les pages, rendu dans `App.jsx` au-dessus du `<Routes>`.

**Éléments affichés :**

- **Logo VOLO** (`/images/Vologo.webp`) — lien vers l'accueil
- **Lien « Catalogue »** → `/soins`
- **Icône panier** (SVG) → `/panier` — avec un **badge compteur** circulaire affiché dynamiquement quand `cartCount > 0` (valeur tirée de `useCart()` / `CartContext`). L'`aria-label` annonce également le nombre d'articles pour l'accessibilité
- **Zone authentification** (conditionnelle via `useAuth()`) :
  - Non connecté : lien « Connexion » → `/connexion`
  - Connecté : prénom de l'utilisateur (lien vers `/mon-compte`) + bouton « Déconnexion » (appelle `POST /api/auth/logout` pour effacer le cookie HttpOnly côté serveur, affiche un toast, redirige vers l'accueil)

**Responsive mobile** : menu hamburger avec tiroir latéral animé (breakpoint 768px). Les trois lignes du burger s'animent en croix via transitions CSS. Le tiroir se ferme automatiquement au changement de route (`useEffect` sur `location.pathname`). Stylé via CSS Modules (`NavBar.module.css`), cohérent avec le reste du projet.

#### Protection des routes côté front-end

Un composant **`PrivateRoute`** centralise la protection des routes côté front-end. La protection est assurée à **deux niveaux** :

**1. Côté serveur (protection réelle)** — `security.yaml` :

| Règle | Effet |
|---|---|
| `^/api/auth/(login\|register)` | `PUBLIC_ACCESS` |
| `^/api/products` (GET) | `PUBLIC_ACCESS` |
| `^/api/brands` (GET), `^/api/skin-concerns` (GET) | `PUBLIC_ACCESS` |
| `^/api/contact` (POST) | `PUBLIC_ACCESS` |
| `^/api/webhooks/stripe` (POST) | `PUBLIC_ACCESS` |
| `^/api/admin/**` | `ROLE_ADMIN` |
| `^/api/products` (POST/PUT/DELETE) | `ROLE_ADMIN` |
| `^/api/**` (catch-all) | `ROLE_USER` |
| `^/admin/**` | `ROLE_ADMIN` |
| `^/user/**` | `ROLE_ADMIN` (filet de sécurité) |

Toute requête non authentifiée vers une route protégée reçoit un **401** du firewall JWT avant même d'atteindre le contrôleur.

**2. Côté client (UX)** — composant `PrivateRoute` :

Un composant wrapper réutilisable (`PrivateRoute.jsx`) protège les routes privées dans `App.jsx` :

```jsx
<Route path="/commande" element={<PrivateRoute><CheckoutPage /></PrivateRoute>} />
<Route path="/confirmation" element={<PrivateRoute><OrderConfirmationPage /></PrivateRoute>} />
<Route path="/mes-commandes" element={<PrivateRoute><OrderHistoryPage /></PrivateRoute>} />
<Route path="/mon-compte" element={<PrivateRoute><AccountPage /></PrivateRoute>} />
```

`PrivateRoute` consulte `useAuth()` : si `isLoading`, affiche un état de chargement ; si `!isAuthenticated`, redirige vers `/connexion` via `<Navigate replace />`. Ce pattern centralise la logique de garde et évite les vérifications ad hoc par page. **La vraie protection reste côté serveur** : même si un utilisateur contourne le front, l'API refuse la requête.

#### Normalisation des données côté front-end

La couche API (`api/api.js`) est un **transport pur** — elle ne transforme pas les données :

- `apiCall(endpoint, options)` : préfixe `/api`, pose `credentials: 'include'`, injecte le header `X-Csrf-Token` sur les méthodes mutantes, parse la réponse JSON brute
- `productApi.js` : `fetchProducts(params)` construit les query params (`?brand=`, `?skin_concern=`, `?page=`, `?limit=`) via `URLSearchParams`, retourne l'enveloppe `{ data, meta }` telle quelle
- `contactApi.js` : `submitContactMessage(payload)` POST vers `/contact`, retourne la réponse brute

**Pas de couche de normalisation explicite.** Les transformations (ex : construction des URLs d'images à partir de `imageUrl` + préfixe `/images/products/`) se font au niveau des composants de rendu (`ProductCard`, `ProductDetailPage`). C'est un choix de simplicité cohérent avec l'absence de Redux et de couche DTO côté front.

#### Espace administrateur — Back-office EasyAdmin

L'administration se fait **entièrement via EasyAdmin (Twig, `/admin`)**, pas dans la SPA React. C'est un choix délibéré : Symfony fournit un back-office CRUD complet en quelques classes, là où l'écrire en React aurait représenté plusieurs semaines.

**Dashboard** (`DashboardController`) : redirige vers la gestion des produits. Menu organisé en deux sections :

| Section | Entrées |
|---|---|
| **Catalogue** | Produits, Marques, Problématiques |
| **Ventes** | Commandes, Paiements, Clients |

Plus un lien « Retour au site » vers la SPA.

**CRUD Controllers** (6 fichiers) :

| Controller | Fonctionnalités |
|---|---|
| `ProductCrudController` | CRUD complet + upload image via VichUploader (`VichImageType`). Champ `imageFile` (non persisté) → `imageUrl` (persisté). Validation : JPEG/PNG/WebP, max 2 Mo |
| `BrandCrudController` | CRUD + upload logo via VichUploader. Validation : JPEG/PNG/WebP, max 1 Mo |
| `SkinConcernCrudController` | CRUD des problématiques de peau |
| `OrderCrudController` | Consultation et gestion des statuts de commande |
| `PaymentCrudController` | Gestion des statuts de paiement — créé après la suppression du doublon `Order.paymentStatus` |
| `UserCrudController` | Gestion des utilisateurs. `persistEntity()` surchargé pour **hacher le mot de passe** avant écriture (correction du bug 6.8) |

**Sécurité du back-office** :
- Firewall `admin` séparé : `form_login` classique avec session PHP (pas JWT)
- `SecurityController` + template Twig dédié avec `_csrf_token` (EasyAdmin ne fournit **aucun écran de connexion** par défaut)
- Login throttling : max 5 tentatives / 15 minutes
- Accès uniquement via `ROLE_ADMIN`, attribuable uniquement par console (`app:create-admin`)

### Stack technique

| Couche | Technologie | Version | Justification |
|---|---|---|---|
| Front-end | React | 19 | SPA pour un panier réactif, filtres sans rechargement. Context API suffit (pas Redux) |
| Build tool | Vite | 8 | HMR instantané + proxy `/api` (pièce d'architecture pour les cookies HttpOnly) |
| Back-end | Symfony | 7.4 | Écosystème sécurité (firewalls, voters, rate limiter), EasyAdmin, Mailer |
| ORM | Doctrine | — | Paramétrage systématique des requêtes → injection SQL structurellement impossible |
| BDD | MySQL 8.0 | — | Unifié dev/prod. Compatibilité hébergements mutualisés français |
| Auth | LexikJWTBundle | — | JWT RSA signé, stocké en cookie HttpOnly (jamais en localStorage) |
| Paiement | Stripe (SDK PHP + React Elements) | — | Le numéro de carte ne transite jamais par VOLO (conformité PCI-DSS légère) |
| Back-office | EasyAdmin 5 | — | CRUD généré depuis les entités Doctrine en quelques classes |
| Email | Symfony Mailer + Mailpit (dev) | — | Emails synchrones — Mailpit capture en dev |
| Upload images | VichUploaderBundle | — | Upload avec validation MIME, SmartUniqueNamer |
| Tests backend | PHPUnit 13 | — | 26 tests, 88 assertions |
| Tests frontend | Vitest + Testing Library | — | Tests des contextes, validators, pages |
| Analyse statique | PHPStan level max | — | 0 erreur (avec baseline pour l'existant) |
| Linting front | ESLint 10 | — | 0 erreur |
| Qualité React | React Doctor 0.9 | — | Score 100/100, 0 issue (bugs, perf, a11y) |
| SEO | react-helmet-async + SitemapController | — | Meta par page + sitemap XML dynamique |

### Architecture de communication (développement)

```
Navigateur → localhost:5173 (Vite)
              ├── /api/*  → proxy → 127.0.0.1:8000 (Apache/Symfony) → MySQL 8
              └── /*      → React SPA
```

Le proxy Vite est une **pièce d'architecture** : il ramène React et l'API à une origine unique, ce qui permet aux cookies HttpOnly de fonctionner (sans proxy = cookies tiers = blocage par les navigateurs).

---

## 9. Méthodologie de gestion de projet — Agile / Kanban

### Approche

Développement itératif en **5 phases** inspiré du Kanban.

### Principes appliqués

- **Le back-end d'abord** : le front-end React ne démarre qu'une fois le contrat API stabilisé → évite de refactoriser les appels API côté React
- **Une branche = une fonctionnalité** : convention git `feature/`, `fix/`, `chore/`, `test/`
- **Commits conventionnels** : `type(scope): description` (ex : `feat(auth): implement JWT login endpoint`)
- **Monorepo** : `backend/` + `frontend/` dans un seul dépôt Git
- **Documentation auto-critique** : chaque document signale ses propres écarts avec le code réel (marqueurs ⬜ prévu / ❌ abandonné)

### Outils

- Git (versioning)
- GitHub (hébergement du code)
- React Doctor (CI frontend — analyse statique, sécurité, performance, accessibilité sur les PRs)

### Phases de développement

| Phase | Contenu |
|---|---|
| 1 — Infrastructure | Skeleton Symfony + React, config proxy Vite, CORS, env |
| 2 — Back-end | Entités, migrations, JWT, API REST, fixtures, EasyAdmin, Voters |
| 3 — Front-end | Router, pages, contextes (Auth, Cart), intégration API, CSS Modules |
| 4 — Avancées | Stripe (gateway + webhook), emails, uploads, rate limiting, CSRF, headers sécu |
| 5 — Finalisation | Tests, PHPStan, corrections, Docker production, documentation |

---

## 10. Démonstration du parcours utilisateur

### Parcours complet à démontrer

1. **Visiteur arrive sur la page d'accueil** (`/`)
2. **Consulte le catalogue** (`/soins`) — filtre par problématique (ex : « acné ») ou par marque
3. **Consulte une fiche produit** (`/soins/:id`) — détails, prix, marque, image
4. **Ajoute au panier** — CartContext met à jour le localStorage (`volo_cart:v1`)
5. **S'inscrit** (`/inscription`) — validation du mot de passe (8 chars, 1 chiffre, 1 spécial), rate limited 5/h
6. **Se connecte** (`/connexion`) — JWT posé dans cookie HttpOnly `volo_token` + cookie CSRF `volo_csrf`, rate limited 5/15min
7. **Consulte le panier** (`/panier`) — modification des quantités, suppression d'articles
8. **Valide la commande** (`/commande`) — saisie adresse de livraison
   - `POST /api/orders` : le total est **recalculé côté serveur** (RG4)
   - Vérification JWT (firewall stateless) + CSRF (double-submit cookie)
   - `OrderService` crée `Order` + `OrderItem` (snapshot prix/nom)
9. **Paie par carte** — Stripe Elements (iframe Stripe, le numéro de carte ne transite jamais par VOLO)
   - `POST /api/payments` → `PaymentGatewayResolver` → `StripePaymentGateway` → retourne `clientSecret`
   - `stripe.confirmCardPayment(clientSecret)` côté React
   - Stripe envoie webhook `payment_intent.succeeded` → `WebhookController` → Payment CAPTURED + Order PAID
   - Email de confirmation envoyé (best-effort)
10. **Page de confirmation** (`/confirmation`) — récapitulatif de la commande
11. **Historique des commandes** (`/mes-commandes`) — liste des commandes passées avec statut

### Points à souligner durant la démo

- Le panier survit à la fermeture du navigateur (localStorage, pas sessionStorage)
- Le JWT n'est jamais visible en JS (cookie HttpOnly) — montrer dans DevTools > Application > Cookies
- Le total est toujours recalculé côté serveur
- Le numéro de carte ne passe jamais par le serveur VOLO (iframe Stripe)

---

## 11. POO et Architecture multicouches et trois tiers

### Architecture trois tiers

| Tiers | Composant | Technologie |
|---|---|---|
| **Présentation** | SPA React (navigateur) | React 19 + Vite + CSS Modules |
| **Logique métier** | API REST Symfony | PHP 8.2 + Symfony 7.4 |
| **Données** | Base de données relationnelle | MySQL 8.0 + Doctrine ORM |

### Architecture multicouches Symfony

```
Request HTTP
     │
     ▼
Controller          ← reçoit, délègue, retourne (jamais de logique métier)
     │
     ▼
Service             ← logique métier, validation, règles de gestion
     │
     ▼
Repository          ← requêtes BDD uniquement
     │
     ▼
Entity              ← données, relations Doctrine
```

**Règle absolue** : un Controller ne contient **jamais** de logique métier. Il appelle un Service, récupère un résultat, et retourne une `JsonResponse`.

### Principes SOLID appliqués

| Principe | Application concrète dans VOLO |
|---|---|
| **SRP** (Single Responsibility) | `PaymentController` ne fait que traduire HTTP ↔ objets. `PaymentService` contient la règle métier (montant, devise). `StripePaymentGateway` est le seul endroit qui connaît le SDK Stripe. Avant : une seule méthode avait 5 raisons de changer |
| **OCP** (Open/Closed) | `PaymentGatewayInterface` + `#[AutoconfigureTag]` + `#[AutowireIterator]` : ajouter Apple Pay = un fichier neuf, zéro ligne modifiée. Le conteneur Symfony détecte automatiquement la nouvelle implémentation |
| **LSP** (Liskov Substitution) | `PaymentGatewayResolver` ne connaît que l'interface. `PayPalPaymentGateway` respecte le contrat en levant une exception (échec conforme), pas en retournant `null` (violation LSP). `User` n'a pas de sous-classes Client/Admin : le rôle est une donnée, pas un type |
| **ISP** (Interface Segregation) | `PaymentGatewayInterface` ne contient que 2 méthodes (`supports`, `createIntent`). Pas de `refund()`, `capture()`, `verifyWebhookSignature()` — ces fonctions auront leur propre interface (ex : `WebhookVerifierInterface`) |
| **DIP** (Dependency Inversion) | `PaymentService` dépend de `PaymentGatewayInterface` (abstraction), jamais de `StripePaymentGateway` (concret). Testable sans réseau, sans clé API. Choix assumé : DIP appliqué là où le changement est probable (paiement), pas partout |

### Patterns utilisés

| Pattern | Implémentation |
|---|---|
| **Strategy** | `PaymentGatewayInterface` + `StripePaymentGateway` / `PayPalPaymentGateway` — choix du prestataire à l'exécution via `PaymentGatewayResolver` |
| **Service Layer** | Toute la logique métier dans `src/Service/` — les contrôleurs délèguent |
| **Repository** | Requêtes BDD centralisées dans `src/Repository/` |
| **Subscriber / Observer** | `CsrfProtectionSubscriber`, `SecurityHeadersSubscriber`, `StatusTransitionSubscriber`, `ExceptionSubscriber`, `AuditSubscriber` — logique transversale sur les événements Symfony |
| **DTO (implicite)** | `PaymentIntentResult` encapsule le retour de la gateway |
| **State Machine** | `OrderStatus` et `PaymentStatus` avec transitions validées par `StatusTransitionSubscriber` (Doctrine `preUpdate`) |
| **Soft Delete** | `deletedAt` nullable sur `Order` et `Payment` + `SoftDeleteFilter` Doctrine SQL Filter — les enregistrements supprimés sont exclus transparemment des requêtes |
| **UUID** | `Order.reference` (UUID v4) comme identifiant public — l'ID auto-incrémenté reste interne, le client ne voit que la référence |
| **Audit Trail** | `AuditLog` + `AuditSubscriber` (Doctrine `postPersist` / `preUpdate`) — trace les changements de statut (Order, Payment) et les modifications sensibles (User.password, User.roles) |
| **Cache-Aside (HTTP)** | `ETag` + `Cache-Control` sur les endpoints produits (`max-age=60` liste, `max-age=300` détail) avec `isNotModified()` pour les 304 |

---

## 12. Sécurité de l'application

### Vue d'ensemble des couches de sécurité

| Menace | Protection | Implémentation |
|---|---|---|
| **Vol de jeton (XSS)** | JWT en cookie `HttpOnly` | `LexikJWTBundle` configuré pour lire le cookie, pas le header `Authorization` |
| **CSRF** | Double-submit cookie | `CsrfProtectionSubscriber` : cookie `volo_csrf` (lisible JS) + header `X-Csrf-Token` |
| **Injection SQL** | Doctrine paramètre les requêtes | ORM — aucune concaténation de DQL brute |
| **XSS** | 3 couches | React échappe par défaut, `strip_tags()` dans `ContactService`, CSP restrictive |
| **Force brute** | Rate limiting (sliding window) | 5 login/15min, 5 register/1h, 5 contact/1h, 10 profil/15min |
| **Clickjacking** | `X-Frame-Options: DENY` | `SecurityHeadersSubscriber` |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` | idem |
| **Mot de passe faible** | `PasswordValidator` | 8 chars, 1 chiffre, 1 spécial — même classe API et EasyAdmin (RG10) |
| **Mot de passe en clair** | Hachage bcrypt (via `auto`) | Corrigé dans EasyAdmin : `persistEntity()` hache avant écriture |
| **Élévation de privilèges** | Console uniquement | RG11 — aucun endpoint HTTP ne modifie les rôles |
| **Données bancaires** | Stripe Elements (iframe) | VOLO ne voit jamais le numéro de carte — conformité PCI-DSS allégée |
| **Webhook falsifié** | Signature HMAC Stripe | `Stripe\Webhook::constructEvent()` |
| **Fuite clientSecret** | Exclusion de sérialisation | `$payment` exclu des groupes `#[Groups]` |

### Deux firewalls disjoints

| Firewall | Périmètre | Mécanisme | Session |
|---|---|---|---|
| `api` | `^/api` | JWT en cookie HttpOnly | **Stateless** (aucune session serveur) |
| `admin` | `^/admin` | `form_login` classique + CSRF token | Session PHP |

### Voters (autorisation fine)

| Voter | Attributs | Logique |
|---|---|---|
| `ProductVoter` | VIEW (public), CREATE/EDIT/DELETE (ROLE_ADMIN) | Contrôle d'accès par rôle |
| `OrderVoter` | VIEW (propriétaire ou admin), CREATE (tout authentifié), EDIT (admin) | Contrôle de propriété |

### Vulnérabilité corrigée — incident CRUD `/user`

Un scaffold `make:crud` oublié exposait `/user` en **accès anonyme** avec un formulaire exposant `roles` et `password` en clair → n'importe qui pouvait se créer un compte `ROLE_ADMIN`. Corrigé par suppression + règle `^/user → ROLE_ADMIN` en filet.

**Leçon** : `access_control` est une liste d'autorisations, pas une politique par défaut. Tout chemin non listé est ouvert.

### Headers de sécurité (`SecurityHeadersSubscriber`)

```
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

---

## 13. Tests unitaires et fonctionnels

### Résultat global

**26 tests, 88 assertions** (PHPUnit 13) — tous verts.

### Tests backend (PHPUnit)

| Fichier | Type | Ce qui est testé |
|---|---|---|
| `AuthControllerTest` | Fonctionnel (WebTestCase) | Inscription réussie, données manquantes, doublon email, cookie JWT HttpOnly, cookie CSRF non HttpOnly, pas de mot de passe dans la réponse |
| `CsrfProtectionTest` | Fonctionnel (WebTestCase) | POST sans header → 403, mauvais token → 403, bon token → passe, GET non bloqué, login/register exemptés, contact public exempt (8 tests) |
| `OrderPaymentTest` | Intégration (KernelTestCase) | Dérivation du statut Payment→Order, contrat d'API préservé (clés JSON), clientSecret non exposé, ON DELETE CASCADE, suppression paiement ≠ suppression commande |
| `ContactNotificationTest` | Fonctionnel (WebTestCase) | Message persisté en BDD, email admin envoyé, From ≠ visiteur, Reply-To = visiteur, échec SMTP ne perd pas le message, données invalides → rien persisté, HTML strippé |

### Tests frontend (Vitest + Testing Library)

| Fichier | Ce qui est testé |
|---|---|
| `LoginPage.test.jsx` | Rendu du formulaire, erreur champ vide, erreur email invalide, login réussi (API + redirect), erreur API affichée, erreur rate limit, bouton disabled pendant chargement |
| `CartContext.test.jsx` | Panier vide, ajout produit, incrémentation quantité, calcul total, suppression, mise à jour quantité, suppression si qty < 1, clear, persistance localStorage, restauration localStorage |
| `validators.test.js` | `validateEmail` (6 cas), `validatePassword` (5 cas), `isRequired` (4 cas) |

### Exemples concrets

**Test 1 — Contrat d'API préservé** (`OrderPaymentTest`) :

```php
// Vérifie que les clés paymentStatus et paymentMethod
// restent dans le JSON même après suppression des colonnes de Order
$this->assertArrayHasKey('paymentStatus', $json);
$this->assertArrayHasKey('paymentMethod', $json);
```

**Test 2 — Cascade correct** (`OrderPaymentTest`) :

```php
// Supprimer une commande supprime son paiement (ON DELETE CASCADE)
$em->remove($order);
$em->flush();
$this->assertNull($paymentRepo->find($paymentId));

// Mais supprimer un paiement ne supprime PAS la commande
$em->remove($payment);
$em->flush();
$this->assertNotNull($orderRepo->find($orderId));
```

**Test fonctionnel — CSRF** (`CsrfProtectionTest`) :

```php
// POST sans en-tête CSRF → 403
$client->request('POST', '/api/orders', [], [], [], '{}');
$this->assertResponseStatusCodeSame(403);

// POST avec le bon token → passe
$client->request('POST', '/api/orders', [], [], [
    'HTTP_X_CSRF_TOKEN' => $csrfToken,
    'HTTP_COOKIE' => 'volo_csrf=' . $csrfToken,
], '{}');
```

### Analyse statique

- **PHPStan** : `level: max` (le plus strict), 0 erreur (avec baseline de ~128 entrées, régénéré le 02/09/2026)
- **ESLint** : 0 erreur
- **React Doctor** : score **100/100** (0 issue). Analyse statique frontend : bugs, sécurité, performance, accessibilité. CI GitHub Actions sur les PRs

---

## 14. Déploiement dans une démarche DevOps & Référencement SEO

### Architecture de déploiement cible (Docker)

```
docker-compose.yml — 5 services :

┌───────────────────────────────────────────────────┐
│                  VPS Production                    │
│                                                    │
│  ┌──────────┐    ┌───────────┐    ┌───────────┐   │
│  │  nginx   │    │  backend  │    │ frontend  │   │
│  │ (alpine) │    │  PHP-FPM  │    │   nginx   │   │
│  │ port 80  ├───>│ port 9000 │    │  (static) │   │
│  │          ├───>│  Symfony  │    │   React   │   │
│  └────┬─────┘    └─────┬─────┘    └───────────┘   │
│       │                │                           │
│  ┌────▼─────┐    ┌─────▼─────┐                    │
│  │  mailer  │    │    db     │                     │
│  │ Mailpit  │    │  MySQL 8  │                     │
│  │1025/8025 │    │ port 3306 │                     │
│  └──────────┘    └───────────┘                     │
└───────────────────────────────────────────────────┘
```

**Dockerfile backend** : PHP 8.2-fpm-alpine, extensions `intl`, `pdo_mysql`, `zip`, `opcache`, `mbstring`. Composer 2. Génération automatique des clés RSA JWT si absentes.

**Dockerfile frontend** : multi-stage. Stage 1 (node:22-alpine) : `npm ci` + `vite build`. Stage 2 (nginx:alpine) : copie du `dist/` dans nginx.

**Nginx** : reverse proxy — routes `/api/*`, `/images/*`, `/sitemap.xml` vers PHP-FPM ; tout le reste vers la SPA React.

### Configuration du serveur Web (Nginx)

Le reverse proxy Nginx est la pièce centrale de l'infrastructure de production. Sa configuration (`docker/nginx/default.conf`) définit deux upstreams et un routage précis :

**Upstreams :**

| Upstream | Cible | Rôle |
|---|---|---|
| `php-fpm` | `backend:9000` | Symfony (PHP-FPM) |
| `frontend` | `frontend:80` | Build React statique (nginx interne) |

**Règles de routage :**

| Chemin | Servi par | Raison |
|---|---|---|
| `/api/*` | PHP-FPM (Symfony) | Endpoints REST |
| `/_wdt/*`, `/_profiler/*` | PHP-FPM | Outils de debug Symfony (dev uniquement) |
| `/sitemap.xml` | PHP-FPM | Généré dynamiquement depuis la BDD |
| `/images/products/*`, `/images/brands/*` | Nginx directement | Fichiers statiques (uploads VichUploader), servis depuis le volume partagé `backend_public` |
| `/*` (tout le reste) | Frontend (React SPA) | Routing client-side — toutes les URLs non matchées renvoient `index.html` |

**Volume partagé `backend_public`** : le backend (PHP-FPM) écrit les fichiers uploadés dans `public/images/`, et nginx les sert directement sans passer par PHP — performance et séparation des responsabilités.

### HTTPS est bloquant (pas optionnel)

Les cookies `volo_token` et `volo_csrf` portent le drapeau `Secure`. Sans HTTPS, le navigateur **ne les envoie jamais** → la connexion échoue silencieusement (200 au login, puis 401 partout, sans message d'erreur explicite). Solution : Certbot / Let's Encrypt.

| Port | Exposition | Justification |
|---|---|---|
| 443/tcp | Public | Seul point d'entrée applicatif |
| 80/tcp | Public | Redirection vers 443 + challenge Certbot uniquement |
| 22/tcp (SSH) | Restreint par IP | Déploiement |
| 3306 (MySQL) | **Jamais exposé** | Socket local uniquement |

### Vérifications après déploiement

Checklist à exécuter après chaque mise en production :

| Vérification | Commande / Action | Résultat attendu |
|---|---|---|
| Conteneurs actifs | `docker compose ps` | 5 services `Up (healthy)` |
| BDD accessible | `docker compose exec backend php bin/console doctrine:schema:validate` | Schéma synchronisé |
| Migrations appliquées | `docker compose exec backend php bin/console doctrine:migrations:status` | Aucune migration en attente |
| API répond | `curl -k https://localhost/api/products` | 200 OK + JSON |
| SPA chargée | Navigateur → `https://domaine.fr/` | Page d'accueil React |
| Cookies HTTPS | DevTools > Application > Cookies | `volo_token` Secure + HttpOnly |
| CSRF fonctionne | POST sans `X-Csrf-Token` | 403 Forbidden |
| Sitemap | `curl https://domaine.fr/sitemap.xml` | XML valide avec les produits |
| Back-office | `https://domaine.fr/admin/login` | Formulaire de connexion |
| Emails | Formulaire de contact → vérifier Mailpit (`localhost:8025`) | Email reçu |
| Webhook Stripe | Stripe Dashboard > Webhooks > Envoyer un événement test | 200 OK |
| Headers de sécurité | `curl -I https://domaine.fr/api/products` | CSP, X-Frame-Options, HSTS présents |

### Maintenance et sauvegarde

**Sauvegardes BDD :**

- Script `scripts/backup-db.sh` : `mysqldump` compressé (gzip), rétention 30 jours avec purge automatique, mode Docker (`volo-db`) ou local XAMPP (`--local`)
- Données persistées dans le volume Docker `db_data`
- Prêt pour cron (`0 3 * * *`) — le cron lui-même reste à activer sur le serveur cible
- En production : export vers un stockage externe (à mettre en place)

**Maintenance applicative :**

| Action | Commande |
|---|---|
| Mise à jour du code | `git pull` + `docker compose build` + `docker compose up -d` |
| Appliquer les migrations | `docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction` |
| Vider le cache Symfony | `docker compose exec backend php bin/console cache:clear` |
| Créer un administrateur | `docker compose exec backend php bin/console app:create-admin email@volo.fr "Password1!"` |
| Consulter les logs | `docker compose logs -f backend` |
| Charger les fixtures (dev) | `docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction` |

**État actuel** : le script de sauvegarde est implémenté et testé ; son activation via cron sur le serveur cible et le monitoring des conteneurs restent à mettre en place — identifiés comme axe d'amélioration.

### CI/CD

**Existant :**

- **React Doctor** (GitHub Actions) : analyse statique frontend sur les PRs (sécurité, performance, accessibilité, bundle size)
- PHPStan et PHPUnit existent mais ne sont pas exécutés automatiquement

**Pipeline CI cible :**

1. `composer install` + `npm ci` (avec cache)
2. Lint : `php-cs-fixer --dry-run`, ESLint
3. Analyse statique : PHPStan level max
4. Tests unitaires PHPUnit (rapides)
5. MySQL éphémère + `doctrine:migrations:migrate` + tests d'intégration
6. `npm run build` (vérifie que le front compile)

### Référencement SEO

| Dispositif | Implémentation |
|---|---|
| **Sitemap XML dynamique** | `SitemapController` (`/sitemap.xml`) : pages statiques + pages produits dynamiques (`/soins/{id}` avec `lastmod`) + pages filtrées par problématique |
| **Meta tags par page** | `react-helmet-async` : `<title>` et `<meta description>` uniques par page |
| **Pages indexables** | Catalogue, produits, contact : meta robots par défaut |
| **Pages non indexables** | Panier, commande, profil, historique : `<meta name="robots" content="noindex, nofollow">` |
| **URLs propres** | Routes en français (`/soins`, `/soins/:id`, `/contact`) — lisibles par les utilisateurs et les moteurs |
| **Priorités sitemap** | Home (1.0 daily), Catalogue (0.9 daily), Produits (0.8 weekly), Problématiques (0.6 weekly), Contact (0.5 monthly), Légales (0.3 yearly) |

**Limite connue** : les aperçus de partage sociaux (WhatsApp, Facebook, LinkedIn) ne fonctionnent pas — ils n'exécutent pas le JavaScript, donc les `<meta og:*>` posées par react-helmet-async leur sont invisibles. Google, lui, exécute le JS — le référencement organique fonctionne.

---

## 15. Documentation du projet

### 15.1 Documentation technique interne (code)

#### Docstrings et conventions de commentaires

Chaque fichier métier commence par un bloc structuré standardisé. L'objectif : comprendre le rôle du fichier **sans lire son code**.

**Entité Doctrine :**

```php
/*
===============================================================================
Entity : Product
===============================================================================
Purpose:
    Represents a skincare product available on the VOLO platform.
Responsibilities:
    - Store product information (name, description, price, stock, availability).
    - Define the relationship with Brand (many-to-one).
    - Define the relationship with SkinConcern (many-to-many).
Main Properties:
    id, name, description, price, stock, isAvailable, brand, skinConcerns, createdAt
Related Entities:
    Brand, SkinConcern, OrderItem, Routine
===============================================================================
*/
```

**Service :**

```php
/*
===============================================================================
Service : OrderService
===============================================================================
Purpose:
    Centralizes business logic related to orders.
Responsibilities:
    - Create orders from cart data
    - Recalculate total server-side (RG4)
    - Validate product existence and availability
Dependencies:
    - ProductRepository, EntityManagerInterface
Used By:
    - OrderController
Throws:
    - InvalidArgumentException if validation fails
===============================================================================
*/
```

**Contrôleur API :**

```php
/*
===============================================================================
Controller : ProductController
===============================================================================
Available Endpoints:
    GET     /api/products          List all products (paginated)
    GET     /api/products/{id}     Get a single product
    POST    /api/products          Create a product (ROLE_ADMIN)
    PUT     /api/products/{id}     Replace a product (ROLE_ADMIN)
    DELETE  /api/products/{id}     Delete a product (ROLE_ADMIN)
Query Parameters (GET):
    ?skin_concern={slug}  ?brand={id}  ?page={n}  ?limit={n}
Security:
    Public: GET  |  Admin: POST, PUT, DELETE (ProductVoter)
Dependencies:
    - ProductService, SerializerInterface
===============================================================================
*/
```

**Composant React (JSDoc) :**

```jsx
/**
 * ProductCard
 * -----------
 * Purpose: Displays a single product in the catalogue grid.
 * Props:
 *   @param {Object}   product         - Product data object
 *   @param {string}   product.name    - Product name
 *   @param {string}   product.price   - Formatted price string
 *   @param {number}   product.stock   - Available stock quantity
 *   @param {boolean}  product.isAvailable
 *   @param {Function} onAddToCart     - Callback on CTA click
 * Used By: ProductListPage
 */
```

#### Conventions de nommage

| Contexte | Convention | Exemple |
|---|---|---|
| Classes PHP | PascalCase | `Product`, `OrderItem` |
| Variables PHP/JS | camelCase | `productName`, `cartTotal` |
| Tables BDD | snake_case singulier | `product`, `order_item` |
| Colonnes BDD | snake_case | `first_name`, `created_at` |
| Routes API | kebab-case pluriel | `/api/products`, `/api/skin-concerns` |
| Composants React | PascalCase | `ProductCard`, `CheckoutForm` |
| Branches Git | kebab-case avec préfixe | `feature/cart-system`, `fix/order-validation` |
| Commits | Conventional Commits | `feat(auth): implement JWT login endpoint` |
| Containers Docker | kebab-case, préfixe `volo-` | `volo-api`, `volo-db` |
| Enums PHP | PascalCase, valeurs snake_case | `OrderStatus::PENDING = 'pending'` |

#### Règles Clean Code / SOLID

- **SRP** : chaque classe a une seule raison de changer (Controller ≠ Service ≠ Repository)
- **OCP** : `PaymentGatewayInterface` — ajouter un moyen de paiement = un fichier, zéro modification
- **DRY** : `PasswordValidator` partagé entre API et EasyAdmin
- **Pas de logique dans les contrôleurs** : délégation systématique aux services
- **Code en anglais, interface en français**
- **Pas d'abréviations** sauf acronymes établis (`id`, `url`, `jwt`, `dto`)
- **Cohérence inter-couches** : une même entité porte le même nom partout (`Product` en PHP, `product` en BDD, `ProductCard` en React, `/api/products` en REST)

### 15.2 Documentation de l'API

**État actuel :** `api_specification.md` est un fichier Markdown maintenu à la main. Il documente chaque endpoint avec des marqueurs ✅ (implémenté) / ⬜ (prévu).

**Contrat d'API transversal :** `CONTRAT_API.md` décrit les conventions qui s'appliquent à **toutes** les routes :
- Stratégie d'authentification (JWT en cookie HttpOnly, pourquoi pas `localStorage`)
- Mécanisme CSRF (double-submit cookie, exemptions, ordre des listeners)
- Séparation des deux firewalls (`api` stateless vs `admin` session)
- Règles d'autorisation (`access_control` + Voters)
- Format des erreurs et pagination

**Limite identifiée :** il n'existe pas de fichier OpenAPI (`openapi.yaml`). La spécification Markdown peut dériver silencieusement du code réel — et elle l'a fait massivement (11 routes documentées sur 20 existaient réellement lors de l'audit du 17/07/2026). Un fichier OpenAPI permettrait des tests de contrat automatisés et une documentation interactive (Swagger UI). C'est un axe d'amélioration prioritaire.

### 15.3 Documentation du projet

12 fichiers de documentation dans `docs/`, organisés par thème :

**Architecture et conception :**

| Document | Contenu |
|---|---|
| `architecture.md` | Architecture globale (révisée, écarts signalés entre conception et réalité) |
| `DIAGRAMME_CLASSES.md` | Diagrammes de classes + principes SOLID appliqués avec exemples concrets |
| `DIAGRAMME_ETATS.md` | Machines à états (Order, Payment) — a révélé 3 défauts réels dans le code |
| `DIAGRAMME_CAS_UTILISATION.md` | Diagramme de cas d'utilisation UML + séquences détaillées (connexion, commande/paiement) |
| `DIAGRAMME_DEPLOIEMENT.md` | Infrastructure dev (XAMPP) vs cible prod (Docker/Nginx) |

**Données et API :**

| Document | Contenu |
|---|---|
| `MODELE_DONNEES.md` | MCD/MLD/MPD + dictionnaire de données + 8 défauts documentés (5 corrigés) |
| `CONTRAT_API.md` | Conventions transversales de l'API (JWT, CSRF, firewalls, autorisation) |
| `api_specification.md` | Spécification des endpoints avec marqueurs d'état ✅/⬜ |

**Qualité et processus :**

| Document | Contenu |
|---|---|
| `TECHNOLOGIES.md` | Choix technologiques justifiés (« pourquoi cette techno et pas une autre ») |
| `STRATEGIE_TESTS.md` | Stratégie de tests, pyramide, couverture, ce qui est non vérifié |
| `convention_de_nommage.md` | Conventions de nommage toutes couches |
| `CORRECTION.md` | Journal des corrections (migration cassée, cascade inversé, doublon) |
| `roadmap.md` | Roadmap 5 phases + backlog v2 + tâches issues des révisions |

**Particularité notable** : la documentation est **auto-critique** — elle signale explicitement ce qui était prévu mais pas implémenté (⬜), ce qui a été abandonné (❌), et les défauts découverts pendant sa rédaction. Trois des cinq défauts les plus graves du projet ont été trouvés **en rédigeant les diagrammes**, pas en lisant le code.

---

## 16. Conclusion — Bilan & Axes d'amélioration

### Bilan

**Ce qui fonctionne :**

- Parcours d'achat complet de bout en bout : catalogue → filtrage → panier → inscription → connexion → commande → paiement Stripe → webhook → confirmation (vérifié le 02/09/2026 avec Stripe CLI en mode test)
- Architecture découplée SPA + API REST avec séparation stricte des responsabilités
- Sécurité multicouche : JWT HttpOnly, CSRF double-submit, rate limiting, headers de sécurité (CSP, HSTS, Permissions-Policy), hachage bcrypt, Voters
- Back-office EasyAdmin fonctionnel (produits, marques, problématiques, commandes, paiements, utilisateurs)
- Webhook Stripe avec vérification HMAC, idempotence et métadonnées UUID
- Gestion de stock avec vérification et décrémentation atomique à la commande
- Transitions de statut contraintes par le composant Workflow Symfony + `StatusTransitionSubscriber`
- Audit trail automatique (`AuditSubscriber`) traçant les changements de statut et les modifications sensibles
- Soft Delete sur `Order` et `Payment` via `SoftDeleteFilter` Doctrine
- `ExceptionSubscriber` unifiant les réponses d'erreur JSON sur `/api/*`
- 26 tests backend + tests frontend (Vitest) couvrant l'authentification, le CSRF, le paiement, le contact, le panier et les validateurs
- PHPStan level max à 0 erreur (baseline ~128 entrées)
- UI/UX soignée : toast notifications animées (gradients, SVG, progress bar), `ConfirmDialog` avec backdrop blur et icônes contextuelles, page de confirmation avec animations cascade et check SVG animé, images de problématiques de peau sur la page d'accueil
- Pages légales conformes au droit français du e-commerce : CGV (17 articles, formulaire de rétractation, règlement cosmétiques CE 1223/2009, médiation, force majeure), mentions légales (LCEN + CGU intégrées), politique de confidentialité (RGPD, transferts internationaux Stripe US, profilage, mineurs, violation de données)
- Documentation exhaustive et auto-critique

**Défauts corrigés en cours de projet :**

- Doublon de statut de paiement `Order` / `Payment` → source unique (Payment fait autorité)
- Cascade inversé `Payment → Order` → corrigé (`ON DELETE CASCADE` de Order vers Payment)
- Mot de passe en clair via EasyAdmin → hachage dans `persistEntity()`
- CRUD `/user` anonyme exposant les rôles → supprimé + filet de sécurité
- Formulaire de contact bloqué en 403 pour les visiteurs → exemption CSRF
- Références `CMD-{id}` dans le front → remplacées par la `reference` UUID de la commande
- SGBD dev/prod désynchronisés (MariaDB 10.4 / MySQL 8) → unifié sur MySQL 8.0
- Métadonnées Stripe sans référence de commande → `order_id` (cast string) + `reference` UUID

### Axes d'amélioration

| Axe | Détail |
|---|---|
| ~~**Contraindre les transitions de statut**~~ | ✅ **Implémenté** — Le composant Workflow de Symfony est configuré (`workflow.yaml`) avec deux state machines (`order` : pending→paid→shipped→delivered, annulable depuis pending/paid/shipped ; `payment` : pending→captured/failed, captured→refunded). `WebhookController` utilise `$workflow->can()` / `$workflow->apply()`. Un `StatusTransitionSubscriber` (Doctrine `preUpdate`) valide chaque changement de statut contre les transitions autorisées — y compris depuis EasyAdmin. Toute transition invalide lève une `LogicException` |
| **Implémenter PayPal** | Le stub `PayPalPaymentGateway` est prêt — l'architecture OCP permet d'ajouter PayPal sans toucher à `PaymentService` |
| **CI/CD complète** | PHPStan et PHPUnit existent mais rien ne les exécute automatiquement. Pipeline GitHub Actions cible prête |
| **Worker Messenger** | Les emails sont synchrones (pas de worker `messenger:consume`). Prérequis pour basculer en asynchrone sans perdre de messages |
| **Fichier OpenAPI** | `api_specification.md` est du Markdown à la main — peut dériver silencieusement. Un `openapi.yaml` permettrait des tests de contrat automatisés et une documentation interactive (Swagger UI) |
| ~~**Notifications et communication**~~ | ✅ **Implémenté** — `WelcomeEmailService` envoie un email de bienvenue à l'inscription (best-effort, même pattern que `OrderConfirmationService`). Axes restants : notifications push, email de réinitialisation de mot de passe, notifications admin |
| ~~**Gestion des images produits**~~ | ✅ **Corrigé** — `BrandCrudController` utilisait `setBasePath('/media/brands')` au lieu de `'/images/brands'` (incohérence avec `vich_uploader.yaml`). Axes restants : optimisation WebP, CDN, placeholder sans image |
| ~~**NavBar responsive**~~ | ✅ **Implémenté** — Menu hamburger avec tiroir latéral animé, CSS Modules (`NavBar.module.css`), breakpoint 768px, fermeture automatique au changement de route, transitions CSS sur les lignes du burger (croix animée) |
| ~~**Gestion de stock**~~ | ✅ **Implémenté** — Colonne `stock` (integer) ajoutée à `Product` (migration `Version20260901120000`). `OrderService` vérifie le stock disponible et le décrémente atomiquement à la création de commande. `Product::decrementStock()` lève une `LogicException` si stock insuffisant. Le front (`ProductDetailPage`) affiche « Rupture de stock », « Plus que N en stock » (≤ 5), et plafonne le sélecteur de quantité. `isAvailable` conservé comme override admin |
| ~~**Aperçus sociaux**~~ | ✅ **Partiellement implémenté** — Balises `og:title`, `og:description`, `og:image`, `og:type` ajoutées via `react-helmet-async` sur HomePage, ProductDetailPage et ProductListPage. Limite : les crawlers sociaux (Facebook, WhatsApp) n'exécutent pas le JS — un `SeoController` côté Symfony serait nécessaire pour un rendu complet |
| ~~**ExceptionSubscriber**~~ | ✅ **Implémenté** — `ExceptionSubscriber` écoute `kernel.exception` et retourne un JSON unifié `{"error": {"code": N, "message": "..."}}` pour toutes les routes `/api/*`. En prod, les messages d'erreur 500 sont masqués ; en dev, le message original est conservé. Journalise les erreurs 500 via `LoggerInterface` |
| ~~**Permissions-Policy**~~ | ✅ **Implémenté** — Header `Permissions-Policy: camera=(), microphone=(), geolocation=()` ajouté dans `SecurityHeadersSubscriber` |
| ~~**Sauvegardes automatisées**~~ | ✅ **Implémenté** — Script `scripts/backup-db.sh` : `mysqldump` compressé (gzip), rétention 30 jours avec purge automatique, mode Docker (`volo-db`) ou local XAMPP (`--local`). Prêt pour cron (`0 3 * * *`). Axe restant : monitoring et alerting conteneurs |
| ~~**Composant PrivateRoute**~~ | ✅ **Implémenté** — `PrivateRoute.jsx` wraps les routes protégées (`/commande`, `/confirmation`, `/mes-commandes`, `/mon-compte`) dans `App.jsx`. Utilise `useAuth()` : redirige vers `/connexion` si non authentifié, affiche un loader pendant la restauration de session. Suppression du guard ad hoc dans `AccountPage` |
| ~~**Unifier le SGBD**~~ | ✅ **Corrigé** — `DATABASE_URL` dans `.env` cible désormais MySQL 8.0 explicitement (`?serverVersion=8.0&charset=utf8mb4`). Les deux `compose.yaml` (racine et backend) utilisent `mysql:8.0`. Doctrine génère les migrations pour MySQL 8 partout, éliminant les incompatibilités MariaDB (ex : `RENAME INDEX`) |
| ~~**React Doctor 100/100**~~ | ✅ **Corrigé** — 6 issues identifiées et résolues : `ConfirmDialog` migré vers `<dialog>` natif (accessibilité + suppression de la gestion manuelle d'Escape/focus), `ToastContext` stabilisé avec `useMemo` (évite les re-renders inutiles des consommateurs), `AccountPage` refactoré avec `useReducer` (9 `useState` liés consolidés), `HomePage` clés stables sans index de tableau, `ProductDetailPage` pattern `AbortController` + `.then()` au lieu de `async/await` dans `useEffect` |
| **Backlog v2** | Programme de fidélité, diagnostic peau (questionnaire → routine personnalisée), blog skincare, avis produits, wishlist, application mobile (React Native sur la même API), multi-langue |
