<?php

namespace App\Http\Public\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Event\UserCreatedEvent;
use App\Domain\Auth\Form\RegistrationFormType;
use App\Domain\Auth\Repository\UserRepository;
use App\Domain\Auth\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    #[Route("/register", name: "app_register")]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get("plainPassword")->getData();

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword),
            );
            $user->setRoles(["ROLE_USER"]);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->dispatcher->dispatch(
                new UserCreatedEvent($user, $user->getLocale()),
            );

            return $this->redirectToRoute("app_login");
        }

        return $this->render("registration/register.html.twig", [
            "registrationForm" => $form,
        ]);
    }

    #[Route("/verify/email", name: "app_verify_email")]
    public function verifyUserEmail(
        Request $request,
        UserRepository $userRepository,
        TranslatorInterface $translator,
    ): Response {
        // validation anonyme : l'utilisateur n'a pas besoin d'être connecté.
        $id = $request->query->get("id");
        if (null === $id) {
            return $this->redirectToRoute("app_register");
        }

        $user = $userRepository->find($id);
        if (null === $user) {
            return $this->redirectToRoute("app_register");
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                "verify_email_error",
                $translator->trans(
                    $exception->getReason(),
                    [],
                    "VerifyEmailBundle",
                ),
            );

            return $this->redirectToRoute("app_register");
        }

        $this->addFlash("success", $translator->trans("flash.email_verified"));

        // l'email étant vérifié, on envoie l'utilisateur se connecter
        return $this->redirectToRoute("app_login");
    }
}
