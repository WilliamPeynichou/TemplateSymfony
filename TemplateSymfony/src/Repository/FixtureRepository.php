<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fixture;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fixture>
 */
class FixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fixture::class);
    }

    /**
     * @return Fixture[]
     */
    public function findByTeamOrderedByDate(Team $team): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->setParameter('team', $team)
            ->orderBy('f.matchDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Fixture[]
     */
    public function findUpcomingForTeam(Team $team, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->andWhere('f.matchDate >= :now')
            ->andWhere('f.status = :scheduled')
            ->setParameter('team', $team)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('scheduled', Fixture::STATUS_SCHEDULED)
            ->orderBy('f.matchDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
