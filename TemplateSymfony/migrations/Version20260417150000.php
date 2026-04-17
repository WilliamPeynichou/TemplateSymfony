<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Player match stats + training session.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE player_match_stat (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            fixture_id INT NOT NULL,
            minutes_played INT NOT NULL,
            goals INT NOT NULL,
            assists INT NOT NULL,
            yellow_cards INT NOT NULL,
            red_cards INT NOT NULL,
            rating NUMERIC(3, 1) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_PMS_PLAYER (player_id),
            INDEX IDX_PMS_FIXTURE (fixture_id),
            UNIQUE INDEX UNIQ_PLAYER_FIXTURE (player_id, fixture_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE player_match_stat ADD CONSTRAINT FK_PMS_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player_match_stat ADD CONSTRAINT FK_PMS_FIXTURE FOREIGN KEY (fixture_id) REFERENCES fixture (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE training_session (
            id INT AUTO_INCREMENT NOT NULL,
            team_id INT NOT NULL,
            coach_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            starts_at DATETIME NOT NULL,
            duration_minutes INT NOT NULL,
            location VARCHAR(150) DEFAULT NULL,
            focus VARCHAR(150) DEFAULT NULL,
            plan LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_TRAIN_TEAM (team_id),
            INDEX IDX_TRAIN_COACH (coach_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT FK_TRAIN_TEAM FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE training_session ADD CONSTRAINT FK_TRAIN_COACH FOREIGN KEY (coach_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY FK_TRAIN_TEAM');
        $this->addSql('ALTER TABLE training_session DROP FOREIGN KEY FK_TRAIN_COACH');
        $this->addSql('DROP TABLE training_session');

        $this->addSql('ALTER TABLE player_match_stat DROP FOREIGN KEY FK_PMS_PLAYER');
        $this->addSql('ALTER TABLE player_match_stat DROP FOREIGN KEY FK_PMS_FIXTURE');
        $this->addSql('DROP TABLE player_match_stat');
    }
}
