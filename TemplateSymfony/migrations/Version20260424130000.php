<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link fixtures to tactical strategies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fixture ADD tactical_strategy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fixture ADD CONSTRAINT FK_7D93C3C7746082B5 FOREIGN KEY (tactical_strategy_id) REFERENCES tactical_strategy (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7D93C3C7746082B5 ON fixture (tactical_strategy_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fixture DROP FOREIGN KEY FK_7D93C3C7746082B5');
        $this->addSql('DROP INDEX IDX_7D93C3C7746082B5 ON fixture');
        $this->addSql('ALTER TABLE fixture DROP tactical_strategy_id');
    }
}
