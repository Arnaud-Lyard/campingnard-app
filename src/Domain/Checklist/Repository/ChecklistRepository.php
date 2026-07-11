<?php

namespace App\Domain\Checklist\Repository;

use App\Domain\Auth\Entity\User;
use App\Domain\Checklist\Entity\Checklist;
use App\Domain\Checklist\Enum\ChecklistStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checklist>
 */
class ChecklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checklist::class);
    }

    /**
     * @return Checklist[]
     */
    public function findOrdered(User $owner, ?ChecklistStatus $status = null): array
    {
        $qb = $this->createQueryBuilder("c")
            ->andWhere("c.owner = :owner")
            ->setParameter("owner", $owner)
            ->orderBy("c.ordre", "ASC")
            ->addOrderBy("c.createdAt", "DESC");

        if (null !== $status) {
            $qb->andWhere("c.status = :status")->setParameter("status", $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function shiftDown(User $owner, int $by = 1): void
    {
        $this->getEntityManager()
            ->createQueryBuilder()
            ->update(Checklist::class, "c")
            ->set("c.ordre", "c.ordre + :by")
            ->where("c.owner = :owner")
            ->setParameter("by", $by)
            ->setParameter("owner", $owner)
            ->getQuery()
            ->execute();
    }
}
