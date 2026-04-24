<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FormationSlotRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Emplacement dans une formation (poste, rôle, devoir, joueur attribué).
 */
#[ORM\Entity(repositoryClass: FormationSlotRepository::class)]
#[ORM\Table(name: 'formation_slot')]
class FormationSlot
{
    public const DUTY_DEFEND  = 'defend';
    public const DUTY_SUPPORT = 'support';
    public const DUTY_ATTACK  = 'attack';

    public const DUTIES = [
        'Défensif' => self::DUTY_DEFEND,
        'Soutien'  => self::DUTY_SUPPORT,
        'Offensif' => self::DUTY_ATTACK,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TacticalStrategy $strategy = null;

    #[ORM\Column(type: 'smallint')]
    private int $slotIndex = 0;

    #[ORM\Column(length: 10)]
    private string $positionCode = 'CM'; // GK/CB/LB/…/ST

    #[ORM\Column(length: 40)]
    private string $label = 'Slot';

    #[ORM\Column(length: 50)]
    private string $role = 'box_to_box';

    #[ORM\Column(length: 20)]
    private string $duty = self::DUTY_SUPPORT;

    #[ORM\Column(type: 'float')]
    private float $posX = 50.0;

    #[ORM\Column(type: 'float')]
    private float $posY = 50.0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Player $player = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $individualInstructions = null;

    public function getId(): ?int { return $this->id; }

    public function getStrategy(): ?TacticalStrategy { return $this->strategy; }
    public function setStrategy(?TacticalStrategy $s): static { $this->strategy = $s; return $this; }

    public function getSlotIndex(): int { return $this->slotIndex; }
    public function setSlotIndex(int $v): static { $this->slotIndex = $v; return $this; }

    public function getPositionCode(): string { return $this->positionCode; }
    public function setPositionCode(string $v): static { $this->positionCode = $v; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $v): static { $this->role = $v; return $this; }

    public function getDuty(): string { return $this->duty; }
    public function setDuty(string $v): static
    {
        if (!\in_array($v, self::DUTIES, true)) {
            throw new \InvalidArgumentException('Devoir invalide.');
        }
        $this->duty = $v;
        return $this;
    }

    public function getDutyLabel(): string { return array_search($this->duty, self::DUTIES, true) ?: $this->duty; }

    public function getPosX(): float { return $this->posX; }
    public function setPosX(float $v): static { $this->posX = $v; return $this; }

    public function getPosY(): float { return $this->posY; }
    public function setPosY(float $v): static { $this->posY = $v; return $this; }

    public function getPlayer(): ?Player { return $this->player; }
    public function setPlayer(?Player $p): static { $this->player = $p; return $this; }

    public function getIndividualInstructions(): ?string { return $this->individualInstructions; }
    public function setIndividualInstructions(?string $v): static { $this->individualInstructions = $v; return $this; }

    public function getPositionGroup(): string
    {
        return match ($this->positionCode) {
            'GK' => 'GK',
            'CB', 'LB', 'RB' => 'DEF',
            'CDM', 'CM', 'CAM' => 'MID',
            'LW', 'RW', 'ST' => 'ATT',
            default => 'MID',
        };
    }
}
