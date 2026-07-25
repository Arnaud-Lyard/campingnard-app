<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\DTO\ForgotPasswordDTO;
use App\Domain\Auth\DTO\RegisterDTO;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

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
        #[MapRequestPayload] RegisterDTO $registerDatas,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EmailVerifier $emailVerifier,
    ): JsonResponse {

        $email = strtolower(trim($registerDatas->email));
        $password = trim($registerDatas->password);
        $locale = $registerDatas->locale;

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

        $this->dispatcher->dispatch(new UserCreatedEvent($user, $locale->value));

        return $this->json(["ok" => true], Response::HTTP_CREATED);
    }

    #[Route("/forgot-password", name: "api_forgot_password", methods: ["POST"])]
    public function forgotPassword(
        #[MapRequestPayload] ForgotPasswordDTO $forgotPasswordDatas,
        UserRepository $users,
        ResetPasswordHelperInterface $resetHelper,
        MailerInterface $mailer,
    ): JsonResponse {

        $email = strtolower(trim((string) $forgotPasswordDatas->email),);
        $user = $users->findOneBy(["email" => $email]);

        // Always return 200 — never reveal whether the account exists.
        if ($user) {
            $this->dispatcher->dispatch(new UserCreatedEvent($user, $user->getLocale()->value));
        }

        return $this->json(["ok" => true]);
    }
}
