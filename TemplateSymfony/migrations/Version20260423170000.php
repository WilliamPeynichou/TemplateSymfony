<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player attendance tracking for fixtures and training sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE attendance (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            fixture_id INT DEFAULT NULL,
            training_session_id INT DEFAULT NULL,
            recorded_by_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            reason LONGTEXT DEFAULT NULL,
            recorded_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_ATTENDANCE_PLAYER (player_id),
            INDEX IDX_ATTENDANCE_FIXTURE (fixture_id),
            INDEX IDX_ATTENDANCE_TRAINING_SESSION (training_session_id),
            INDEX IDX_ATTENDANCE_RECORDED_BY (recorded_by_id),
            UNIQUE INDEX UNIQ_ATTENDANCE_PLAYER_FIXTURE (player_id, fixture_id),
            UNIQUE INDEX UNIQ_ATTENDANCE_PLAYER_TRAINING (player_id, training_session_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_ATTENDANCE_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_ATTENDANCE_FIXTURE FOREIGN KEY (fixture_id) REFERENCES fixture (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_ATTENDANCE_TRAINING_SESSION FOREIGN KEY (training_session_id) REFERENCES training_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_ATTENDANCE_RECORDED_BY FOREIGN KEY (recorded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_ATTENDANCE_PLAYER');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_ATTENDANCE_FIXTURE');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_ATTENDANCE_TRAINING_SESSION');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_ATTENDANCE_RECORDED_BY');
        $this->addSql('DROP TABLE attendance');
    }
}
