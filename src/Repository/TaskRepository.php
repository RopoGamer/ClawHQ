<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findOneByAgentAndExternalId(Agent $agent, string $externalId): ?Task
    {
        return $this->findOneBy([
            'agent' => $agent,
            'externalId' => trim($externalId),
        ]);
    }

    /**
     * @return list<Task>
     */
    public function findByAgentAndStatus(Agent $agent, TaskStatus $status): array
    {
        return $this->findBy(
            ['agent' => $agent, 'status' => $status],
            ['updatedAt' => 'DESC', 'createdAt' => 'DESC']
        );
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(Agent $agent): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.status AS status, COUNT(t.id) AS amount')
            ->where('t.agent = :agent')
            ->setParameter('agent', $agent)
            ->groupBy('t.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            TaskStatus::TODO->value => 0,
            TaskStatus::DOING->value => 0,
            TaskStatus::DONE->value => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'];
            if ($status instanceof TaskStatus) {
                $status = $status->value;
            }

            if (array_key_exists((string) $status, $counts)) {
                $counts[(string) $status] = (int) $row['amount'];
            }
        }

        return $counts;
    }
}
