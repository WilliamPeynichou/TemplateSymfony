<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\Team;
use App\Entity\TrainingSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    /**
     * @return array<int, Attendance>
     */
    public function findIndexedForTraining(TrainingSession $trainingSession): array
    {
        return $this->indexByPlayer($this->findBy(['trainingSession' => $trainingSession]));
    }

    /**
     * @return array<int, Attendance>
     */
    public function findIndexedForFixture(Fixture $fixture): array
    {
        return $this->indexByPlayer($this->findBy(['fixture' => $fixture]));
    }

    public function findOneForTraining(Player $player, TrainingSession $trainingSession): ?Attendance
    {
        return $this->findOneBy(['player' => $player, 'trainingSession' => $trainingSession]);
    }

    public function findOneForFixture(Player $player, Fixture $fixture): ?Attendance
    {
        return $this->findOneBy(['player' => $player, 'fixture' => $fixture]);
    }

    /**
     * @return array<int, array{total:int,present:int,absent:int,excused:int,late:int,rate:int}>
     */
    public function getSummaryByPlayerForTeam(
        Team $team,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.player) AS player_id')
            ->addSelect('COUNT(a.id) AS total')
            ->addSelect('SUM(CASE WHEN a.status = :present OR a.status = :late THEN 1 ELSE 0 END) AS present')
            ->addSelect('SUM(CASE WHEN a.status = :absent THEN 1 ELSE 0 END) AS absent')
            ->addSelect('SUM(CASE WHEN a.status = :excused THEN 1 ELSE 0 END) AS excused')
            ->addSelect('SUM(CASE WHEN a.status = :late THEN 1 ELSE 0 END) AS late')
            ->join('a.player', 'p')
            ->andWhere('p.team = :team')
            ->setParameter('team', $team)
            ->setParameter('present', Attendance::STATUS_PRESENT)
            ->setParameter('absent', Attendance::STATUS_ABSENT)
            ->setParameter('excused', Attendance::STATUS_EXCUSED)
            ->setParameter('late', Attendance::STATUS_LATE)
            ->groupBy('a.player');

        $this->applyPeriodFilter($qb, $from, $to);

        $rows = $qb->getQuery()->getArrayResult();

        $summary = [];
        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $present = (int) $row['present'];
            $summary[(int) $row['player_id']] = [
                'total' => $total,
                'present' => $present,
                'absent' => (int) $row['absent'],
                'excused' => (int) $row['excused'],
                'late' => (int) $row['late'],
                'rate' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
            ];
        }

        return $summary;
    }

    /**
     * @return Attendance[]
     */
    public function findRecentForTeam(
        Team $team,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $limit = 50,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->join('a.player', 'p')
            ->leftJoin('a.fixture', 'f')
            ->leftJoin('a.trainingSession', 'ts')
            ->addSelect('p', 'f', 'ts')
            ->addSelect('(CASE WHEN f.matchDate IS NOT NULL THEN f.matchDate ELSE ts.startsAt END) AS HIDDEN eventDate')
            ->andWhere('p.team = :team')
            ->setParameter('team', $team)
            ->orderBy('eventDate', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        $this->applyPeriodFilter($qb, $from, $to);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Attendance[]
     */
    public function findRecentForPlayer(Player $player, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.player = :player')
            ->setParameter('player', $player)
            ->leftJoin('a.fixture', 'f')
            ->leftJoin('a.trainingSession', 'ts')
            ->addSelect('f', 'ts')
            ->addSelect('(CASE WHEN f.matchDate IS NOT NULL THEN f.matchDate ELSE ts.startsAt END) AS HIDDEN eventDate')
            ->orderBy('eventDate', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array{
     *     type:string,
     *     event: Fixture|TrainingSession,
     *     title:string,
     *     date:\DateTimeImmutable,
     *     savedAt:\DateTimeImmutable,
     *     entered:int,
     *     present:int,
     *     absent:int,
     *     excused:int,
     *     late:int
     * }>
     */
    public function findSavedSheetsForTeam(Team $team): array
    {
        $sheets = [
            ...$this->findSavedFixtureSheetsForTeam($team),
            ...$this->findSavedTrainingSheetsForTeam($team),
        ];

        usort($sheets, fn (array $left, array $right): int => $right['savedAt'] <=> $left['savedAt']);

        return $sheets;
    }

    /**
     * @param Attendance[] $attendances
     *
     * @return array<int, Attendance>
     */
    private function indexByPlayer(array $attendances): array
    {
        $indexed = [];
        foreach ($attendances as $attendance) {
            $player = $attendance->getPlayer();
            if (null !== $player && null !== $player->getId()) {
                $indexed[$player->getId()] = $attendance;
            }
        }

        return $indexed;
    }

    /**
     * @return array<int, array{
     *     type:string,
     *     event: Fixture,
     *     title:string,
     *     date:\DateTimeImmutable,
     *     savedAt:\DateTimeImmutable,
     *     entered:int,
     *     present:int,
     *     absent:int,
     *     excused:int,
     *     late:int
     * }>
     */
    private function findSavedFixtureSheetsForTeam(Team $team): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('f AS event')
            ->addSelect('COUNT(a.id) AS entered')
            ->addSelect('SUM(CASE WHEN a.status = :present OR a.status = :late THEN 1 ELSE 0 END) AS present')
            ->addSelect('SUM(CASE WHEN a.status = :absent THEN 1 ELSE 0 END) AS absent')
            ->addSelect('SUM(CASE WHEN a.status = :excused THEN 1 ELSE 0 END) AS excused')
            ->addSelect('SUM(CASE WHEN a.status = :late THEN 1 ELSE 0 END) AS late')
            ->addSelect('MAX(a.updatedAt) AS savedAt')
            ->from(Fixture::class, 'f')
            ->join(Attendance::class, 'a', 'WITH', 'a.fixture = f')
            ->join('a.player', 'p')
            ->andWhere('p.team = :team')
            ->setParameter('team', $team)
            ->setParameter('present', Attendance::STATUS_PRESENT)
            ->setParameter('absent', Attendance::STATUS_ABSENT)
            ->setParameter('excused', Attendance::STATUS_EXCUSED)
            ->setParameter('late', Attendance::STATUS_LATE)
            ->groupBy('f.id')
            ->getQuery()
            ->getResult();

        return array_map(function (array $row): array {
            /** @var Fixture $fixture */
            $fixture = $row['event'];

            return $this->normalizeSavedSheetRow(
                'Match',
                $fixture,
                'vs '.$fixture->getOpponent(),
                $fixture->getMatchDate(),
                $row,
            );
        }, $rows);
    }

    /**
     * @return array<int, array{
     *     type:string,
     *     event: TrainingSession,
     *     title:string,
     *     date:\DateTimeImmutable,
     *     savedAt:\DateTimeImmutable,
     *     entered:int,
     *     present:int,
     *     absent:int,
     *     excused:int,
     *     late:int
     * }>
     */
    private function findSavedTrainingSheetsForTeam(Team $team): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('ts AS event')
            ->addSelect('COUNT(a.id) AS entered')
            ->addSelect('SUM(CASE WHEN a.status = :present OR a.status = :late THEN 1 ELSE 0 END) AS present')
            ->addSelect('SUM(CASE WHEN a.status = :absent THEN 1 ELSE 0 END) AS absent')
            ->addSelect('SUM(CASE WHEN a.status = :excused THEN 1 ELSE 0 END) AS excused')
            ->addSelect('SUM(CASE WHEN a.status = :late THEN 1 ELSE 0 END) AS late')
            ->addSelect('MAX(a.updatedAt) AS savedAt')
            ->from(TrainingSession::class, 'ts')
            ->join(Attendance::class, 'a', 'WITH', 'a.trainingSession = ts')
            ->join('a.player', 'p')
            ->andWhere('p.team = :team')
            ->setParameter('team', $team)
            ->setParameter('present', Attendance::STATUS_PRESENT)
            ->setParameter('absent', Attendance::STATUS_ABSENT)
            ->setParameter('excused', Attendance::STATUS_EXCUSED)
            ->setParameter('late', Attendance::STATUS_LATE)
            ->groupBy('ts.id')
            ->getQuery()
            ->getResult();

        return array_map(function (array $row): array {
            /** @var TrainingSession $training */
            $training = $row['event'];

            return $this->normalizeSavedSheetRow(
                'Entraînement',
                $training,
                $training->getTitle(),
                $training->getStartsAt(),
                $row,
            );
        }, $rows);
    }

    /**
     * @param array{entered:mixed,present:mixed,absent:mixed,excused:mixed,late:mixed,savedAt:mixed} $row
     *
     * @return array{
     *     type:string,
     *     event: Fixture|TrainingSession,
     *     title:string,
     *     date:\DateTimeImmutable,
     *     savedAt:\DateTimeImmutable,
     *     entered:int,
     *     present:int,
     *     absent:int,
     *     excused:int,
     *     late:int
     * }
     */
    private function normalizeSavedSheetRow(
        string $type,
        Fixture|TrainingSession $event,
        string $title,
        \DateTimeImmutable $date,
        array $row,
    ): array {
        $savedAt = $row['savedAt'] instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($row['savedAt'])
            : new \DateTimeImmutable((string) $row['savedAt']);

        return [
            'type' => $type,
            'event' => $event,
            'title' => $title,
            'date' => $date,
            'savedAt' => $savedAt,
            'entered' => (int) $row['entered'],
            'present' => (int) $row['present'],
            'absent' => (int) $row['absent'],
            'excused' => (int) $row['excused'],
            'late' => (int) $row['late'],
        ];
    }

    private function applyPeriodFilter(
        \Doctrine\ORM\QueryBuilder $qb,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
    ): void {
        if ($from instanceof \DateTimeImmutable) {
            $qb
                ->andWhere('((f.matchDate IS NOT NULL AND f.matchDate >= :from) OR (ts.startsAt IS NOT NULL AND ts.startsAt >= :from))')
                ->setParameter('from', $from);
        }

        if ($to instanceof \DateTimeImmutable) {
            $qb
                ->andWhere('((f.matchDate IS NOT NULL AND f.matchDate <= :to) OR (ts.startsAt IS NOT NULL AND ts.startsAt <= :to))')
                ->setParameter('to', $to);
        }

        if (!\in_array('f', $qb->getAllAliases(), true)) {
            $qb->leftJoin('a.fixture', 'f');
        }

        if (!\in_array('ts', $qb->getAllAliases(), true)) {
            $qb->leftJoin('a.trainingSession', 'ts');
        }
    }
}
