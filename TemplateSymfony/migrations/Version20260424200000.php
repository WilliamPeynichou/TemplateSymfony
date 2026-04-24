<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'F1 Convocations : tables callup + callup_player';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE callup (
            id INT AUTO_INCREMENT NOT NULL,
            fixture_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_CALLUP_FIXTURE (fixture_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->addSql('ALTER TABLE callup
            ADD CONSTRAINT FK_CALLUP_FIXTURE FOREIGN KEY (fixture_id) REFERENCES fixture (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE callup_player (
            id INT AUTO_INCREMENT NOT NULL,
            callup_id INT NOT NULL,
            player_id INT NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT \'not_called\',
            reason VARCHAR(20) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            jersey_number INT DEFAULT NULL,
            UNIQUE INDEX UNIQ_CALLUP_PLAYER (callup_id, player_id),
            INDEX IDX_CP_PLAYER (player_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $this->addSql('ALTER TABLE callup_player
            ADD CONSTRAINT FK_CP_CALLUP FOREIGN KEY (callup_id) REFERENCES callup (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_CP_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE IF EXISTS callup_player');
        $this->addSql('DROP TABLE IF EXISTS callup');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
