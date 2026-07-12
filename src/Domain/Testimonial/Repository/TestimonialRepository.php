<?php

namespace App\Domain\Testimonial\Repository;

use App\Domain\Testimonial\Entity\Testimonial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Testimonial>
 */
class TestimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Testimonial::class);
    }

    /**
     * @return Testimonial[]
     */
    public function findLastActive(int $limit = 11): array
    {
        return $this->createQueryBuilder("t")
            ->andWhere("t.isActive = :active")
            ->setParameter("active", true)
            ->orderBy("t.createdAt", "DESC")
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
