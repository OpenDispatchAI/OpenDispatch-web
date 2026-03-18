<?php

namespace App\Repository;

use App\Entity\Skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Skill>
 */
class SkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Skill::class);
    }

    /**
     * @return Skill[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.publishedAt IS NOT NULL')
            ->orderBy('s.skillId', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
