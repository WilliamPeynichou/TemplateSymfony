<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubscriptionPlanRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plan d'abonnement disponible (Free, Club, Club+, etc.).
 *
 * Peuplé via une fixture/seed ou l'admin. Nommé SubscriptionPlan pour ne pas
 * entrer en collision avec l'entité `Plan` existante (plans tactiques).
 */
#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plan')]
class SubscriptionPlan
{
    public const SLUG_FREE = 'free';
    public const SLUG_CLUB = 'club';
    public const SLUG_CLUB_PLUS = 'club_plus';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripePriceId = null;

    #[ORM\Column]
    private int $priceCents = 0;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(length: 20)]
    private string $billingInterval = 'month';

    #[ORM\Column]
    private bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(?string $id): static
    {
        $this->stripePriceId = $id;

        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getBillingInterval(): string
    {
        return $this->billingInterval;
    }

    public function setBillingInterval(string $interval): static
    {
        $this->billingInterval = $interval;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isFree(): bool
    {
        return self::SLUG_FREE === $this->slug;
    }
}
