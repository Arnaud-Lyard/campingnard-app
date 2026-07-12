<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Service\UserDeletionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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

    /**
     * Supprime le compte de l'utilisateur authentifié et toutes ses ressources.
     * Le mot de passe doit être confirmé dans le corps de la requête.
     * L'app mobile doit supprimer le JWT stocké localement après succès.
     */
    #[Route("/me", name: "api_delete_me", methods: ["DELETE"])]
    public function deleteMe(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserDeletionService $deletionService,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $password = (string) $request->getPayload()->get("password", "");
        if (!$password || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(["error" => "invalid_password"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $deletionService->deleteUser($user);

        return $this->json(["ok" => true]);
    }
}
