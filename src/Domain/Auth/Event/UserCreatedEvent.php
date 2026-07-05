<?php

namespace App\Domain\Auth\Event;

use App\Domain\Auth\Entity\User;

class UserCreatedEvent
{
    public function __construct(
        private readonly User $user,
        private readonly string $locale,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
