<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * @return Organization[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.memberships', 'm')
            ->where('o.owner = :u OR m.user = :u')
            ->setParameter('u', $user)
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
