# Modèle de données — Approche Merise

Décrit les données de VOLO du dictionnaire jusqu'au schéma physique MySQL. Le modèle a été établi **à partir des entités Doctrine réelles** (`backend/src/Entity/`), pas l'inverse — ce document décrit donc ce qui existe, y compris ses défauts (§6).

---

## 1. Dictionnaire de données

### UTILISATEUR (`user`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `email` | string(180) | NOT NULL, UNIQUE | Identifiant de connexion. UNIQUE **vérifié** (`UNIQ_8D93D649E7927C74`) |
| `password` | string(255) | NOT NULL | Haché bcrypt — **jamais en clair** (cf. §6, défauts 6.8 et 6.9) |
| `roles` | json | NOT NULL | `["ROLE_USER"]` par défaut. Jamais modifiable via HTTP (RG11) |
| `firstName` | string(255) | NOT NULL | |
| `lastName` | string(255) | NOT NULL | |
| `createdAt` / `updatedAt` | datetime | NOT NULL | |

### PRODUIT (`product`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `name` | string(255) | NOT NULL | |
| `description` | text | NULL | |
| `price` | decimal(10,2) | NOT NULL | **Jamais un `float`** — cf. §5 |
| `isAvailable` | bool | NOT NULL, défaut `true` | Interrupteur admin — un produit peut être désactivé même avec du stock |
| `stock` | int | NOT NULL, défaut `0` | Quantité en stock. Décrémenté à chaque commande. Ajouté par migration `Version20260901120000` |
| `imageUrl` | string(255) | NULL | Rempli par VichUploader |
| `createdAt` / `updatedAt` | datetime | NOT NULL / NULL | |

### MARQUE (`brand`)

| Propriété | Type | Contraintes |
|---|---|---|
| `id` | int | PK, auto |
| `name` | string(255) | NOT NULL |
| `logoUrl` | string(255) | NULL |
| `createdAt` / `updatedAt` | datetime | NOT NULL |

### PROBLEMATIQUE (`skin_concern`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `name` | string(255) | NOT NULL | « Acné », « Hyperpigmentation »… |
| `slug` | string(255) | NOT NULL, UNIQUE | Filtre `?skin_concern=acne`. UNIQUE **vérifié** (`UNIQ_DBD33427989D9B62`) |
| `description` | text | NULL | |
| `createdAt` / `updatedAt` | datetime | NOT NULL | |

### ROUTINE (`routine`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `name` | string(255) | NOT NULL | |
| `level` | enum | NOT NULL | `beginner` / `intermediate` / `advanced` |
| `description` | text | NULL | |
| `createdAt` / `updatedAt` | datetime | NOT NULL | ⬜ Aucune API n'expose cette entité — cf. [api_specification.md](api_specification.md) §6 |

### COMMANDE (`order`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `status` | enum | NOT NULL, défaut `pending` | Cycle de vie : [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §1 |
| `total` | decimal(10,2) | NOT NULL | Recalculé serveur, jamais reçu du client |
| `street` / `city` / `postalCode` / `country` | string | NOT NULL | Adresse **copiée**, pas référencée — cf. §5 |
| `reference` | string(36) | NOT NULL, UNIQUE | UUID v4 — identifiant public (l'ID interne n'est jamais exposé au client) |
| `notes` | text | NULL | |
| `deletedAt` | datetime | NULL | Soft Delete — `null` = actif, renseigné = supprimé logiquement |
| `createdAt` / `updatedAt` | datetime | NOT NULL | |

> ❌ **`paymentStatus` et `paymentMethod` ne sont plus des colonnes.** Elles figuraient ici comme « redondantes avec `payment.status` / `payment.method` » ; la migration `Version20260717120000` les a **supprimées de la base** le 17/07/2026 (§6.1).
>
> `Order::getPaymentStatus()` et `getPaymentMethod()` subsistent en **lecture seule**, dérivés de `$this->payment?->getStatus()`. Le JSON de l'API est inchangé — les clés `paymentStatus` et `paymentMethod` sont toujours là, et un test le garantit (`testLeContratDApiEstPreserve`).

### LIGNE_COMMANDE (`order_item`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `quantity` | int | NOT NULL, > 0 | |
| `unitPrice` | decimal(10,2) | NOT NULL | **Copie** du prix au moment de l'achat |
| `productName` | string(255) | NOT NULL | **Copie** du nom au moment de l'achat |

### PAIEMENT (`payment`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `status` | enum | NOT NULL, défaut `pending` | [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2 |
| `method` | enum | NOT NULL | `card` / `paypal` |
| `clientSecret` | string(255) | NULL | Jeton Stripe — ⚠️ cf. §6 |
| `stripePaymentIntentId` | string(255) | NULL, UNIQUE | Identifiant `pi_...` de l'intention Stripe — clé de lookup pour le webhook |
| `amount` | decimal(10,2) | NOT NULL | |
| `deletedAt` | datetime | NULL | Soft Delete — même mécanisme que `Order` |
| `createdAt` / `updatedAt` | datetime | NOT NULL | |

### MESSAGE_CONTACT (`contact_message`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `firstName` | string(255) | NOT NULL | |
| `email` | string(255) | NOT NULL | Va dans le `Reply-To` de la notification |
| `subject` | string(255) | NOT NULL | |
| `message` | text | NOT NULL | Assaini (`strip_tags`) à l'entrée |
| `isProcessed` | bool | NOT NULL, défaut `false` | ⚠️ **Plus lu par personne** — à retirer (§6.5) |
| `createdAt` / `updatedAt` | datetime | NOT NULL | |

> **Table d'archive, pas de travail.** Depuis le 17/07/2026, `ContactService` persiste le message **et** notifie l'administrateur par email. La base garantit qu'un envoi raté ne perd rien ; le traitement se fait dans la boîte mail. C'est ce qui rend RG12 et `processed_by_user_id` inutiles — cf. §6.5.

### JOURNAL_AUDIT (`audit_log`)

| Propriété | Type | Contraintes | Remarque |
|---|---|---|---|
| `id` | int | PK, auto | |
| `entityType` | string(50) | NOT NULL | Nom court de l'entité (`Order`, `Payment`, `User`) |
| `entityId` | int | NOT NULL | ID de l'enregistrement concerné |
| `action` | string(20) | NOT NULL | `create` ou `update` |
| `field` | string(100) | NULL | Champ modifié (absent pour `create`) |
| `oldValue` | string(255) | NULL | Valeur avant modification |
| `newValue` | string(255) | NULL | Valeur après modification |
| `userIdentifier` | string(255) | NULL | Email de l'utilisateur connecté (null si système) |
| `createdAt` | datetime | NOT NULL | Horodatage de l'événement |

> **Audit Trail automatique.** `AuditSubscriber` (Doctrine `postPersist` + `preUpdate`) trace les créations et les modifications des champs sensibles : `Order.status`, `Payment.status`, `User.password` (stocké comme `[hashed]`), `User.roles`. Index composé sur `(entity_type, entity_id)` et index sur `created_at` pour les requêtes d'historique.

---

## 2. Règles de gestion

| # | Règle |
|---|---|
| **RG1** | Un utilisateur possède 0 à N commandes ; une commande appartient à exactement 1 utilisateur. |
| **RG2** | Une commande contient au moins 1 ligne de commande. |
| **RG3** | Une ligne de commande référence exactement 1 produit et **copie** son nom et son prix à l'instant de l'achat. |
| **RG4** | Le `total` d'une commande est **toujours recalculé côté serveur** à partir des lignes. Un total reçu du client est ignoré. |
| **RG5** | Une marque propose 0 à N produits ; un produit appartient à exactement 1 marque. |
| **RG6** | Un produit cible 0 à N problématiques ; une problématique concerne 0 à N produits. |
| **RG7** | Une routine inclut 0 à N produits ; un produit entre dans 0 à N routines. |
| **RG8** | Une commande donne lieu à 0 ou 1 paiement. |
| **RG9** | Un `slug` de problématique est unique et sert d'identifiant public dans les URL. |
| **RG10** | Le mot de passe est stocké haché, jamais en clair, **quelle que soit la voie d'écriture** (API, EasyAdmin, commande console). |
| **RG11** | Le rôle `ROLE_ADMIN` ne peut être attribué que par la commande console `app:create-admin` — aucun endpoint HTTP. |
| ~~**RG12**~~ | ~~Un message de contact est traité par 0 ou 1 utilisateur.~~ ❌ **Règle abandonnée le 17/07/2026** — le traitement se fait par email, pas en base. Cf. §6.5. |

---

## 3. Modèle Conceptuel de Données (MCD)

Notation Merise, cardinalités min/max de part et d'autre de chaque association.

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

MESSAGE_CONTACT            (entité isolée — aucune association)
```

> L'association `TRAITE` entre `UTILISATEUR` et `MESSAGE_CONTACT` a été **retirée le 17/07/2026** avec RG12 : le traitement d'un message se fait par email, pas en base (§6.5). `MESSAGE_CONTACT` n'a plus aucune association — c'est une archive, pas un objet de travail.

Deux associations sont **porteuses de cardinalités N-N** et deviendront des tables de jointure au MLD : `CIBLE` et `INCLUT`.

> ⚠️ **Les fichiers `mcd_volo_v2.mocodo.txt` et `mcd_volo_v2.png`, annoncés ici comme sources de ce MCD, n'existent pas dans le dépôt.** Le schéma ASCII ci-dessus est donc l'unique représentation du MCD, et rien ne l'a jamais engendré.
>
> Deux options : soit ces fichiers ont existé hors dépôt et doivent y être versionnés, soit la mention doit disparaître. En attendant, ne pas chercher un rendu Mocodo qui n'a jamais été committé.

---

## 4. Modèle Logique de Données (MLD)

Les associations 1-N deviennent des clés étrangères, les N-N deviennent des tables.

```
UTILISATEUR (#id, email, password, roles, firstName, lastName, createdAt)

MARQUE (#id, name, logoUrl, createdAt)

PRODUIT (#id, name, description, price, isAvailable, imageUrl,
         createdAt, updatedAt, brand_id→MARQUE)

PROBLEMATIQUE (#id, name, slug, description)

ROUTINE (#id, name, level, description)

PRODUIT_PROBLEMATIQUE (#product_id→PRODUIT, #skin_concern_id→PROBLEMATIQUE)

ROUTINE_PRODUIT (#routine_id→ROUTINE, #product_id→PRODUIT)

COMMANDE (#id, reference, status, total, street, city, postalCode, country, notes,
          deletedAt, createdAt, updatedAt,
          user_id→UTILISATEUR)

LIGNE_COMMANDE (#id, quantity, unitPrice, productName,
                order_id→COMMANDE, product_id→PRODUIT)

PAIEMENT (#id, status, method, clientSecret, amount, deletedAt,
          createdAt, updatedAt, order_id→COMMANDE UNIQUE)

MESSAGE_CONTACT (#id, firstName, email, subject, message, isProcessed,
                 createdAt, updatedAt)

JOURNAL_AUDIT (#id, entityType, entityId, action, field, oldValue,
              newValue, userIdentifier, createdAt)
```

> `processed_by_user_id` figurait ici comme cible traduisant RG12. **La règle est abandonnée** (§6.5) : le traitement se fait par email, la colonne n'a plus d'objet. Le MLD décrit désormais la table telle qu'elle existe.
>
> `isProcessed` subsiste en base mais n'est lu par personne — à retirer lors d'un prochain passage.

Le `UNIQUE` sur `PAIEMENT.order_id` traduit la cardinalité (0,1) de RG8 — sans lui, rien n'empêcherait deux paiements sur une même commande.

---

## 5. Modèle physique — décisions et justifications

### `decimal(10,2)` et jamais `float` pour l'argent

`float` est un binaire à virgule flottante : `0.1 + 0.2` ne vaut pas `0.3`. Sur un total de commande, l'erreur s'accumule ligne après ligne et produit des écarts au centime que personne ne sait expliquer. `DECIMAL` est exact.

Conséquence dans le code : Doctrine mappe `decimal` sur une **`string` PHP**, pas un `float`. D'où `private ?string $price = null;` — qui surprend à la lecture mais est délibéré. Tout calcul doit passer par `bcmath` ou une conversion explicite et contrôlée.

### L'adresse est copiée, pas référencée

`COMMANDE` porte `street`, `city`, `postalCode`, `country` en colonnes directes plutôt qu'une clé vers une table `ADRESSE`.

C'est délibéré : une commande est un **document historique**. Si le client déménage six mois plus tard, sa commande passée doit continuer à afficher l'adresse où le colis a réellement été livré. Une clé étrangère vers une adresse mutable réécrirait le passé.

Même raisonnement pour `LIGNE_COMMANDE.unitPrice` et `productName` : une promotion appliquée aujourd'hui ne doit pas modifier le montant d'une facture émise l'an dernier. La redondance est ici **la fonctionnalité**, pas un défaut de normalisation.

### La table s'appelle `shop_order`, pas `order`

`ORDER` est un mot réservé SQL : une table nommée `order` doit être échappée en backticks dans **toute** requête brute (`SELECT * FROM \`order\``). Doctrine le ferait automatiquement, mais chaque requête manuelle en phpMyAdmin échouerait sans les backticks.

Le piège a été évité à la conception : `#[ORM\Table(name: 'shop_order')]`. L'entité PHP s'appelle `Order` (le métier), la table `shop_order` (la contrainte technique). C'est le bon arbitrage — renommer l'entité aurait pollué le code applicatif pour un problème qui n'appartient qu'à la base.

Conséquence à connaître : toute requête SQL écrite à la main doit viser `shop_order`. `SELECT * FROM \`order\`` renvoie « table inexistante », pas une erreur de syntaxe — ce qui égare.

### Index — ✅ vérifiés le 17/07/2026

Cette section affirmait que deux index UNIQUE « manquent » et qu'ils étaient « supposés par le code mais **non vérifiés en base** ». Vérification faite sur `information_schema.STATISTICS` : **les trois existent.**

| Table | Colonne | Index | Ce qu'il garantit |
|---|---|---|---|
| `user` | `email` | `UNIQ_8D93D649E7927C74` | Unicité de l'identifiant de connexion |
| `skin_concern` | `slug` | `UNIQ_DBD33427989D9B62` | RG9 — identifiant public dans les URL |
| `payment` | `order_id` | `UNIQ_6D28840D8D9F6D38` | RG8 — cardinalité (0,1) : un seul paiement par commande |
| `shop_order` | `reference` | `UNIQ_323FC9CAAEA34913` | Unicité de la référence UUID publique |
| `audit_log` | `(entity_type, entity_id)` | `idx_audit_entity` | Recherche rapide de l'historique d'une entité |
| `audit_log` | `created_at` | `idx_audit_date` | Requêtes chronologiques sur le journal |

Doctrine les avait créés depuis les attributs `#[ORM\UniqueConstraint]` / `unique: true` des entités : ils n'étaient pas « à créer », seulement jamais regardés. Le troisième a été renommé par la migration `Version20260717120000`, en même temps que sa colonne.

> **Ce que cette section illustre**, et qui vaut au-delà du cas : elle décrivait un manque qui n'existait pas, en s'appuyant sur un raisonnement (« Doctrine n'indexe que les PK et FK ») plutôt que sur une lecture. Une minute de `information_schema` répondait. C'est le même travers que §6.6, et il produit ici l'erreur inverse : croire qu'il manque quelque chose qui est là.

---

## 6. ⚠️ Défauts connus du modèle

Cette section est le récapitulatif des incohérences réelles. Elle existe parce que les documenter vaut mieux que les découvrir en production.

### 6.1 Statut de paiement dupliqué — ✅ CORRIGÉ, APPLIQUÉ ET TESTÉ

> **Historique du statut de cette section, qui vaut d'être conservé** :
>
> Elle a d'abord annoncé « ✅ CORRIGÉ ». C'était vrai du code des entités et **faux de la base** : la migration `Version20260717120000` était cassée et n'avait jamais pu s'exécuter — sur aucune base. Pire, code et schéma étant désynchronisés, **l'application était cassée** contre la base de dev. Un « ✅ » a donc masqué une panne pendant des semaines.
>
> **Réparée, appliquée et vérifiée le 17/07/2026** : migration passée sur `volo` après sauvegarde, `doctrine:schema:validate` vert sur les deux bases, et neuf tests dans `tests/Entity/OrderPaymentTest.php` couvrant la dérivation, le contrat d'API et le sens du cascade.
>
> **Ce que la reprise de données a révélé** : les 11 commandes avaient `payment_status` et `payment_method` à **NULL**, alors que 3 `Payment` existaient. Le doublon n'était pas « déjà faux » — il était mort-né : personne n'avait jamais renseigné ces colonnes, ni l'API (qui n'écrivait que sur `Payment`), ni EasyAdmin. Zéro donnée à reprendre, zéro perte.
>
> La leçon tient en une ligne : **un statut « ✅ corrigé » qui ne renvoie à aucune vérification reproductible ne vaut rien.** Voir [CORRECTION.md](CORRECTION.md).

`COMMANDE.paymentStatus` / `paymentMethod` et `PAIEMENT.status` / `method` portaient la même information sans mécanisme de cohérence.

**Ce qui a tranché** : `PaymentService` n'écrivait **que** sur `Payment`. Les colonnes de `Order` n'étaient alimentées que par EasyAdmin, à la main. Elles étaient donc **déjà fausses** dès qu'un paiement passait par l'API — le doublon n'était pas un risque théorique, il était déjà réalisé.

**Correction appliquée** (option A du tableau ci-dessous : `Payment` fait autorité) :

- Les deux propriétés ont été supprimées de `Order`.
- `Order` porte désormais `$payment` (côté inverse du `OneToOne`).
- `Order::getPaymentStatus()` et `getPaymentMethod()` sont conservés en **lecture seule**, dérivés via `$this->payment?->getStatus()`.
- Ils gardent leur attribut `#[Groups('order:read')]` : **le JSON de l'API est inchangé**, aucune adaptation du front n'a été nécessaire.
- `$payment` n'est volontairement **pas** exposé au groupe de sérialisation — cela créerait une référence circulaire et ferait fuiter `clientSecret` dans les réponses.

**Effets de bord traités** :

| Impact | Traitement |
|---|---|
| `OrderCrudController` : les `ChoiceField` de paiement n'ont plus de setter | Retirés du formulaire, conservés en lecture seule sur index/détail |
| `setSearchFields(['…', 'paymentStatus'])` visait une colonne disparue | Devient `'payment.status'` (traversée d'association, comme `user.email`) |
| L'admin ne pouvait plus marquer une commande payée à la main | Nouveau `PaymentCrudController` — au bon endroit, là où la donnée vit |
| Données existantes | La migration crée les `Payment` manquants avant de supprimer les colonnes |

Migration : `Version20260717120000`. Analyse d'origine : [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §3.

### 6.2 `cascade: ['persist', 'remove']` du mauvais côté — ✅ CORRIGÉ

```php
// Payment.php
#[ORM\OneToOne(targetEntity: Order::class, cascade: ['persist', 'remove'])]
private ?Order $orderEntity = null;
```

Le cascade est déclaré sur `Payment` vers `Order`. Cela signifie : **supprimer un paiement supprime la commande.** C'est l'inverse de ce qu'on veut — un paiement est un détail d'une commande, pas son propriétaire.

Concrètement, un administrateur qui supprimerait une ligne de paiement dans EasyAdmin ferait disparaître la commande, ses lignes (elles-mêmes en cascade depuis `Order.items`), et l'historique d'achat du client. Sans confirmation particulière.

**Correction appliquée** : le cascade a été entièrement retiré de cette relation.

Il n'a **pas** été déplacé sur `Order` vers `Payment` : un enregistrement financier ne doit de toute façon jamais être supprimé par cascade ORM (cf. [DIAGRAMME_ETATS.md](DIAGRAMME_ETATS.md) §2 — un échec crée un nouveau `Payment`, il n'écrase pas le précédent).

À la place, la clé étrangère porte désormais `onDelete: 'CASCADE'` **au niveau base**. La distinction compte :

- L'ancien cascade ORM allait de `Payment` vers `Order` — supprimer le détail supprimait le tout.
- Le nouveau va de `shop_order` vers `payment` — supprimer une commande emporte son paiement, ce qui est le sens correct de dépendance.

Sans lui, supprimer une commande ayant un paiement lèverait une violation de contrainte (`payment.order_id` est `NOT NULL`) — une erreur 500 opaque dans EasyAdmin plutôt qu'un comportement défini.

### 6.3 `clientSecret` persisté en base — 🟠 à réexaminer

`PAIEMENT.clientSecret` stocke le jeton retourné par Stripe. Ce jeton permet de confirmer un paiement côté client. Le persister durablement en base est discutable : il est censé être éphémère et transiter vers le navigateur, pas être archivé.

Il ne donne pas accès au compte Stripe, la gravité est donc limitée — mais c'est une donnée sensible conservée sans raison identifiée. À moins qu'un besoin de reprise de paiement soit avéré, la colonne devrait disparaître.

### 6.4 Gestion de stock — ✅ IMPLÉMENTÉ

Colonne `stock` (integer, NOT NULL, défaut 0) ajoutée à `Product` par la migration `Version20260901120000`. `OrderService` vérifie le stock disponible et le décrémente atomiquement à la création de commande. `Product::decrementStock()` lève une `LogicException` si le stock est insuffisant.

Le front (`ProductDetailPage`) affiche « Rupture de stock » (bouton désactivé), « Plus que N en stock » (≤ 5, alerte visuelle), et plafonne le sélecteur de quantité au stock disponible. `isAvailable` est conservé comme interrupteur admin : un produit peut être désactivé même avec du stock restant.

### 6.5 `MESSAGE_CONTACT.processed_by_user_id` absent — ✅ CLOS, RG12 abandonnée

RG12 et le MCD prévoient la relation `TRAITE`. L'entité Doctrine ne l'a jamais reçue.

> **Décision du 17/07/2026 : RG12 est abandonnée, et cette divergence se referme en retirant la règle plutôt qu'en implémentant la colonne.**
>
> Le formulaire de contact **notifie désormais l'administrateur par email** ([CONTRAT_API.md](CONTRAT_API.md) §2). L'administrateur traite donc dans sa boîte mail, où il dispose déjà d'un état « lu / non lu », d'archives et de réponses. `processed_by_user_id` dupliquerait cet état dans une seconde source de vérité, qu'il faudrait tenir à jour à la main — le même travers que le doublon de §6.1.
>
> **`ContactMessage` devient une archive, pas un outil de travail.** Sa raison d'être est de garantir qu'aucun message ne se perd si un envoi échoue. Ce n'est pas un poste de travail, et il n'a besoin ni d'écran EasyAdmin, ni de suivi de traitement.
>
> `isProcessed` reste sur l'entité mais n'est plus lu par personne : à retirer lors d'un prochain passage.
>
> **Le MCD doit être corrigé** : l'association `TRAITE` entre `UTILISATEUR` et `MESSAGE_CONTACT` (§3) et RG12 (§2) sont à supprimer. Elles décrivent un flux de travail qui n'aura pas lieu.

### 6.6 Convention de nommage des colonnes — ✅ VÉRIFIÉ, conforme

`convention_de_nommage.md` impose le `snake_case` en base. Les entités déclarent des propriétés en `camelCase` (`isAvailable`, `postalCode`, `createdAt`) **sans `name:` explicite** dans `#[ORM\Column]`.

**Question tranchée le 17/07/2026** : la stratégie configurée est bien `underscore`. Les colonnes sont en `snake_case` (`image_url`, `postal_code`, `created_at`, `payment_status`) et la convention est **respectée**.

Vérifié de deux façons : par la migration, qui affiche à l'exécution le nom réellement trouvé (`Colonnes détectées : payment_status / payment_method`), et par `SHOW CREATE TABLE`. Le doute était donc infondé — mais il ne pouvait pas être levé sans regarder.

> **Le vrai enseignement est ailleurs.** Ce défaut concernait des colonnes dont le nom était *dérivé automatiquement*, et le nommage automatique était correct. Pendant ce temps, une colonne dérivée de la même façon — `payment.order_entity_id`, tirée de la propriété `$orderEntity` — a été **supposée** s'appeler `order_id` par la migration censée corriger tout ça. C'est elle qui a cassé.
>
> La leçon n'est pas « vérifier la stratégie de nommage ». C'est : **une colonne dont le nom est dérivé par l'ORM ne doit jamais être écrite en dur sans avoir été lue.**

### 6.8 Mot de passe en clair via EasyAdmin — ✅ CORRIGÉ, pour mémoire

`UserCrudController` (EasyAdmin) **écrivait les mots de passe en clair** en base : le formulaire posait la valeur brute sur `User.password` sans passer par le hasher, contrairement à `AuthController` qui, lui, hachait correctement.

Deux voies d'écriture pour la même donnée, une seule sécurisée. Corrigé par une surcharge de `persistEntity()` qui hache avant écriture et réutilise `PasswordValidator` pour la complexité — la même classe que l'API, pour que les deux voies ne puissent plus diverger.

C'est l'illustration de RG10 : une règle de gestion doit tenir **quelle que soit la voie d'écriture**, et un back-office est une voie d'écriture comme une autre.

### 6.9 CRUD `/user` anonyme — ✅ CORRIGÉ

Un scaffold `make:crud` oublié exposait `/user`, `/user/new`, `/user/{id}/edit` et `/user/{id}` (DELETE) en **accès anonyme** : `access_control` ne couvrait que `^/admin` et `^/api`. Son formulaire `UserType` exposait `roles` et `password` en champs libres, sans hachage.

**N'importe qui pouvait se créer un compte `ROLE_ADMIN`**, mot de passe en clair en base. RG10 et RG11 étaient violées toutes les deux.

Corrigé par suppression du contrôleur, du formulaire et des templates, plus une règle `^/user → ROLE_ADMIN` en filet contre une régénération.

> **C'est l'illustration la plus coûteuse de la leçon de §6.8** — et elle est arrivée pendant que ce document la formulait. §6.8 conclut : « une règle de gestion doit tenir **quelle que soit la voie d'écriture** ». Deux voies avaient été identifiées et sécurisées (l'API, EasyAdmin). **La troisième était dans le dépôt, non protégée, et personne ne l'avait cherchée.**
>
> Ce qui l'a trouvée n'est pas un raisonnement mais un inventaire : `debug:router` confronté à `access_control`. Énumérer les voies d'écriture est une opération mécanique — l'omettre parce qu'on croit les connaître est précisément l'erreur.

### 6.10 Récapitulatif

| # | Défaut | Gravité | Statut |
|---|---|---|---|
| 6.1 | Statut de paiement dupliqué | 🔴 | ✅ Corrigé, **appliqué** et testé — `OrderPaymentTest` |
| 6.2 | Cascade remove inversé Payment → Order | 🔴 | ✅ Idem — le sens du cascade est tenu par un test |
| 6.3 | `clientSecret` persisté | 🟠 | À réexaminer |
| 6.4 | Gestion de stock | 🟡 | ✅ **Implémenté** — `Product.stock`, `decrementStock()`, migration `Version20260901120000` |
| 6.5 | `processed_by_user_id` absent | 🟡 | ✅ **Clos** — RG12 abandonnée, traitement par email |
| 6.6 | Nommage des colonnes | 🟡 | ✅ **Vérifié — conforme** (`underscore`) |
| 6.8 | Mot de passe en clair via EasyAdmin | 🔴 | ✅ Corrigé — `persistEntity()` hache |
| 6.9 | CRUD `/user` anonyme exposant `roles` | 🔴 | ✅ **Corrigé** — supprimé le 17/07/2026 |

**Les deux 🔴 d'origine sont désormais réellement traités** — migration appliquée sur `volo` le 17/07/2026, `schema:validate` vert, neuf tests. Ils ne l'étaient pas quand ce tableau l'a affirmé une première fois : la migration qui les traitait était cassée et n'avait jamais tourné. Voir [CORRECTION.md](CORRECTION.md).

Les 🔴 de 6.8 et 6.9 sont traités aussi. **Le webhook Stripe est désormais implémenté** (`WebhookController`) — les commandes passent automatiquement à `paid` après capture du paiement. Tous les 🔴 du projet sont résolus.

**Sur 6.5** : le défaut n'était pas qu'une colonne manque — c'est que **les messages n'étaient lus par personne**, et depuis la mise en place du CSRF, qu'ils n'arrivaient même plus (403 pour tout visiteur anonyme). Le formulaire ne fonctionnait d'aucun bout.

Résolu en ajoutant la **notification par email** plutôt que la colonne : le message est persisté (trace durable) *et* envoyé à l'administrateur (notification). La base garantit qu'un envoi raté ne perd rien ; l'email garantit qu'un humain voit le message. `processed_by_user_id` aurait dupliqué un état que la boîte mail tient déjà.
