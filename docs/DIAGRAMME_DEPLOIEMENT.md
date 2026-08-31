# Diagramme de déploiement

Schématise l'infrastructure décrite en prose dans [architecture.md](architecture.md) §6 et [roadmap.md](roadmap.md) phases 1 et 5.

> ⚠️ **Écart majeur entre la conception et la réalité, à énoncer d'emblée** : `architecture.md` décrivait une cible Docker (`volo-api`, `volo-react`, `volo-db`, `volo-nginx`). Le développement se fait sur **XAMPP** (Apache + MariaDB locaux) avec le serveur de développement Vite à côté.
>
> **Précision du 17/07/2026** : ce document affirmait « ce `docker-compose.yml` n'existe pas ». C'est inexact — **`backend/compose.yaml` existe**, mais il ne contient que deux services sur cinq : `volo-db` (MySQL 8) et `mailer` (Mailpit). Ni `volo-api`, ni `volo-react`, ni `volo-nginx`. Et `volo-db` n'est en pratique **pas utilisé** : le dev tape sur le MariaDB de XAMPP.
>
> D'où une conséquence que personne n'avait relevée : **le projet se développe sur MariaDB 10.4 alors que sa cible Compose est MySQL 8.** Ce sont deux SGBD, pas deux versions — une migration l'a appris en échouant sur `RENAME INDEX`, absent de MariaDB avant 10.5.2. Voir [TECHNOLOGIES.md](TECHNOLOGIES.md) §2.
>
> Il n'y a par ailleurs **aucun environnement de production**. Toutes les configurations Nginx du projet ont été écrites pour un déploiement qui n'a jamais eu lieu.
>
> Ce document distingue donc systématiquement **ce qui tourne** de **ce qui est prévu**.

Mermaid n'a pas de notation UML « déploiement » native (nœuds en cube, artefacts). Les `subgraph` ci-dessous représentent les **machines**, les boîtes les **artefacts déployés**.

---

## 1. Ce qui tourne aujourd'hui — développement local

```mermaid
flowchart TB
    subgraph Poste["Poste développeur (Windows)"]
        direction TB
        Navigateur["Navigateur<br/>localhost:5173"]

        subgraph Vite["Serveur de développement Vite"]
            ViteSrv["vite dev<br/>port 5173"]
            Proxy["Proxy /api → 127.0.0.1:8000"]
        end

        subgraph XAMPP["XAMPP"]
            Apache["Apache<br/>port 8000"]
            Symfony["Symfony 7<br/>public/index.php"]
            MySQL[("MySQL 8<br/>port 3306")]
        end

        Navigateur -->|HTTP| ViteSrv
        ViteSrv --> Proxy
        Proxy -->|HTTP 8000| Apache
        Apache --> Symfony
        Symfony -->|PDO| MySQL
    end

    Stripe[/"API Stripe<br/>(mode test)"/]
    Symfony -->|HTTPS sortant| Stripe
```

**Le proxy Vite est la pièce importante.** Sans lui, le navigateur parle à `localhost:5173` (React) et `localhost:8000` (API) : deux **origines différentes**. Les cookies `volo_token` et `volo_csrf` deviennent alors des cookies tiers, que les navigateurs bloquent de plus en plus agressivement — la connexion échouait silencieusement.

Avec le proxy, tout passe par `localhost:5173`. Une seule origine, cookies traités comme premier partie, et le problème CORS disparaît presque entièrement en développement.

C'est la solution au bug le plus coûteux de la migration vers les cookies `HttpOnly` — et c'est trois lignes dans `vite.config.js` :

```js
server: {
  proxy: { '/api': 'http://127.0.0.1:8000' }
}
```

> **Piège** : `127.0.0.1` et non `localhost`. Sous Windows, `localhost` peut résoudre en IPv6 (`::1`) alors qu'Apache n'écoute qu'en IPv4 — la connexion est alors refusée sans message explicite.

**Ce que ce schéma ne montre pas et qui n'existe pas** : aucune flèche entrante depuis Stripe. Le webhook n'est pas implémenté ([DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2). La flèche vers Stripe est donc à sens unique.

---

## 2. Ce qui est prévu — production

```mermaid
flowchart TB
    Client["Navigateur client"]
    StripeIn[/"Stripe — webhooks"/]

    subgraph VPS["VPS de production"]
        direction TB
        Nginx["Nginx + Certbot<br/>expose 80, 443"]
        Static["Build React statique<br/>frontend/dist/"]
        PHP["PHP-FPM + Symfony<br/>port interne 9000"]
        DB[("MySQL 8<br/>port interne 3306")]

        Nginx -->|fichiers statiques| Static
        Nginx -->|FastCGI| PHP
        PHP -->|socket local| DB
    end

    Backup[("Sauvegardes<br/>mysqldump cron")]

    Client -->|HTTPS 443| Nginx
    StripeIn -->|POST /api/webhooks/stripe| Nginx
    DB -->|cron quotidien| Backup
```

### Routage Nginx — la subtilité

Quatre chemins doivent aller à Symfony **alors même que ce sont des pages HTML**, pas des routes d'API :

| Chemin | Servi par | État |
|---|---|---|
| `/api/*` | Symfony | ✅ |
| `/admin/*` | Symfony | ✅ Twig rendu serveur |
| `/sitemap.xml` | Symfony | ✅ `SitemapController` — généré depuis la base |
| `/`, `/soins`, `/soins/{id}`, `/contact` | **Symfony** | ⬜ **`SeoController` n'existe pas** |
| tout le reste | fichiers statiques | La SPA |

La quatrième ligne est contre-intuitive : ce seraient des pages React passant par Symfony. Raison : les aperçus de partage (WhatsApp, Facebook, LinkedIn) n'exécutent **pas** le JavaScript, donc les balises `<meta og:*>` posées par `react-helmet-async` leur sont invisibles. L'idée est que le serveur lise `frontend/dist/index.html` et y injecte les métadonnées avant de le renvoyer.

> ⚠️ **Ni `SeoController` ni `IndexHtmlInjector` n'existent** dans `backend/src/` — vérifié le 17/07/2026. Ce document les décrivait au présent, comme une pièce d'architecture en place. Seul `SitemapController` existe.
>
> Ce routage Nginx est donc doublement théorique : le dispositif qu'il route n'existe pas, et **Nginx non plus** (§1). Concrètement, **les aperçus de partage sociaux ne fonctionnent pas** aujourd'hui.

Google, lui, exécute le JS — le référencement pur fonctionne sans ce dispositif. Il n'aurait servi qu'aux aperçus sociaux, ce qui explique pourquoi il a été jugé non prioritaire — mais il faut alors le dire au futur, pas au présent.

### HTTPS n'est pas optionnel — c'est bloquant

| Port | Exposition | Justification |
|---|---|---|
| 443/tcp | Public | Seul point d'entrée applicatif |
| 80/tcp | Public | Redirection vers 443 + challenge Certbot uniquement |
| 22/tcp (SSH) | Restreint par IP | Déploiement |
| 3306 (MySQL) | **Jamais exposé** | Socket local uniquement |

> ⚠️ **Sans HTTPS, la production ne fonctionnera pas du tout** — pas « moins bien » : pas du tout.
>
> Les cookies `volo_token` et `volo_csrf` portent le drapeau `Secure`. Un navigateur **n'envoie jamais** un cookie `Secure` sur une connexion HTTP. Un déploiement en HTTP produirait donc une connexion qui « réussit » (200 sur `/api/auth/login`) suivie d'un `GET /api/auth/me` en 401, sans aucun message d'erreur explicite.
>
> C'est la conséquence directe et non négociable du choix de §1 de [CONTRAT_API.md](CONTRAT_API.md). La tâche 5.4 de la roadmap est marquée 🟠 Haute ; elle est en réalité **🔴 bloquante**.

---

## 3. Ce qui manque entre les deux

| Élément | État | Conséquence |
|---|---|---|
| `docker-compose.yml` complet | Partiel (`backend/compose.yaml` : db + mailer) | L'environnement de dev n'est pas reproductible : chaque poste doit installer XAMPP et le configurer à la main. Et le SGBD du dev (MariaDB) n'est pas celui du Compose (MySQL 8) |
| Environnement de staging | Inexistant | Rien n'est jamais testé dans des conditions proches de la production avant d'y arriver |
| CI/CD | Inexistant | Déploiement manuel, donc oubliable et non reproductible |
| Sauvegardes | Inexistantes | Une perte de base = une perte définitive |
| Variables d'environnement de prod | Inexistantes | Les secrets de prod n'ont jamais été définis |

L'absence de Docker est celle qui coûte le plus cher aujourd'hui : elle explique pourquoi les configurations Nginx du projet n'ont **jamais été exécutées une seule fois**. Elles sont écrites, relues, et non testées — ce qui, pour de la configuration d'infrastructure, revient à dire qu'elles sont probablement fausses par endroits.

---

## 4. Ce que ce document ne couvre pas

- **Haute disponibilité** : un VPS unique = un point de panne unique. Acceptable pour un projet de formation, à ne pas présenter comme une architecture de production.
- **CDN / cache d'images** : les images produits sont servies par Nginx depuis le disque. Suffisant à cette échelle.
- **Un incident de sécurité passé, pour mémoire** : une clé API Stripe (mode test) a été committée dans `.env.dev` et `.env.test`. L'historique Git a été réinitialisé et un `.gitignore` racine unifié mis en place (`.env` / `.env.*` / `!.env.example`, plus robuste que `.env.*.local` qui laissait passer `.env.dev`).

  La leçon vaut d'être écrite : le motif `.env.*.local` recommandé par défaut ne couvre **pas** `.env.dev`. C'est exactement le genre de faux sentiment de sécurité qu'un `.gitignore` mal compris produit.
