<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SkillManifest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SkillManifest>
 */
class SkillManifestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkillManifest::class);
    }

    public function findLatest(): ?SkillManifest
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function pruneOldManifests(int $keep = 50): int
    {
        $latest = $this->createQueryBuilder('m')
            ->select('m.id')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($keep)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($latest)) {
            return 0;
        }

        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.id NOT IN (:ids)')
            ->setParameter('ids', $latest)
            ->getQuery()
            ->execute();
    }
}
