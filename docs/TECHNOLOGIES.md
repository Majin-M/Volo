# Les technologies de VOLO, expliquées

Chaque technologie du projet, avec la seule question qui compte : **pourquoi celle-là et pas une autre ?** Une brique qu'on ne sait pas justifier est une brique qu'on a copiée d'un tutoriel.

Les mentions *(prévu)* signalent ce qui n'est pas en place — voir [roadmap.md](roadmap.md).

---

## 0. Le chemin d'une action

Un client clique « Ajouter au panier », puis « Commander » :

1. **React** capte le clic, met à jour le **CartContext**.
2. `api.js` envoie un `POST /api/orders` avec le cookie de session et l'en-tête CSRF.
3. Le **proxy Vite** (dev) ou **Nginx** (prod) route vers **Symfony**.
4. Le **firewall** vérifie le JWT du cookie ; le **CsrfProtectionSubscriber** vérifie l'en-tête.
5. `OrderController` désérialise, appelle `OrderService`.
6. `OrderService` **recalcule le total** et demande à **Doctrine** de persister.
7. Doctrine écrit dans **MySQL**.
8. La réponse remonte en JSON, React affiche la confirmation.

Chaque section ci-dessous explique une de ces briques.

---

## 1. Le front-end

### React 19

Une SPA pour un site marchand : le panier doit survivre à la navigation sans rechargement, et le catalogue doit se filtrer sans recharger la page.

**L'alternative écartée** : du Twig rendu serveur, que Symfony fournit gratuitement. Le catalogue et le panier auraient été plus simples à écrire. Mais chaque filtre aurait rechargé la page entière, et le panier aurait vécu en session serveur — moins réactif.

**Le coût réel du choix** : deux applications à faire dialoguer, du CORS, une gestion de session à réinventer côté client, et un référencement qui exige un dispositif dédié ([DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §2). C'est un coût qu'un site vitrine ne devrait pas payer.

### Vite

Serveur de développement quasi instantané et rechargement à chaud. Surtout : son **proxy** résout le problème de cookie tiers en trois lignes ([DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §1). C'est devenu une pièce d'architecture, pas juste un outil de build.

### Context API — et pas Redux

`AuthContext` et `CartContext` suffisent. Redux résout un problème que VOLO n'a pas : un état global complexe, partagé par des dizaines de composants, avec des mises à jour concurrentes. Deux contextes et un `useReducer` sur le formulaire de contact couvrent tout.

Ajouter Redux ici serait ajouter du vocabulaire (actions, reducers, middlewares, store) sans résoudre de difficulté existante.

### CSS Modules — et pas Tailwind

Les classes sont locales au composant : impossible qu'un `.button` d'une page en écrase un autre ailleurs. Les couleurs de marque vivent dans des variables CSS (`#5F4C42` brun cacao, `#F8F0E8` ivoire, `#E9D7C3` beige, `#9CB997` vert sage) définies une fois.

Tailwind aurait été plus rapide à écrire, au prix d'un JSX chargé de classes utilitaires. Sur un projet où l'identité visuelle est un différenciateur revendiqué face à The Ordinary, garder le CSS lisible et centralisé a été jugé plus important que la vitesse de frappe.

### react-helmet-async

Titre et description par page. Google exécute le JavaScript : c'est suffisant pour le référencement. Ça ne l'est **pas** pour les aperçus de partage sociaux, qui ne l'exécutent pas — d'où l'idée d'un `SeoController` côté Symfony.

> ⚠️ **`SeoController` n'existe pas** (vérifié le 17/07/2026). Cette section, [CONTRAT_API.md](CONTRAT_API.md) §7 et [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §2 le citaient tous les trois comme acquis. Seul `SitemapController` existe. **Les aperçus de partage sociaux ne fonctionnent donc pas.**

---

## 2. Le back-end

### PHP 8.2 + Symfony 7

**Pourquoi Symfony et pas Express/NestJS** : le projet a besoin d'un back-office. Symfony en fournit un (EasyAdmin) en quelques classes ; l'écrire à la main en Node aurait représenté plusieurs semaines. Le composant Security, les Voters, le rate limiter et la validation sont fournis, testés, et maintenus.

**Ce que ça coûte** : deux langages dans le projet (PHP et JS), donc deux écosystèmes, deux gestionnaires de dépendances, deux façons de tester.

### Doctrine ORM

Traduit les objets PHP en SQL. Le vrai bénéfice n'est pas le confort : **Doctrine paramètre systématiquement ses requêtes**, ce qui rend l'injection SQL structurellement impossible tant qu'on ne concatène pas de DQL à la main.

**Le piège** : Doctrine mappe `decimal` sur une **`string` PHP**, jamais un `float`. D'où `private ?string $price = null;`, qui surprend mais est délibéré ([MODELE_DONNEES.md](MODELE_DONNEES.md) §5).

### MySQL 8 — en réalité **MariaDB 10.4** en développement

> ⚠️ **Trois moteurs pour un seul projet.** Ce document dit « MySQL 8 ». `backend/compose.yaml` épingle `mysql:8.0`. Et XAMPP — sur lequel le développement se fait réellement — livre **MariaDB 10.4.32**. XAMPP a cessé de livrer MySQL il y a des années ; l'argument « MySQL a été retenu parce qu'il est fourni par XAMPP » repose donc sur une prémisse fausse.
>
> **Ce n'est pas un détail de version, ce sont des SGBD différents.** Constaté en corrigeant une migration : `RENAME INDEX` existe depuis MySQL 5.7 et seulement depuis **MariaDB 10.5.2** — sur 10.4 il échoue en erreur de syntaxe 1064. Une migration écrite et testée mentalement « pour MySQL 8 » casse sur le poste de dev.
>
> À trancher : soit le dev bascule sur le `volo-db` de `compose.yaml` (MySQL 8, comme la cible), soit la doc et la production assument MariaDB. Développer sur un moteur et déployer sur un autre est la situation actuelle, et personne ne l'a décidée.

**Pourquoi pas PostgreSQL** : PostgreSQL aurait offert des types plus riches (ENUM natifs, contraintes `CHECK` plus expressives, JSONB). MySQL/MariaDB a été retenu pour sa disponibilité sur la quasi-totalité des hébergements mutualisés français — le projet doit pouvoir être déployé sans VPS.

**La conséquence à connaître** : les énumérations de VOLO sont des `VARCHAR` en base avec la contrainte portée par PHP, pas par MySQL. Une écriture SQL directe peut donc insérer `status = 'bidon'` sans que la base ne bronche. En PostgreSQL, `CREATE TYPE ... AS ENUM` l'aurait empêché. C'est une garantie perdue.

### LexikJWTAuthenticationBundle

JWT signé par paire de clés RSA. Le firewall `api` est **stateless** : aucune session serveur, le jeton porte l'identité.

**Le choix qui compte n'est pas le JWT, c'est où il est stocké.** Le jeton vit dans un cookie `HttpOnly`, pas en `localStorage` — voir [CONTRAT_API.md](CONTRAT_API.md) §1 pour le raisonnement complet.

### EasyAdmin

Back-office CRUD généré depuis les entités Doctrine. Cinq contrôleurs de quelques lignes couvrent produits, marques, commandes, problématiques et utilisateurs.

**Le piège majeur** : EasyAdmin ne fournit **aucun écran de connexion**. Il faut l'écrire (`Admin/SecurityController` + template Twig avec `_csrf_token`). Sans lui, `/admin` renvoie vers une route inexistante et casse en 500.

**Le piège dangereux** : le formulaire utilisateur écrivait le mot de passe **en clair** en base — EasyAdmin ne sait pas qu'un champ doit être haché. Corrigé par une surcharge de `persistEntity()` ([MODELE_DONNEES.md](MODELE_DONNEES.md) §6.8). C'est le rappel qu'un outil qui génère du CRUD génère aussi les failles du CRUD.

### Symfony Mailer — et le piège du routage asynchrone

Notification de l'administrateur à la réception d'un message de contact. En développement, `MAILER_DSN` vise **Mailpit** (`smtp://localhost:1025`, service `mailer` de `compose.yaml`) : les mails sont capturés et consultables sur `localhost:8025`, rien ne part vers de vraies adresses.

**Volontairement pas `null://null`** — c'était la valeur précédente. Ce transport jette tout **sans jamais lever d'erreur** : un envoi cassé y ressemble trait pour trait à un envoi réussi. C'est le mode de panne silencieux qu'on passe cette session à traquer.

> ⚠️ **Le piège, qui a failli coûter la fonctionnalité** : la recette Symfony route les emails vers Messenger par défaut —
>
> ```yaml
> routing:
>     Symfony\Component\Mailer\Messenger\SendEmailMessage: async
> ```
>
> C'est le bon choix **en production** : la requête HTTP n'attend pas le SMTP. Mais cela suppose un worker qui consomme la file (`messenger:consume async`). **Aucun worker ne tourne sur VOLO, et aucun n'est déclaré nulle part** — ni supervisor, ni cron, ni Docker.
>
> Avec ce routage, la notification de contact aurait été écrite dans la table `messenger_messages` et n'en serait **jamais sortie**. Le même trou noir que celui qu'on cherchait à combler, déplacé d'une table — et invisible, puisque tout aurait l'air de fonctionner.
>
> `SendEmailMessage` a donc été **retiré du routage** : les emails partent en synchrone. Le coût est assumé (la requête attend le SMTP). **À rebasculer en `async` le jour où un worker existera** — ce qui est un prérequis de la tâche 4.2, pas un détail.

**Deux décisions de conception dans `ContactService`** :

- **L'expéditeur n'est jamais le visiteur.** `From` porte une adresse du domaine, l'adresse du visiteur va dans `Reply-To`. Usurper le domaine du visiteur ferait échouer SPF/DKIM et classerait la notification en indésirables — donc personne ne la lirait, ce qui est précisément le problème à résoudre. Le `Reply-To` permet quand même de répondre d'un clic.
- **Un échec d'envoi ne fait pas échouer la requête.** Le message est déjà en base à ce stade : rendre une erreur au visiteur lui dirait que son envoi a échoué alors qu'il est enregistré, et il créerait des doublons. L'incident est journalisé en `error` — sans ce log, on retomberait dans la panne silencieuse.

### Symfony RateLimiter

`login_attempts` : 5 tentatives par 15 minutes, fenêtre glissante. `register_attempts` : 5 par heure.

Sans lui, un attaquant teste des milliers de mots de passe par minute. Avec, l'attaque par force brute devient économiquement inintéressante. Un composant natif, deux entrées de YAML.

### VichUploaderBundle

Upload des images produits et logos de marque, avec validation MIME et taille.

> ⚠️ **Trois chemins d'images coexistent dans le projet** : `/images/products`, `/media/products`, `/media/brands` — répartis entre `ProductCrudController`, `BrandCrudController`, `api_specification.md` et le code React. Aucun n'a été retenu officiellement. C'est une incohérence connue et non résolue.

### Stripe

Paiement par carte. VOLO ne voit **jamais** le numéro de carte : Stripe Elements collecte les données dans une iframe hébergée par Stripe, et VOLO ne manipule qu'un `clientSecret`. C'est ce qui met le projet hors du périmètre le plus lourd de la conformité PCI-DSS.

**Le SDK Stripe n'est appelé que depuis une seule classe** (`StripePaymentGateway`), derrière `PaymentGatewayInterface` — voir [DIAGRAMME_CLASSES.md](DIAGRAMME_CLASSES.md) §3.

> ⚠️ **Le webhook n'existe pas.** L'intégration est à moitié faite : VOLO sait demander un paiement, pas apprendre qu'il a réussi ([DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2).

---

## 3. Sécurité

### bcrypt (via `auto` de Symfony)

Hachage lent par conception : ce qui est insupportable pour un attaquant qui teste des milliards de mots de passe reste imperceptible pour une connexion unique. **Argon2id** est aujourd'hui préféré par l'ANSSI ; le `auto` de Symfony le choisira automatiquement si l'extension Sodium est disponible.

### `SecurityHeadersSubscriber`

Pose sur chaque réponse :

| En-tête | Contre quoi |
|---|---|
| `Content-Security-Policy` | Limite les sources de scripts — atténue une XSS réussie |
| `X-Content-Type-Options: nosniff` | Empêche le navigateur de « deviner » qu'un fichier uploadé est du JS |
| `X-Frame-Options: DENY` | Clickjacking |
| `Referrer-Policy` | Fuite d'URL vers les sites tiers |

Quatre en-têtes, une classe. Le meilleur rapport effort/protection du projet.

### `PasswordValidator`

8 caractères minimum, un chiffre, un caractère spécial. La classe est utilisée par `AuthController` **et** par `UserCrudController` — délibérément la même, pour que l'API et le back-office ne puissent pas diverger sur ce qu'est un mot de passe acceptable.

### Ce qui protège de la XSS

Trois couches : React échappe par défaut tout ce qu'il rend (`{variable}` est du texte, jamais du HTML) ; `ContactService` applique `strip_tags` à l'entrée ; la CSP limite les dégâts si les deux premières échouent.

Aucun `dangerouslySetInnerHTML` n'existe dans le code — c'est le seul endroit où React cesse de protéger.

### CAPTCHA — absent

Le rate limiter freine la force brute mais n'empêche pas le spam distribué du formulaire de contact. Un Cloudflare Turnstile serait l'ajout le plus rentable si le spam devenait un problème réel. Non fait, non prioritaire tant que le site n'est pas en ligne.

---

## 4. L'atelier

### Git

Dépôt monorepo à la racine (`backend/` + `frontend/`). Le dépôt initial ne couvrait que le back : les deux moitiés du projet pouvaient diverger sans que rien ne le signale.

**Incident** : une clé API Stripe (mode test) a été committée. Historique réinitialisé, `.gitignore` racine unifié. Le motif retenu est `.env` / `.env.*` / `!.env.example` — plus robuste que le `.env.*.local` par défaut, qui ne couvre **pas** `.env.dev`.

### XAMPP — et pas Docker

`architecture.md` décrit une cible Docker. Le développement se fait sur XAMPP.

**Ce que ça coûte** : l'environnement n'est pas reproductible, et surtout, les configurations Nginx écrites pour la production **n'ont jamais été exécutées une seule fois** — voir [DIAGRAMME_DEPLOIEMENT.md](DIAGRAMME_DEPLOIEMENT.md) §3.

### Ce qui n'existe pas

| Outil | État | Ce que ça coûte |
|---|---|---|
| PHPUnit | ✅ **Présent** — 26 tests / 88 assertions, 4 fichiers | Couverture partielle, ciblée sécurité et paiement : [STRATEGIE_TESTS.md](STRATEGIE_TESTS.md) §1 |
| Vitest | Absent | Aucun test front |
| PHPStan | ✅ **Présent** — `level: max` + baseline | Voir ci-dessous |
| CI/CD | Absent | Rien ne vérifie qu'une branche compile avant fusion |
| OpenAPI | Absent | `api_specification.md` peut mentir sans que rien ne le signale — et [le fait massivement](api_specification.md) |

> ⚠️ **Ce tableau annonçait « Aucun test » et « PHPStan absent ». Les deux sont faux**, et l'étaient déjà quand ces lignes ont été écrites : `backend/phpunit.dist.xml`, `backend/tests/` et `backend/phpstan.neon` sont dans le dépôt.
>
> **PHPStan tourne en `level: max`** — le niveau le plus strict. Mais avec un `phpstan-baseline.neon` de ~550 lignes : les erreurs existantes sont gelées, seules les nouvelles remontent. C'est un usage légitime sur du code existant, à condition de le savoir. Aujourd'hui **11 erreurs échappent au baseline** et ne sont vues par personne, faute de CI qui exécute la commande.
>
> Deux pièges appris à l'usage : PHPStan a besoin du conteneur `dev` compilé (`cache:warmup` avant, sinon il refuse de démarrer), et de `--memory-limit=1G` (128M ne suffisent pas, il s'arrête en cours d'analyse).

PHPStan reste le meilleur rapport effort/trouvailles du projet — mais un analyseur qu'aucune CI n'exécute ne trouve rien. C'est le vrai argument pour la tâche 5.5.

**Ce que les tests ont réellement révélé** (17/07/2026), et qui illustre pourquoi « ça marche quand je clique » ne suffit pas :

- Le rate limiter **fonctionne** — constaté en épuisant son quota par accident. [STRATEGIE_TESTS.md](STRATEGIE_TESTS.md) §1 le classait « jamais testé ».
- `#[TaggedIterator]` (déprécié en Symfony 7.1) rendait la suite **rouge à cache froid et verte à cache chaud** : PHPUnit 13 compte les dépréciations comme des échecs, et une dépréciation ne se déclenche qu'à la compilation du conteneur. Sur une CI — toujours à froid — la suite aurait été rouge en permanence. Remplacé par `#[AutowireIterator]`.
- L'authentification était **cassée dans tous les environnements** : `.env` portait `JWT_SECRET_KEY=fausse_valeur`, alors que cette variable est un **chemin de fichier**, pas un secret. Masquée comme un secret, elle cassait Lexik sans rien protéger.

---

## 5. Synthèse — les trois choix les plus structurants

1. **SPA React plutôt que Twig.** Tout le reste en découle : CORS, cookies, le dispositif SEO côté serveur, deux écosystèmes à maintenir. C'est le choix le plus coûteux du projet — défendable pour l'apprentissage, discutable pour le produit.

2. **JWT en cookie `HttpOnly` plutôt qu'en `localStorage`.** Le bon choix de sécurité, qui impose CSRF, `credentials: 'include'`, le proxy Vite et **HTTPS obligatoire en production**.

3. **`PaymentGatewayInterface` plutôt qu'un `if/elseif`.** Cinq fichiers pour zéro fonctionnalité nouvelle. Le retour n'existe que le jour où PayPal sera implémenté sans rouvrir `PaymentService`.
