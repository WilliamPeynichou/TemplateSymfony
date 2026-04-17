<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Souscription d'un utilisateur à un plan. 1 User = 1 Subscription active.
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscription')]
class Subscription
{
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_INCOMPLETE = 'incomplete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubscriptionPlan $plan = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    #[ORM\Column]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPlan(): ?SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(?SubscriptionPlan $plan): static
    {
        $this->plan = $plan;
        $this->touch();

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $id): static
    {
        $this->stripeCustomerId = $id;

        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $id): static
    {
        $this->stripeSubscriptionId = $id;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(?\DateTimeImmutable $dt): static
    {
        $this->currentPeriodEnd = $dt;

        return $this;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(bool $v): static
    {
        $this->cancelAtPeriodEnd = $v;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return \in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
