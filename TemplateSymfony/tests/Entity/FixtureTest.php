<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Fixture;
use PHPUnit\Framework\TestCase;

final class FixtureTest extends TestCase
{
    public function testDefaultsOnConstruction(): void
    {
        $fixture = new Fixture();

        self::assertSame(Fixture::STATUS_SCHEDULED, $fixture->getStatus());
        self::assertSame(Fixture::HOME, $fixture->getVenue());
        self::assertNull($fixture->getScoreFor());
        self::assertNull($fixture->getScoreAgainst());
        self::assertNull($fixture->getResult());
    }

    public function testResultIsWinWhenScoreForHigher(): void
    {
        $fixture = new Fixture();
        $fixture->setScoreFor(3)->setScoreAgainst(1);

        self::assertSame('win', $fixture->getResult());
    }

    public function testResultIsLossWhenScoreAgainstHigher(): void
    {
        $fixture = new Fixture();
        $fixture->setScoreFor(0)->setScoreAgainst(2);

        self::assertSame('loss', $fixture->getResult());
    }

    public function testResultIsDrawWhenScoresEqual(): void
    {
        $fixture = new Fixture();
        $fixture->setScoreFor(1)->setScoreAgainst(1);

        self::assertSame('draw', $fixture->getResult());
    }

    public function testInvalidVenueThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Fixture())->setVenue('stadium');
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Fixture())->setStatus('unknown');
    }

    public function testSettersAreFluent(): void
    {
        $fixture = new Fixture();
        $same = $fixture
            ->setOpponent('Liverpool')
            ->setCompetition('Ligue 1')
            ->setVenue(Fixture::AWAY)
            ->setStatus(Fixture::STATUS_PLAYED);

        self::assertSame($fixture, $same);
        self::assertSame('Liverpool', $fixture->getOpponent());
        self::assertSame(Fixture::AWAY, $fixture->getVenue());
    }
}
