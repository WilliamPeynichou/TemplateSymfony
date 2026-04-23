<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player availability status, contact fields and status history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE player
            ADD status VARCHAR(20) NOT NULL DEFAULT 'present',
            ADD status_reason LONGTEXT DEFAULT NULL,
            ADD email VARCHAR(180) DEFAULT NULL,
            ADD phone VARCHAR(30) DEFAULT NULL,
            ADD emergency_contact VARCHAR(180) DEFAULT NULL");

        $this->addSql('CREATE TABLE player_status_history (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            changed_by_id INT DEFAULT NULL,
            old_status VARCHAR(20) DEFAULT NULL,
            new_status VARCHAR(20) NOT NULL,
            reason LONGTEXT DEFAULT NULL,
            changed_at DATETIME NOT NULL,
            INDEX IDX_PLAYER_STATUS_HISTORY_PLAYER (player_id),
            INDEX IDX_PLAYER_STATUS_HISTORY_CHANGED_BY (changed_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE player_status_history ADD CONSTRAINT FK_PLAYER_STATUS_HISTORY_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player_status_history ADD CONSTRAINT FK_PLAYER_STATUS_HISTORY_CHANGED_BY FOREIGN KEY (changed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_status_history DROP FOREIGN KEY FK_PLAYER_STATUS_HISTORY_PLAYER');
        $this->addSql('ALTER TABLE player_status_history DROP FOREIGN KEY FK_PLAYER_STATUS_HISTORY_CHANGED_BY');
        $this->addSql('DROP TABLE player_status_history');
        $this->addSql('ALTER TABLE player DROP status, DROP status_reason, DROP email, DROP phone, DROP emergency_contact');
    }
}
