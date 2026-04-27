<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material_stock: inventaire matériel global (coach) et par équipe, lignes à libellé libre.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE material_stock (
            id INT AUTO_INCREMENT NOT NULL,
            coach_id INT NOT NULL,
            team_id INT DEFAULT NULL,
            label VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            unit VARCHAR(32) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            sort_order INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_MATERIAL_STOCK_COACH (coach_id),
            INDEX IDX_MATERIAL_STOCK_TEAM (team_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE material_stock ADD CONSTRAINT FK_MATERIAL_STOCK_COACH FOREIGN KEY (coach_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE material_stock ADD CONSTRAINT FK_MATERIAL_STOCK_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_stock DROP FOREIGN KEY FK_MATERIAL_STOCK_COACH');
        $this->addSql('ALTER TABLE material_stock DROP FOREIGN KEY FK_MATERIAL_STOCK_TEAM');
        $this->addSql('DROP TABLE material_stock');
    }
}
