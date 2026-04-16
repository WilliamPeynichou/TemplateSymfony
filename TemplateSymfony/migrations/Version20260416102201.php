<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416102201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE match_note (id INT AUTO_INCREMENT NOT NULL, match_label VARCHAR(150) NOT NULL, content LONGTEXT NOT NULL, match_date DATETIME NOT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, coach_id INT NOT NULL, INDEX IDX_AED24B66296CD8AE (team_id), INDEX IDX_AED24B663C105691 (coach_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE match_note ADD CONSTRAINT FK_AED24B66296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE match_note ADD CONSTRAINT FK_AED24B663C105691 FOREIGN KEY (coach_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE match_note DROP FOREIGN KEY FK_AED24B66296CD8AE');
        $this->addSql('ALTER TABLE match_note DROP FOREIGN KEY FK_AED24B663C105691');
        $this->addSql('DROP TABLE match_note');
    }
}
