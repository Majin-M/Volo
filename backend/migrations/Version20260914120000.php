<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Converge le schema reel sur le mapping, dans TOUS les environnements.
 *
 * Constat du 14/09/2026 : les migrations ne produisaient pas le schema que
 * decrivent les entites, et la base de developpement ne correspondait pas non
 * plus aux migrations qu'elle declare avoir appliquees. Deux ecarts reels,
 * un par environnement :
 *
 *   - Base construite par les migrations (Docker, CI, futur serveur) :
 *     Version20260901130000 cree l'index unique de shop_order.reference sous
 *     le nom UNIQ_338B4B18AEA34913, alors que Doctrine attend
 *     UNIQ_323FC9CAAEA34913 (prefixe de la table shop_order).
 *
 *   - Base de developpement (MariaDB 10.4) : l'index porte deja le bon nom,
 *     mais product.stock n'a pas le DEFAULT 0 que Version20260901120000
 *     declare.
 *
 * Deux contraintes dictent la forme de cette migration :
 *
 *   1. PAS DE `RENAME INDEX`. Cette syntaxe n'existe dans MariaDB qu'a partir
 *      de 10.5.2 : sur le poste de developpement elle echoue en erreur 1064,
 *      exactement comme l'avait deja fait Version20260717120000. On passe par
 *      DROP INDEX + CREATE UNIQUE INDEX, compris par les deux moteurs.
 *
 *   2. L'INDEX N'EST TOUCHE QUE S'IL PORTE LE MAUVAIS NOM. Sur la base de
 *      developpement il n'existe pas sous ce nom : un DROP inconditionnel
 *      ferait echouer la migration. Le Schema recu en parametre est lu depuis
 *      la base reelle, ce qui permet de decider au cas par cas.
 *
 * `ALTER ... SET DEFAULT 0` est sans effet la ou le defaut existe deja.
 */
final class Version20260914120000 extends AbstractMigration
{
    private const INDEX_ERRONE = 'UNIQ_338B4B18AEA34913';
    private const INDEX_ATTENDU = 'UNIQ_323FC9CAAEA34913';

    public function getDescription(): string
    {
        return 'Converge le schema sur le mapping : nom de l\'index unique de shop_order.reference, defaut de product.stock';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER stock SET DEFAULT 0');

        if ($schema->getTable('shop_order')->hasIndex(self::INDEX_ERRONE)) {
            $this->addSql(sprintf('DROP INDEX %s ON shop_order', self::INDEX_ERRONE));
            $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON shop_order (reference)', self::INDEX_ATTENDU));
        }
    }

    public function down(Schema $schema): void
    {
        // Irreversible par nature, et c'est assume : revenir en arriere
        // recreerait la divergence entre environnements que cette migration
        // corrige, sans qu'on sache dans quel etat chacun se trouvait.
        $this->throwIrreversibleMigrationException(
            'Cette migration aligne des noms et un defaut sur le mapping ; l\'annuler reintroduirait une divergence entre environnements.'
        );
    }
}
