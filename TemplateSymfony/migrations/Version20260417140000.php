<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organization + membership + invitation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization (
            id INT AUTO_INCREMENT NOT NULL,
            owner_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_ORG_OWNER (owner_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE organization_membership (
            id INT AUTO_INCREMENT NOT NULL,
            organization_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(20) NOT NULL,
            joined_at DATETIME NOT NULL,
            INDEX IDX_ORG_MEM_ORG (organization_id),
            INDEX IDX_ORG_MEM_USER (user_id),
            UNIQUE INDEX UNIQ_ORG_USER (organization_id, user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE organization_invitation (
            id INT AUTO_INCREMENT NOT NULL,
            organization_id INT NOT NULL,
            email VARCHAR(180) NOT NULL,
            token VARCHAR(80) NOT NULL,
            role VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_ORG_INV_TOKEN (token),
            INDEX IDX_ORG_INV_ORG (organization_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_ORG_OWNER FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_ORG_MEM_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_ORG_MEM_USER FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE organization_invitation ADD CONSTRAINT FK_ORG_INV_ORG FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_invitation DROP FOREIGN KEY FK_ORG_INV_ORG');
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_ORG_MEM_ORG');
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_ORG_MEM_USER');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_ORG_OWNER');
        $this->addSql('DROP TABLE organization_invitation');
        $this->addSql('DROP TABLE organization_membership');
        $this->addSql('DROP TABLE organization');
    }
}
