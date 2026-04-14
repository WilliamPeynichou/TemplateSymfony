<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414111303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE composition (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, updated_at DATETIME NOT NULL, team_id INT NOT NULL, UNIQUE INDEX UNIQ_C7F4347296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, number INT NOT NULL, position VARCHAR(10) NOT NULL, photo VARCHAR(255) DEFAULT NULL, date_of_birth DATETIME DEFAULT NULL, strong_foot VARCHAR(10) DEFAULT NULL, height INT DEFAULT NULL, weight INT DEFAULT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, INDEX IDX_98197A65296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player_position (id INT AUTO_INCREMENT NOT NULL, pos_x DOUBLE PRECISION NOT NULL, pos_y DOUBLE PRECISION NOT NULL, instructions LONGTEXT DEFAULT NULL, composition_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_40FBA51587A2E12 (composition_id), INDEX IDX_40FBA51599E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, club VARCHAR(150) DEFAULT NULL, season VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, coach_id INT NOT NULL, INDEX IDX_C4E0A61F3C105691 (coach_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE composition ADD CONSTRAINT FK_C7F4347296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE player_position ADD CONSTRAINT FK_40FBA51587A2E12 FOREIGN KEY (composition_id) REFERENCES composition (id)');
        $this->addSql('ALTER TABLE player_position ADD CONSTRAINT FK_40FBA51599E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F3C105691 FOREIGN KEY (coach_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE composition DROP FOREIGN KEY FK_C7F4347296CD8AE');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65296CD8AE');
        $this->addSql('ALTER TABLE player_position DROP FOREIGN KEY FK_40FBA51587A2E12');
        $this->addSql('ALTER TABLE player_position DROP FOREIGN KEY FK_40FBA51599E6F5DF');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F3C105691');
        $this->addSql('DROP TABLE composition');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE player_position');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE `user`');
    }
}
