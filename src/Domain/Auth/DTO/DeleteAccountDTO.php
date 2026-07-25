<?php

namespace App\Domain\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DeleteAccountDTO
{
  public function __construct(
    #[Assert\NotBlank]
    public readonly string $password,

  ) {}
}
