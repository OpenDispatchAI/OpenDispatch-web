<?php

declare(strict_types=1);

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
    public function search(string $query = '', string $tag = '', string $sort = 'name'): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($query !== '') {
            $qb->andWhere('LOWER(s.name) LIKE LOWER(:query) OR LOWER(s.description) LIKE LOWER(:query)')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($tag !== '') {
            $qb->andWhere('s.tags LIKE :tag')
               ->setParameter('tag', '%"' . $tag . '"%');
        }

        match ($sort) {
            'popular' => $qb
                ->leftJoin('s.downloads', 'd')
                ->groupBy('s.id')
                ->orderBy('COUNT(d.id)', 'DESC'),
            'newest' => $qb->orderBy('s.createdAt', 'DESC'),
            default => $qb->orderBy('s.name', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[] Unique tags across all skills, sorted by frequency (most common first)
     */
    public function findAllTags(): array
    {
        $skills = $this->findAll();
        $tags = [];
        foreach ($skills as $skill) {
            foreach ($skill->getTags() as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }
        arsort($tags);

        return array_keys($tags);
    }
}
