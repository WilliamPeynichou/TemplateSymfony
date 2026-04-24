<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Callup;
use App\Entity\Fixture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Callup>
 */
class CallupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Callup::class);
    }

    public function findByFixture(Fixture $fixture): ?Callup
    {
        return $this->findOneBy(['fixture' => $fixture]);
    }
}
