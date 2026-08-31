<?php

declare(strict_types=1);

/*
===============================================================================
Migration : suppression du doublon de statut de paiement
===============================================================================
Ce qu'elle fait :
    1. Reprend les données : pour chaque commande qui portait un statut de
       paiement SANS enregistrement Payment correspondant, crée le Payment
       manquant. Sans cette étape, un DROP COLUMN sec perdrait l'information.
    2. Supprime shop_order.payment_status et shop_order.payment_method.
    3. Renomme payment.order_entity_id en payment.order_id, et recrée sa clé
       étrangère avec ON DELETE CASCADE.

Pourquoi les noms de colonnes sont détectés dynamiquement :
    docs/MODELE_DONNEES.md section 6.6 signale un point jamais vérifié : selon
    la stratégie de nommage Doctrine configurée, les colonnes s'appellent
    'payment_status' (UnderscoreNamingStrategy, le défaut Symfony) ou
    'paymentStatus' (DefaultNamingStrategy). Écrire un nom en dur ferait
    échouer la migration dans un cas sur deux.

    Plutôt que de deviner, cette migration interroge information_schema et
    s'adapte. Elle affiche au passage le nom réel trouvé — ce qui répond enfin
    à la question ouverte de la section 6.6. Réponse obtenue à l'exécution :
    la stratégie est "underscore".

Pourquoi l'étape 3 est un renommage et pas une simple recréation :
    la première version de cette migration écrivait 'order_id' en dur, alors que
    Version20260610125814 avait créé la colonne sous le nom 'order_entity_id'
    (dérivé par Doctrine de la propriété $orderEntity, qui ne portait pas de
    name: explicite). L'entité déclare aujourd'hui name: 'order_id' : la colonne
    doit donc être RENOMMÉE. Faute de quoi la migration échouait dès sa première
    requête, sur toute base existante — et n'a de fait jamais pu s'appliquer.

    C'est exactement l'erreur contre laquelle le paragraphe précédent met en
    garde, commise sur une autre colonne. La détection est donc appliquée à la
    clé étrangère aussi, et plus seulement aux deux colonnes de statut.

Réversibilité :
    down() recrée les colonnes et recopie les valeurs depuis payment. La
    réversion est donc fidèle, à une exception près : les Payment créés par
    l'étape 1 de up() ne sont pas supprimés (ils constituent l'information
    correcte ; les détruire reperdrait ce que la migration a sauvé).
===============================================================================
*/

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime shop_order.payment_status / payment_method (doublon de payment.status / method) '
             . 'et corrige la clé étrangère payment -> shop_order en ON DELETE CASCADE.';
    }

    public function up(Schema $schema): void
    {
        $statusCol = $this->trouverColonne('shop_order', ['payment_status', 'paymentStatus']);
        $methodCol = $this->trouverColonne('shop_order', ['payment_method', 'paymentMethod']);
        $fkCol     = $this->trouverColonneDeJointure();

        $this->write(sprintf('Colonne de jointure détectée : payment.%s', $fkCol));

        if ($statusCol === null && $methodCol === null) {
            $this->write('Colonnes déjà absentes — rien à faire sur shop_order.');
        } else {
            $this->write(sprintf(
                'Colonnes détectées : %s / %s — la stratégie de nommage est donc "%s".',
                $statusCol ?? '(absente)',
                $methodCol ?? '(absente)',
                ($statusCol === 'payment_status') ? 'underscore' : 'camelCase',
            ));

            if ($statusCol !== null && $methodCol !== null) {
                $this->reprendreLesDonnees($statusCol, $methodCol, $fkCol);
            } else {
                $this->warnIf(true, 'Une seule des deux colonnes est présente : reprise de données ignorée.');
            }

            if ($statusCol !== null) {
                $this->addSql(sprintf('ALTER TABLE shop_order DROP COLUMN `%s`', $statusCol));
            }
            if ($methodCol !== null) {
                $this->addSql(sprintf('ALTER TABLE shop_order DROP COLUMN `%s`', $methodCol));
            }
        }

        $this->renommerEtRecreerLaCleEtrangere($fkCol, 'order_id', 'CASCADE', 'UNIQ_6D28840D8D9F6D38');
    }

    public function down(Schema $schema): void
    {
        $fkCol = $this->trouverColonneDeJointure();

        $this->addSql("ALTER TABLE shop_order ADD payment_status VARCHAR(50) DEFAULT NULL");
        $this->addSql("ALTER TABLE shop_order ADD payment_method VARCHAR(50) DEFAULT NULL");

        // Recopie depuis la source de vérité, pour que la réversion soit fidèle.
        $this->addSql(sprintf(
            'UPDATE shop_order o '
            . 'INNER JOIN payment p ON p.`%s` = o.id '
            . 'SET o.payment_status = p.status, o.payment_method = p.method',
            $fkCol
        ));

        // Symétrie stricte : up() a renommé vers order_id, down() revient au nom
        // d'origine et retire le ON DELETE CASCADE.
        $this->renommerEtRecreerLaCleEtrangere($fkCol, 'order_entity_id', null, 'UNIQ_6D28840D3DA206A5');
    }

    /**
     * Retourne le premier nom de colonne réellement présent, ou null.
     *
     * @param string[] $candidats
     */
    private function trouverColonne(string $table, array $candidats): ?string
    {
        foreach ($candidats as $candidat) {
            $existe = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $candidat]
            );

            if ((int) $existe > 0) {
                return $candidat;
            }
        }

        return null;
    }

    /**
     * Retourne le nom réel de la colonne de jointure payment -> shop_order.
     *
     * Même raisonnement que trouverColonne(), appliqué à la clé étrangère : les
     * premières migrations ont créé la colonne sous le nom dérivé par Doctrine
     * de la propriété $orderEntity, soit `order_entity_id`. L'entité déclare
     * désormais `name: 'order_id'` — up() doit donc RENOMMER la colonne, pas
     * supposer qu'elle porte déjà le nom cible.
     *
     * Écrire `order_id` en dur ici faisait échouer la migration sur toute base
     * antérieure au renommage — c'est-à-dire sur toutes.
     */
    private function trouverColonneDeJointure(): string
    {
        $col = $this->trouverColonne('payment', ['order_id', 'order_entity_id']);

        if ($col === null) {
            throw new \RuntimeException(
                'Aucune colonne de jointure trouvée sur payment (ni order_id ni order_entity_id). '
                . 'Migration interrompue avant tout DROP COLUMN : le schéma attendu n\'est pas celui-ci.'
            );
        }

        return $col;
    }

    /**
     * Crée les Payment manquants à partir des colonnes de shop_order.
     *
     * Ne traite que les commandes dont les DEUX colonnes sont renseignées :
     * payment.method est NOT NULL, et inventer un moyen de paiement par défaut
     * fabriquerait une donnée fausse plutôt que d'en sauver une vraie.
     *
     * $fkCol porte le nom de la colonne AVANT renommage : cette reprise est mise
     * en file d'attente avant le ALTER de renommage, donc elle voit l'ancien nom.
     */
    private function reprendreLesDonnees(string $statusCol, string $methodCol, string $fkCol): void
    {
        $aReprendre = (int) $this->connection->fetchOne(sprintf(
            'SELECT COUNT(*) FROM shop_order o '
            . 'WHERE o.`%s` IS NOT NULL AND o.`%s` IS NOT NULL '
            . 'AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.`%s` = o.id)',
            $statusCol,
            $methodCol,
            $fkCol
        ));

        $incomplets = (int) $this->connection->fetchOne(sprintf(
            'SELECT COUNT(*) FROM shop_order o '
            . 'WHERE o.`%s` IS NOT NULL AND o.`%s` IS NULL '
            . 'AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.`%s` = o.id)',
            $statusCol,
            $methodCol,
            $fkCol
        ));

        $this->write(sprintf('Commandes à reprendre en Payment : %d', $aReprendre));

        $this->warnIf(
            $incomplets > 0,
            sprintf(
                '%d commande(s) ont un statut de paiement mais aucun moyen de paiement : '
                . 'aucun Payment ne peut être créé pour elles (method est NOT NULL). '
                . 'Leur statut sera PERDU. Vérifiez-les avant de poursuivre.',
                $incomplets
            )
        );

        if ($aReprendre > 0) {
            $this->addSql(sprintf(
                'INSERT INTO payment (`%s`, status, method, amount, client_secret, created_at, updated_at) '
                . 'SELECT o.id, o.`%s`, o.`%s`, o.total, NULL, NOW(), NOW() '
                . 'FROM shop_order o '
                . 'WHERE o.`%s` IS NOT NULL AND o.`%s` IS NOT NULL '
                . 'AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.`%s` = o.id)',
                $fkCol,
                $statusCol,
                $methodCol,
                $statusCol,
                $methodCol,
                $fkCol
            ));
        }
    }

    /**
     * Renomme la colonne de jointure si besoin, et recrée sa contrainte.
     *
     * Les trois opérations sont indissociables et leur ordre est imposé par
     * MySQL : une colonne portée par une clé étrangère ne peut pas être
     * renommée tant que la contrainte existe. D'où DROP FK -> CHANGE -> ADD FK.
     *
     * Le nom de la contrainte est généré par Doctrine (FK_<hash>) : il est lu
     * dans information_schema plutôt que deviné.
     *
     * L'index UNIQUE de la colonne survit au CHANGE (MySQL le suit
     * automatiquement) — c'est lui qui porte la cardinalité (0,1) de RG8.
     */
    private function renommerEtRecreerLaCleEtrangere(
        string $colActuelle,
        string $colCible,
        ?string $onDelete,
        string $indexCible,
    ): void {
        $nomFk = $this->connection->fetchOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' "
            . 'AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ? '
            . 'LIMIT 1',
            [$colActuelle, 'shop_order']
        );

        $fkTrouvee = ($nomFk !== false && $nomFk !== null);

        $this->warnIf(
            !$fkTrouvee,
            sprintf('Clé étrangère sur payment.%s introuvable — elle sera créée.', $colActuelle)
        );

        if ($fkTrouvee) {
            $this->addSql(sprintf('ALTER TABLE payment DROP FOREIGN KEY `%s`', $nomFk));
        }

        if ($colActuelle !== $colCible) {
            $this->write(sprintf('Renommage : payment.%s -> payment.%s', $colActuelle, $colCible));
            $this->addSql(sprintf(
                'ALTER TABLE payment CHANGE `%s` `%s` INT NOT NULL',
                $colActuelle,
                $colCible
            ));

            $this->renommerIndexUnique($colActuelle, $colCible, $indexCible);
        }

        $this->addSql(sprintf(
            'ALTER TABLE payment ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES shop_order (id)%s',
            $fkTrouvee ? $nomFk : 'FK_payment_order',
            $colCible,
            $onDelete !== null ? ' ON DELETE ' . $onDelete : ''
        ));
    }

    /**
     * Aligne le nom de l'index UNIQUE sur celui qu'attend Doctrine.
     *
     * Le CHANGE de colonne conserve l'index et sa contrainte, mais PAS son nom :
     * il reste haché sur l'ancien nom de colonne. Doctrine dérive le nom attendu
     * du nom de colonne (UNIQ_<hash>) et considère le schéma désynchronisé tant
     * qu'il diffère — `doctrine:schema:validate` échoue, sans que rien ne soit
     * fonctionnellement cassé. C'est cosmétique, mais un schema:validate rouge
     * en permanence est un détecteur de fumée qu'on finit par ignorer.
     *
     * DROP + CREATE plutôt que RENAME INDEX : XAMPP livre MariaDB (10.4 ici),
     * pas MySQL 8 comme l'annonce docs/TECHNOLOGIES.md, et RENAME INDEX n'existe
     * qu'à partir de MariaDB 10.5.2 — il échoue en erreur de syntaxe 1064 sur
     * 10.4. DROP + CREATE fonctionne sur les deux moteurs.
     *
     * L'appelant doit avoir supprimé la clé étrangère au préalable : ni MySQL ni
     * MariaDB n'acceptent de supprimer un index qui porte une contrainte.
     *
     * $indexCible est fourni par l'appelant plutôt que recalculé : c'est la
     * valeur qu'a réclamée `doctrine:schema:update --dump-sql`, donc une valeur
     * observée, pas devinée.
     */
    private function renommerIndexUnique(string $colActuelle, string $colCible, string $indexCible): void
    {
        $indexActuel = $this->connection->fetchOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment' "
            . 'AND COLUMN_NAME = ? AND NON_UNIQUE = 0 '
            . 'LIMIT 1',
            [$colActuelle]
        );

        if ($indexActuel === false || $indexActuel === null) {
            $this->warnIf(true, sprintf('Index UNIQUE sur payment.%s introuvable — renommage ignoré.', $colActuelle));
            return;
        }

        if ($indexActuel === $indexCible) {
            return;
        }

        $this->write(sprintf('Renommage index : %s -> %s', $indexActuel, $indexCible));
        $this->addSql(sprintf('DROP INDEX `%s` ON payment', $indexActuel));
        $this->addSql(sprintf(
            'CREATE UNIQUE INDEX `%s` ON payment (`%s`)',
            $indexCible,
            $colCible
        ));
    }

    public function isTransactional(): bool
    {
        // MySQL ne sait pas annuler un ALTER TABLE : une transaction donnerait
        // une fausse impression de sécurité. Sauvegardez avant d'exécuter.
        return false;
    }
}
