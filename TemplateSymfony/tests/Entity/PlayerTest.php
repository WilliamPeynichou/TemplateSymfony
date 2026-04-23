<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Player;
use App\Entity\PlayerStatusHistory;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testDefaultsOnConstruction(): void
    {
        $player = new Player();

        self::assertSame(Player::STATUS_PRESENT, $player->getStatus());
        self::assertSame('Présent', $player->getStatusLabel());
        self::assertNull($player->getStatusReason());
    }

    public function testPresentStatusClearsReason(): void
    {
        $player = (new Player())
            ->setStatus(Player::STATUS_ABSENT)
            ->setStatusReason('Absence personnelle');

        $player->setStatus(Player::STATUS_PRESENT);

        self::assertNull($player->getStatusReason());
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Player())->setStatus('unknown');
    }

    public function testStatusHistoryLabels(): void
    {
        $history = (new PlayerStatusHistory())
            ->setOldStatus(Player::STATUS_PRESENT)
            ->setNewStatus(Player::STATUS_INJURED);

        self::assertSame('Présent', $history->getOldStatusLabel());
        self::assertSame('Blessé', $history->getNewStatusLabel());
    }
}
