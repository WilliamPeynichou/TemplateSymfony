<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Attendance;
use App\Entity\Fixture;
use App\Entity\TrainingSession;
use PHPUnit\Framework\TestCase;

final class AttendanceTest extends TestCase
{
    public function testDefaultsOnConstruction(): void
    {
        $attendance = new Attendance();

        self::assertSame(Attendance::STATUS_PRESENT, $attendance->getStatus());
        self::assertSame('Présent', $attendance->getStatusLabel());
        self::assertNull($attendance->getReason());
    }

    public function testPresentStatusClearsReason(): void
    {
        $attendance = (new Attendance())
            ->setStatus(Attendance::STATUS_ABSENT)
            ->setReason('Maladie');

        $attendance->setStatus(Attendance::STATUS_PRESENT);

        self::assertNull($attendance->getReason());
    }

    public function testEventAssignmentIsExclusive(): void
    {
        $fixture = new Fixture();
        $training = new TrainingSession();

        $attendance = (new Attendance())->setFixture($fixture);
        self::assertSame($fixture, $attendance->getFixture());
        self::assertNull($attendance->getTrainingSession());

        $attendance->setTrainingSession($training);
        self::assertSame($training, $attendance->getTrainingSession());
        self::assertNull($attendance->getFixture());
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Attendance())->setStatus('unknown');
    }
}
