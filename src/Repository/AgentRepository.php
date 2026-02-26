<?php

namespace App\Repository;

use App\Entity\Agent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    public function findByNormalizedName(string $name): ?Agent
    {
        return $this->findOneBy(['name' => strtolower(trim($name))]);
    }

    /**
     * @return list<Agent>
     */
    public function findForDashboard(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
