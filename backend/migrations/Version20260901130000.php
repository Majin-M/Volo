<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft delete (deletedAt) to Order/Payment, UUID reference to Order, and AuditLog table';
    }

    public function up(Schema $schema): void
    {
        // Soft Delete
        $this->addSql('ALTER TABLE shop_order ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD deleted_at DATETIME DEFAULT NULL');

        // UUID reference on Order — add nullable first, backfill, then set NOT NULL
        $this->addSql('ALTER TABLE shop_order ADD reference VARCHAR(36) DEFAULT NULL');

        // Backfill existing orders with unique UUIDs
        $rows = $this->connection->fetchAllAssociative('SELECT id FROM shop_order');
        foreach ($rows as $row) {
            $uuid = $this->generateUuid();
            $this->addSql('UPDATE shop_order SET reference = ? WHERE id = ?', [$uuid, $row['id']]);
        }

        $this->addSql('ALTER TABLE shop_order CHANGE reference reference VARCHAR(36) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_338B4B18AEA34913 ON shop_order (reference)');

        // Audit Log table
        $this->addSql('CREATE TABLE audit_log (
            id INT AUTO_INCREMENT NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            action VARCHAR(20) NOT NULL,
            field VARCHAR(100) DEFAULT NULL,
            old_value VARCHAR(255) DEFAULT NULL,
            new_value VARCHAR(255) DEFAULT NULL,
            user_identifier VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_audit_entity (entity_type, entity_id),
            INDEX idx_audit_date (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP INDEX UNIQ_338B4B18AEA34913 ON shop_order');
        $this->addSql('ALTER TABLE shop_order DROP reference, DROP deleted_at');
        $this->addSql('ALTER TABLE payment DROP deleted_at');
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
