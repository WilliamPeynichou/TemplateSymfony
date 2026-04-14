<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414123314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, team_id INT NOT NULL, INDEX IDX_DD5A5B7D296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plan_note (id INT AUTO_INCREMENT NOT NULL, pos_x DOUBLE PRECISION NOT NULL, pos_y DOUBLE PRECISION NOT NULL, note LONGTEXT DEFAULT NULL, plan_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_DA6B36E6E899029B (plan_id), INDEX IDX_DA6B36E699E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_DD5A5B7D296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE plan_note ADD CONSTRAINT FK_DA6B36E6E899029B FOREIGN KEY (plan_id) REFERENCES plan (id)');
        $this->addSql('ALTER TABLE plan_note ADD CONSTRAINT FK_DA6B36E699E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan DROP FOREIGN KEY FK_DD5A5B7D296CD8AE');
        $this->addSql('ALTER TABLE plan_note DROP FOREIGN KEY FK_DA6B36E6E899029B');
        $this->addSql('ALTER TABLE plan_note DROP FOREIGN KEY FK_DA6B36E699E6F5DF');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE plan_note');
    }
}
