<?php

namespace App\Domain\Auth\Service;

use App\Domain\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Supprime un utilisateur et toutes ses ressources associées.
     * L'ordre respecte les contraintes FK : ResetPasswordRequests → Battery → Checklists → User.
     * DQL est utilisé pour Battery afin de contourner le cascade remove défini côté Battery.owner.
     */
    public function deleteUser(User $user): void
    {
        $this->em
            ->createQuery("DELETE FROM App\Domain\Auth\Entity\ResetPasswordRequest r WHERE r.user = :user")
            ->setParameter("user", $user)
            ->execute();

        $this->em
            ->createQuery("DELETE FROM App\Domain\Equipment\Entity\Battery b WHERE b.owner = :user")
            ->setParameter("user", $user)
            ->execute();

        $this->em
            ->createQuery("DELETE FROM App\Domain\Checklist\Entity\Checklist c WHERE c.owner = :user")
            ->setParameter("user", $user)
            ->execute();

        $this->em->remove($user);
        $this->em->flush();
    }
}
