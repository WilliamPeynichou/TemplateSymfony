<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Organisation regroupe plusieurs coachs (club). 1 owner + N membres.
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organization')]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * @var Collection<int, OrganizationMembership>
     */
    #[ORM\OneToMany(targetEntity: OrganizationMembership::class, mappedBy: 'organization', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $memberships;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, OrganizationMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function hasMember(User $user): bool
    {
        if ($this->owner && $this->owner->getId() === $user->getId()) {
            return true;
        }
        foreach ($this->memberships as $m) {
            if ($m->getUser() && $m->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
