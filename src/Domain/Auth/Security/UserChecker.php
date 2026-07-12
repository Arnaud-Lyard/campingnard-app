<?php

namespace App\Domain\Auth\Security;

use App\Domain\Auth\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if ($user->isVerified() === false) {
            $message = match ($user->getLocale()) {
                'en' => 'Your account has not been verified. Please check your inbox.',
                default => 'Votre compte n\'est pas encore vérifié. Veuillez consulter votre boîte mail.',
            };
            $exception = new CustomUserMessageAccountStatusException($message);
            $exception->setUser($user);
            throw $exception;
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}
