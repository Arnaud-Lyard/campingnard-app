<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Event\UserCreatedEvent;
use App\Domain\Auth\Repository\UserRepository;
use App\Domain\Auth\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Public auth endpoints for the mobile app: account creation and password-reset
 * requests. Mirrors the web RegistrationController / ResetPasswordController but
 * stateless (JSON, no CSRF). Email verification and the actual reset form are
 * completed via the signed links emailed to the user (handled by the web app).
 *
 * Routes are mounted under /api by the `api_controllers` resource in routes.yaml.
 */
final class AuthApiController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    #[Route("/register", name: "api_register", methods: ["POST"])]
    public function register(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EmailVerifier $emailVerifier,
    ): JsonResponse {
        $payload = $request->getPayload();
        $email = strtolower(trim((string) $payload->get("email", "")));
        $password = (string) $payload->get("password", "");
        $locale = (string) $payload->get("locale", "fr");
        if (!\in_array($locale, User::SUPPORTED_LOCALES, true)) {
            $locale = "fr";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ["error" => "invalid_email"],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return $this->json(
                ["error" => "password_too_short"],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if ($users->findOneBy(["email" => $email])) {
            return $this->json(
                ["error" => "email_taken"],
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User()
            ->setEmail($email)
            ->setRoles(["ROLE_USER"])
            ->setLocale($locale);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $this->dispatcher->dispatch(new UserCreatedEvent($user, $locale));

        return $this->json(["ok" => true], Response::HTTP_CREATED);
    }

    #[Route("/forgot-password", name: "api_forgot_password", methods: ["POST"])]
    public function forgotPassword(
        Request $request,
        UserRepository $users,
        ResetPasswordHelperInterface $resetHelper,
        MailerInterface $mailer,
    ): JsonResponse {
        $email = strtolower(
            trim((string) $request->getPayload()->get("email", "")),
        );
        $user = $email ? $users->findOneBy(["email" => $email]) : null;

        // Always return 200 — never reveal whether the account exists.
        if ($user) {
            try {
                $resetToken = $resetHelper->generateResetToken($user);
                $mailer->send(
                    new TemplatedEmail()
                        ->from(new Address("send@campingnard.fr", "Campingnard"))
                        ->to((string) $user->getEmail())
                        ->subject(
                            $this->translator->trans("email.reset.subject"),
                        )
                        ->locale($user->getLocale())
                        ->htmlTemplate("reset_password/email.html.twig")
                        ->context(["resetToken" => $resetToken]),
                );
            } catch (ResetPasswordExceptionInterface) {
                // Swallow — e.g. a reset was already requested recently.
            }
        }

        return $this->json(["ok" => true]);
    }
}
