<?php

namespace App\Http\User\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Form\SettingsType;
use App\Domain\Auth\Service\UserDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route("/user", name: "user_")]
final class SettingsController extends AbstractController
{
    #[Route("/settings", name: "settings_index", methods: ["GET", "POST"])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        LocaleSwitcher $localeSwitcher,
    ): Response {
        $user = $this->currentUser();

        $form = $this->createForm(SettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            // Translate the confirmation in the freshly chosen language.
            $localeSwitcher->setLocale($user->getLocale());
            $this->addFlash("success", $translator->trans("settings.flash.saved"));

            return $this->redirectToRoute("user_settings_index");
        }

        return $this->render("user/settings/index.html.twig", [
            "form" => $form,
        ]);
    }

    #[Route("/delete-account", name: "delete_account", methods: ["POST"])]
    public function deleteAccount(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserDeletionService $deletionService,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $user = $this->currentUser();

        if (!$this->isCsrfTokenValid("delete_account", (string) $request->request->get("_csrf_token"))) {
            $this->addFlash("error", "Token de sécurité invalide.");

            return $this->redirectToRoute("user_settings_index");
        }

        $password = (string) $request->request->get("password", "");
        if (!$password || !$passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash("error", "Mot de passe incorrect.");

            return $this->redirectToRoute("user_settings_index");
        }

        $deletionService->deleteUser($user);

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute("home", ["_locale" => "fr"]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
