<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TrainingSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingSession>
 */
class TrainingSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingSession::class);
    }

    /**
     * @return TrainingSession[]
     */
    public function findByTeamOrderedByDate(Team $team): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :t')
            ->setParameter('t', $team)
            ->orderBy('s.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TrainingSession[]
     */
    public function findUpcomingForTeam(Team $team, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :t')
            ->andWhere('s.startsAt >= :now')
            ->setParameter('t', $team)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
