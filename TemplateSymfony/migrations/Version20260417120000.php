<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table fixture (matchs joues/a venir) rattachee a team et user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fixture (
            id INT AUTO_INCREMENT NOT NULL,
            team_id INT NOT NULL,
            coach_id INT NOT NULL,
            opponent VARCHAR(150) NOT NULL,
            match_date DATETIME NOT NULL,
            venue VARCHAR(10) NOT NULL,
            score_for INT DEFAULT NULL,
            score_against INT DEFAULT NULL,
            competition VARCHAR(100) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_FIXTURE_TEAM (team_id),
            INDEX IDX_FIXTURE_COACH (coach_id),
            INDEX IDX_FIXTURE_DATE (match_date),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE fixture ADD CONSTRAINT FK_FIXTURE_TEAM FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE fixture ADD CONSTRAINT FK_FIXTURE_COACH FOREIGN KEY (coach_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fixture DROP FOREIGN KEY FK_FIXTURE_TEAM');
        $this->addSql('ALTER TABLE fixture DROP FOREIGN KEY FK_FIXTURE_COACH');
        $this->addSql('DROP TABLE fixture');
    }
}
