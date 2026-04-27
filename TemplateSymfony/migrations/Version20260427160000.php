<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player.license_number (fédéral / club).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ADD license_number VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player DROP license_number');
    }
}
