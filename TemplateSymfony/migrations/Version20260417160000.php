<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'API keys for public API access.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_key (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            prefix VARCHAR(12) NOT NULL,
            hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME DEFAULT NULL,
            revoked TINYINT(1) NOT NULL,
            INDEX IDX_API_KEY_USER (user_id),
            UNIQUE INDEX UNIQ_API_KEY_HASH (hash),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE api_key ADD CONSTRAINT FK_API_KEY_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_key DROP FOREIGN KEY FK_API_KEY_USER');
        $this->addSql('DROP TABLE api_key');
    }
}
