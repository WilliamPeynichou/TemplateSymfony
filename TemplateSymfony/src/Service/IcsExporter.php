<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Fixture;
use App\Entity\TrainingSession;

/**
 * Générateur de calendrier iCalendar (RFC 5545).
 *
 * On implémente à la main pour éviter une dépendance externe sur un MVP.
 * Les règles respectées : CRLF, folding désactivé (lignes courtes), timezone UTC.
 */
final class IcsExporter
{
    private const PRODID = '-//Andfield//FR-Coach//FR';

    /**
     * @param iterable<Fixture>         $fixtures
     * @param iterable<TrainingSession> $trainings
     */
    public function export(iterable $fixtures, iterable $trainings, string $calendarName = 'Andfield'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($calendarName),
        ];

        foreach ($fixtures as $f) {
            $lines = array_merge($lines, $this->fixtureEvent($f));
        }
        foreach ($trainings as $t) {
            $lines = array_merge($lines, $this->trainingEvent($t));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return string[]
     */
    private function fixtureEvent(Fixture $f): array
    {
        $start = $f->getMatchDate()->setTime(15, 0);
        $end = $start->modify('+2 hours');

        return [
            'BEGIN:VEVENT',
            'UID:fixture-'.$f->getId().'@andfield',
            'DTSTAMP:'.$this->utc(new \DateTimeImmutable()),
            'DTSTART:'.$this->utc($start),
            'DTEND:'.$this->utc($end),
            'SUMMARY:Match vs '.$this->escape($f->getOpponent()),
            'DESCRIPTION:'.$this->escape(sprintf(
                'Compétition: %s. Venue: %s.',
                $f->getCompetition() ?? 'Amical',
                $f->getVenue(),
            )),
            'END:VEVENT',
        ];
    }

    /**
     * @return string[]
     */
    private function trainingEvent(TrainingSession $t): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:training-'.$t->getId().'@andfield',
            'DTSTAMP:'.$this->utc(new \DateTimeImmutable()),
            'DTSTART:'.$this->utc($t->getStartsAt()),
            'DTEND:'.$this->utc($t->getEndsAt()),
            'SUMMARY:'.$this->escape('Entraînement - '.$t->getTitle()),
            'LOCATION:'.$this->escape((string) $t->getLocation()),
            'DESCRIPTION:'.$this->escape((string) ($t->getFocus() ?? '')),
            'END:VEVENT',
        ];
    }

    private function utc(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private function escape(string $s): string
    {
        return str_replace(
            ["\\", ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $s,
        );
    }
}
