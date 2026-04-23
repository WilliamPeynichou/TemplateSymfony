<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * FM-style squad management + tactical strategies.
 */
final class Version20260424000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player attributes, tactical strategies and formation slots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE player_attributes (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            pace SMALLINT NOT NULL DEFAULT 10,
            shooting SMALLINT NOT NULL DEFAULT 10,
            passing SMALLINT NOT NULL DEFAULT 10,
            dribbling SMALLINT NOT NULL DEFAULT 10,
            crossing SMALLINT NOT NULL DEFAULT 10,
            finishing SMALLINT NOT NULL DEFAULT 10,
            first_touch SMALLINT NOT NULL DEFAULT 10,
            heading SMALLINT NOT NULL DEFAULT 10,
            tackling SMALLINT NOT NULL DEFAULT 10,
            marking SMALLINT NOT NULL DEFAULT 10,
            vision SMALLINT NOT NULL DEFAULT 10,
            composure SMALLINT NOT NULL DEFAULT 10,
            decisions SMALLINT NOT NULL DEFAULT 10,
            work_rate SMALLINT NOT NULL DEFAULT 10,
            leadership SMALLINT NOT NULL DEFAULT 10,
            aggression SMALLINT NOT NULL DEFAULT 10,
            positioning SMALLINT NOT NULL DEFAULT 10,
            concentration SMALLINT NOT NULL DEFAULT 10,
            stamina SMALLINT NOT NULL DEFAULT 10,
            strength SMALLINT NOT NULL DEFAULT 10,
            agility SMALLINT NOT NULL DEFAULT 10,
            balance SMALLINT NOT NULL DEFAULT 10,
            jumping SMALLINT NOT NULL DEFAULT 10,
            acceleration SMALLINT NOT NULL DEFAULT 10,
            reflexes SMALLINT NOT NULL DEFAULT 10,
            handling SMALLINT NOT NULL DEFAULT 10,
            kicking SMALLINT NOT NULL DEFAULT 10,
            one_on_ones SMALLINT NOT NULL DEFAULT 10,
            command_of_area SMALLINT NOT NULL DEFAULT 10,
            `condition` SMALLINT NOT NULL DEFAULT 100,
            morale VARCHAR(20) NOT NULL DEFAULT \'okay\',
            potential_ability SMALLINT NOT NULL DEFAULT 50,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_PLAYER_ATTRIBUTES_PLAYER (player_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE player_attributes ADD CONSTRAINT FK_PLAYER_ATTRIBUTES_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE tactical_strategy (
            id INT AUTO_INCREMENT NOT NULL,
            team_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            formation VARCHAR(20) NOT NULL DEFAULT \'4-3-3\',
            mentality VARCHAR(30) NOT NULL DEFAULT \'balanced\',
            pressing_intensity VARCHAR(20) NOT NULL DEFAULT \'medium\',
            defensive_line VARCHAR(20) NOT NULL DEFAULT \'standard\',
            build_up_style VARCHAR(20) NOT NULL DEFAULT \'mixed\',
            width VARCHAR(20) NOT NULL DEFAULT \'standard\',
            tempo VARCHAR(20) NOT NULL DEFAULT \'standard\',
            attacking_focus VARCHAR(30) NOT NULL DEFAULT \'balanced\',
            in_possession_notes LONGTEXT DEFAULT NULL,
            out_of_possession_notes LONGTEXT DEFAULT NULL,
            transition_notes LONGTEXT DEFAULT NULL,
            set_piece_notes LONGTEXT DEFAULT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            usage_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_TACTICAL_STRATEGY_TEAM (team_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE tactical_strategy ADD CONSTRAINT FK_TACTICAL_STRATEGY_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE formation_slot (
            id INT AUTO_INCREMENT NOT NULL,
            strategy_id INT NOT NULL,
            player_id INT DEFAULT NULL,
            slot_index SMALLINT NOT NULL,
            position_code VARCHAR(10) NOT NULL,
            label VARCHAR(40) NOT NULL,
            role VARCHAR(50) NOT NULL,
            duty VARCHAR(20) NOT NULL,
            pos_x DOUBLE PRECISION NOT NULL,
            pos_y DOUBLE PRECISION NOT NULL,
            individual_instructions LONGTEXT DEFAULT NULL,
            INDEX IDX_FORMATION_SLOT_STRATEGY (strategy_id),
            INDEX IDX_FORMATION_SLOT_PLAYER (player_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE formation_slot ADD CONSTRAINT FK_FORMATION_SLOT_STRATEGY FOREIGN KEY (strategy_id) REFERENCES tactical_strategy (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation_slot ADD CONSTRAINT FK_FORMATION_SLOT_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation_slot DROP FOREIGN KEY FK_FORMATION_SLOT_PLAYER');
        $this->addSql('ALTER TABLE formation_slot DROP FOREIGN KEY FK_FORMATION_SLOT_STRATEGY');
        $this->addSql('DROP TABLE formation_slot');

        $this->addSql('ALTER TABLE tactical_strategy DROP FOREIGN KEY FK_TACTICAL_STRATEGY_TEAM');
        $this->addSql('DROP TABLE tactical_strategy');

        $this->addSql('ALTER TABLE player_attributes DROP FOREIGN KEY FK_PLAYER_ATTRIBUTES_PLAYER');
        $this->addSql('DROP TABLE player_attributes');
    }
}
