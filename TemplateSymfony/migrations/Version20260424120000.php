<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fusion Plan + TacticalStrategy : ajout colonne mode, migration des plans, suppression tables plan/plan_note.';
    }

    public function up(Schema $schema): void
    {
        // 1. Étendre tactical_strategy avec mode et legacy_plan_id
        $this->addSql("ALTER TABLE tactical_strategy
            ADD COLUMN mode VARCHAR(20) NOT NULL DEFAULT 'formation',
            ADD COLUMN legacy_plan_id INT DEFAULT NULL");

        // 2. Pour chaque plan existant, créer une tactical_strategy en mode free
        $this->addSql("
            INSERT INTO tactical_strategy
                (team_id, mode, legacy_plan_id, name, description, formation, mentality,
                 pressing_intensity, defensive_line, build_up_style, width, tempo,
                 attacking_focus, is_default, usage_count, created_at, updated_at)
            SELECT
                p.team_id,
                'free',
                p.id,
                p.name,
                p.description,
                '4-3-3',
                'balanced',
                'medium',
                'standard',
                'mixed',
                'standard',
                'standard',
                'balanced',
                0,
                0,
                p.created_at,
                p.updated_at
            FROM plan p
        ");

        // 3. Migrer les plan_note → formation_slot (liées à la nouvelle tactical_strategy)
        $this->addSql("
            INSERT INTO formation_slot
                (strategy_id, slot_index, position_code, label, role, duty,
                 pos_x, pos_y, player_id, individual_instructions)
            SELECT
                ts.id,
                pn.id,
                'CM',
                '?',
                'box_to_box',
                'support',
                pn.pos_x,
                pn.pos_y,
                pn.player_id,
                pn.note
            FROM plan_note pn
            JOIN tactical_strategy ts ON ts.legacy_plan_id = pn.plan_id
        ");

        // 4. Supprimer les anciennes tables (les FK cascadent automatiquement)
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE IF EXISTS plan_note');
        $this->addSql('DROP TABLE IF EXISTS plan');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        // Recréer les tables supprimées
        $this->addSql('CREATE TABLE plan (
            id INT AUTO_INCREMENT NOT NULL,
            team_id INT NOT NULL,
            name VARCHAR(150) NOT NULL DEFAULT \'Nouveau plan\',
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_PLAN_TEAM (team_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_PLAN_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE plan_note (
            id INT AUTO_INCREMENT NOT NULL,
            plan_id INT NOT NULL,
            player_id INT NOT NULL,
            pos_x DOUBLE PRECISION NOT NULL DEFAULT 50,
            pos_y DOUBLE PRECISION NOT NULL DEFAULT 50,
            note LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE plan_note ADD CONSTRAINT FK_PLAN_NOTE_PLAN FOREIGN KEY (plan_id) REFERENCES plan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_note ADD CONSTRAINT FK_PLAN_NOTE_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');

        // Retirer les colonnes ajoutées
        $this->addSql('ALTER TABLE tactical_strategy DROP COLUMN mode, DROP COLUMN legacy_plan_id');

        // Note : la migration inverse ne restore pas les données (irréversible côté données)
    }
}
