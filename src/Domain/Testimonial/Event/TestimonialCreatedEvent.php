<?php

namespace App\Domain\Testimonial\Event;

use App\Domain\Auth\Entity\User;
use App\Domain\Testimonial\Entity\Testimonial;

class TestimonialCreatedEvent
{
    public function __construct(
        private readonly Testimonial $testimonial,
        private readonly string $token,
        private readonly User $user,
        private readonly string $name,
    ) {}

    public function getTestimonial(): Testimonial
    {
        return $this->testimonial;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
