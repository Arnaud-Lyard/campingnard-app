<?php

namespace App\Domain\Auth\DTO;

use App\Domain\Auth\Enum\LocaleEnum;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterDTO
{
  public function __construct(
    #[Assert\NotNull]
    #[Assert\Length(max: 180)]
    public readonly string $email,

    #[Assert\NotNull]
    #[Assert\Length(max: 128)]
    public readonly string $password,

    #[Assert\NotNull]
    #[Assert\Type(LocaleEnum::class)]
    public readonly LocaleEnum $locale,

  ) {}
}
