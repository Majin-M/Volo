<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610125814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brand (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, logo_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, is_processed TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, product_name VARCHAR(255) NOT NULL, order_entity_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_52EA1F093DA206A5 (order_entity_id), INDEX IDX_52EA1F094584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, method VARCHAR(255) NOT NULL, client_secret VARCHAR(255) DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, order_entity_id INT NOT NULL, UNIQUE INDEX UNIQ_6D28840D3DA206A5 (order_entity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, is_available TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, brand_id INT NOT NULL, INDEX IDX_D34A04AD44F5D008 (brand_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_skin_concern (product_id INT NOT NULL, skin_concern_id INT NOT NULL, INDEX IDX_5677164C4584665A (product_id), INDEX IDX_5677164CFFB3E517 (skin_concern_id), PRIMARY KEY (product_id, skin_concern_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE routine (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, level VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE routine_product (routine_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_13B44435F27A94C7 (routine_id), INDEX IDX_13B444354584665A (product_id), PRIMARY KEY (routine_id, product_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shop_order (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_323FC9CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skin_concern (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_DBD33427989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F093DA206A5 FOREIGN KEY (order_entity_id) REFERENCES shop_order (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D3DA206A5 FOREIGN KEY (order_entity_id) REFERENCES shop_order (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id)');
        $this->addSql('ALTER TABLE product_skin_concern ADD CONSTRAINT FK_5677164C4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_skin_concern ADD CONSTRAINT FK_5677164CFFB3E517 FOREIGN KEY (skin_concern_id) REFERENCES skin_concern (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE routine_product ADD CONSTRAINT FK_13B44435F27A94C7 FOREIGN KEY (routine_id) REFERENCES routine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE routine_product ADD CONSTRAINT FK_13B444354584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_323FC9CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F093DA206A5');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D3DA206A5');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD44F5D008');
        $this->addSql('ALTER TABLE product_skin_concern DROP FOREIGN KEY FK_5677164C4584665A');
        $this->addSql('ALTER TABLE product_skin_concern DROP FOREIGN KEY FK_5677164CFFB3E517');
        $this->addSql('ALTER TABLE routine_product DROP FOREIGN KEY FK_13B44435F27A94C7');
        $this->addSql('ALTER TABLE routine_product DROP FOREIGN KEY FK_13B444354584665A');
        $this->addSql('ALTER TABLE shop_order DROP FOREIGN KEY FK_323FC9CAA76ED395');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_skin_concern');
        $this->addSql('DROP TABLE routine');
        $this->addSql('DROP TABLE routine_product');
        $this->addSql('DROP TABLE shop_order');
        $this->addSql('DROP TABLE skin_concern');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
