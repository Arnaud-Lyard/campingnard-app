<?php

namespace App\Domain\Equipment\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AddBatteryDTO
{
  public function __construct(
    #[Assert\NotNull]
    #[Assert\Type('bool')]
    public readonly bool $isActive,

    #[Assert\NotNull]
    #[Assert\Type('int')]
    public readonly int $frequency,

  ) {}
}
