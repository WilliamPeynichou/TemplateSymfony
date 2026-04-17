<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubscriptionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPlan>
 */
class SubscriptionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlan::class);
    }

    public function findOneBySlug(string $slug): ?SubscriptionPlan
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return SubscriptionPlan[]
     */
    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }
}
