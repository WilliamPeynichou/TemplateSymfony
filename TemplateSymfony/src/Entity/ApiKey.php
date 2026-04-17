<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Clé d'API publique permettant à un coach d'accéder à /api/public/*.
 *
 * Sécurité : on ne stocke jamais la clé en clair, uniquement un hash.
 * À la création, on affiche la clé une seule fois.
 */
#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_key')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(length: 12)]
    private string $prefix = '';

    #[ORM\Column(length: 255)]
    private string $hash = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private bool $revoked = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $u): static
    {
        $this->user = $u;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $n): static
    {
        $this->name = $n;

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $p): static
    {
        $this->prefix = $p;

        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $h): static
    {
        $this->hash = $h;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touchLastUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $r): static
    {
        $this->revoked = $r;

        return $this;
    }

    /**
     * Génère une nouvelle clé. Retourne [clé en clair à montrer une seule fois, entité ApiKey].
     *
     * @return array{0: string, 1: ApiKey}
     */
    public static function generateFor(User $user, string $name): array
    {
        $raw = 'ak_'.bin2hex(random_bytes(24));
        $prefix = substr($raw, 0, 10);

        $key = new self();
        $key->setUser($user)
            ->setName($name)
            ->setPrefix($prefix)
            ->setHash(hash('sha256', $raw));

        return [$raw, $key];
    }
}
