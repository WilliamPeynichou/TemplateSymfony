<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416103050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_conversation (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, coach_id INT NOT NULL, team_id INT DEFAULT NULL, INDEX IDX_FCC2A9223C105691 (coach_id), INDEX IDX_FCC2A922296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE agent_message (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, content LONGTEXT NOT NULL, tool_calls JSON DEFAULT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, INDEX IDX_64EE52D59AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE agent_conversation ADD CONSTRAINT FK_FCC2A9223C105691 FOREIGN KEY (coach_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE agent_conversation ADD CONSTRAINT FK_FCC2A922296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE agent_message ADD CONSTRAINT FK_64EE52D59AC0396 FOREIGN KEY (conversation_id) REFERENCES agent_conversation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agent_conversation DROP FOREIGN KEY FK_FCC2A9223C105691');
        $this->addSql('ALTER TABLE agent_conversation DROP FOREIGN KEY FK_FCC2A922296CD8AE');
        $this->addSql('ALTER TABLE agent_message DROP FOREIGN KEY FK_64EE52D59AC0396');
        $this->addSql('DROP TABLE agent_conversation');
        $this->addSql('DROP TABLE agent_message');
    }
}
