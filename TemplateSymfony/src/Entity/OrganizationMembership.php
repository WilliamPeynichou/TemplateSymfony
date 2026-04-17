<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganizationMembershipRepository::class)]
#[ORM\Table(name: 'organization_membership')]
#[ORM\UniqueConstraint(name: 'UNIQ_ORG_USER', columns: ['organization_id', 'user_id'])]
class OrganizationMembership
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_COACH = 'coach';
    public const ROLE_STAFF = 'staff';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_COACH;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $o): static
    {
        $this->organization = $o;

        return $this;
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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
