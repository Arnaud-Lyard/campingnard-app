<?php

namespace App\Domain\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordDTO
{
  public function __construct(
    #[Assert\NotNull]
    #[Assert\Length(max: 180)]
    public readonly string $email,

  ) {}
}
