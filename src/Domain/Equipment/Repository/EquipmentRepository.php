<?php

namespace App\Domain\Equipment\Repository;

use App\Domain\Auth\Entity\User;
use App\Domain\Equipment\Entity\Equipment;
use App\Domain\Equipment\Enum\EquipmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipment>
 */
class EquipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipment::class);
    }

    /**
     * Items of the given owner, ordered with "in progress" first, "completed"
     * last, then by the manual order (ordre), then newest first as a tie-breaker.
     *
     * @return Equipment[]
     */
    public function findOrdered(User $owner, ?EquipmentStatus $status = null): array
    {
        $qb = $this->createQueryBuilder("e")
            ->addSelect("CASE WHEN e.status = :inProgress THEN 0 ELSE 1 END AS HIDDEN statusOrder")
            ->andWhere("e.owner = :owner")
            ->setParameter("owner", $owner)
            ->setParameter("inProgress", EquipmentStatus::InProgress)
            ->orderBy("statusOrder", "ASC")
            ->addOrderBy("e.ordre", "ASC")
            ->addOrderBy("e.createdAt", "DESC");

        if (null !== $status) {
            $qb->andWhere("e.status = :status")->setParameter("status", $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Smallest "ordre" among the owner's items so a new one floats to the top.
     */
    public function getTopOrdre(User $owner): int
    {
        $min = $this->createQueryBuilder("e")
            ->select("MIN(e.ordre)")
            ->andWhere("e.owner = :owner")
            ->setParameter("owner", $owner)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $min ? 0 : ((int) $min) - 1;
    }
}
