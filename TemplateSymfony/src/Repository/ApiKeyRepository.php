<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function findValidByHash(string $hash): ?ApiKey
    {
        return $this->createQueryBuilder('k')
            ->where('k.hash = :h')
            ->andWhere('k.revoked = false')
            ->setParameter('h', $hash)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
