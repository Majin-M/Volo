<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock column to product table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD stock INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN stock');
    }
}
