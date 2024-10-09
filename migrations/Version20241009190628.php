<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241009190628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE advice (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE month (id INT AUTO_INCREMENT NOT NULL, month_number INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE month_advice (month_id INT NOT NULL, advice_id INT NOT NULL, INDEX IDX_6FB6E213A0CBDE4 (month_id), INDEX IDX_6FB6E21312998205 (advice_id), PRIMARY KEY(month_id, advice_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE month_advice ADD CONSTRAINT FK_6FB6E213A0CBDE4 FOREIGN KEY (month_id) REFERENCES month (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE month_advice ADD CONSTRAINT FK_6FB6E21312998205 FOREIGN KEY (advice_id) REFERENCES advice (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE month_advice DROP FOREIGN KEY FK_6FB6E213A0CBDE4');
        $this->addSql('ALTER TABLE month_advice DROP FOREIGN KEY FK_6FB6E21312998205');
        $this->addSql('DROP TABLE advice');
        $this->addSql('DROP TABLE month');
        $this->addSql('DROP TABLE month_advice');
    }
}
