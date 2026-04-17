<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing: subscription_plan + subscription.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscription_plan (
            id INT AUTO_INCREMENT NOT NULL,
            slug VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            stripe_price_id VARCHAR(120) DEFAULT NULL,
            price_cents INT NOT NULL,
            currency VARCHAR(3) NOT NULL,
            billing_interval VARCHAR(20) NOT NULL,
            active TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_SUB_PLAN_SLUG (slug),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE subscription (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            plan_id INT NOT NULL,
            stripe_customer_id VARCHAR(120) DEFAULT NULL,
            stripe_subscription_id VARCHAR(120) DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            current_period_end DATETIME DEFAULT NULL,
            cancel_at_period_end TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_SUB_USER (user_id),
            INDEX IDX_SUB_PLAN (plan_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_SUB_USER FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_SUB_PLAN FOREIGN KEY (plan_id) REFERENCES subscription_plan (id)');

        // Seed des plans de base (prix à ajuster selon la grille réelle)
        $this->addSql("INSERT INTO subscription_plan (slug, name, price_cents, currency, billing_interval, active) VALUES
            ('free', 'Free', 0, 'EUR', 'month', 1),
            ('club', 'Club', 900, 'EUR', 'month', 1),
            ('club_plus', 'Club+', 1900, 'EUR', 'month', 1)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_SUB_USER');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_SUB_PLAN');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE subscription_plan');
    }
}
