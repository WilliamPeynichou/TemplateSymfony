<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CallupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CallupRepository::class)]
#[ORM\Table(name: 'callup')]
class Callup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'callup')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Fixture $fixture = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'callup', targetEntity: CallupPlayer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $callupPlayers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->callupPlayers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getFixture(): ?Fixture { return $this->fixture; }

    public function setFixture(?Fixture $fixture): static
    {
        $this->fixture = $fixture;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /** @return Collection<int, CallupPlayer> */
    public function getCallupPlayers(): Collection { return $this->callupPlayers; }

    public function addCallupPlayer(CallupPlayer $cp): static
    {
        if (!$this->callupPlayers->contains($cp)) {
            $this->callupPlayers->add($cp);
            $cp->setCallup($this);
        }
        return $this;
    }

    public function removeCallupPlayer(CallupPlayer $cp): static
    {
        if ($this->callupPlayers->removeElement($cp)) {
            if ($cp->getCallup() === $this) {
                $cp->setCallup(null);
            }
        }
        return $this;
    }

    /** @return CallupPlayer[] */
    public function getByRole(string $role): array
    {
        return $this->callupPlayers->filter(
            fn (CallupPlayer $cp) => $cp->getRole() === $role
        )->toArray();
    }

    public function getStarters(): array    { return $this->getByRole(CallupPlayer::ROLE_STARTER); }
    public function getSubstitutes(): array { return $this->getByRole(CallupPlayer::ROLE_SUBSTITUTE); }
    public function getNotCalled(): array   { return $this->getByRole(CallupPlayer::ROLE_NOT_CALLED); }
    public function getAbsent(): array      { return $this->getByRole(CallupPlayer::ROLE_ABSENT); }
}
