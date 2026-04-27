<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MaterialStock;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MaterialStock>
 */
class MaterialStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaterialStock::class);
    }

    /**
     * Somme des quantités sur toutes les équipes du coach (lignes avec équipe uniquement).
     */
    public function sumQuantityAllTeamsForCoach(User $coach): int
    {
        $r = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.quantity), 0)')
            ->join('m.team', 't')
            ->where('t.coach = :coach')
            ->setParameter('coach', $coach)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $r;
    }

    /**
     * Nombre total de lignes de stock sur les équipes du coach.
     */
    public function countLinesAllTeamsForCoach(User $coach): int
    {
        $r = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.team', 't')
            ->where('t.coach = :coach')
            ->setParameter('coach', $coach)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $r;
    }

    /**
     * @return list<array{teamId: string|int, qty: int, lines: int}>
     */
    public function aggregateTotalsByTeamForCoach(User $coach): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('t.id AS teamId', 'COALESCE(SUM(m.quantity), 0) AS qty', 'COUNT(m.id) AS lines')
            ->join('m.team', 't')
            ->where('t.coach = :coach')
            ->setParameter('coach', $coach)
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'teamId' => $row['teamId'],
                'qty'    => (int) $row['qty'],
                'lines'  => (int) $row['lines'],
            ];
        }

        return $out;
    }

    /**
     * @return MaterialStock[]
     */
    public function findForTeam(Team $team): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumQuantityForTeam(Team $team): int
    {
        $r = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.quantity), 0)')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $r;
    }
}
