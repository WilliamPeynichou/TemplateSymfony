<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending_action column on agent_conversation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_conversation ADD pending_action JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_conversation DROP pending_action');
    }
}
