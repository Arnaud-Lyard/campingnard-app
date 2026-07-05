<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController extends AbstractController
{
    /**
     * Returns the authenticated user's profile. Used by the mobile app after
     * login and on startup to validate the stored JWT.
     */
    #[Route("/me", name: "api_me", methods: ["GET"])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            "id" => $user->getId(),
            "email" => $user->getEmail(),
            "roles" => $user->getRoles(),
            "locale" => $user->getLocale(),
            "isVerified" => $user->isVerified(),
        ]);
    }
}
