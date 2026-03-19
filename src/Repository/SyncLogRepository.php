<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SyncLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncLog>
 */
class SyncLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncLog::class);
    }

    /**
     * @return SyncLog[]
     */
    public function findLatest(int $limit = 20): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.syncedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
