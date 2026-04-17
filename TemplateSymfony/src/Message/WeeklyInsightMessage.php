<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message asynchrone : "générer le briefing hebdomadaire pour ce coach".
 */
final class WeeklyInsightMessage
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
