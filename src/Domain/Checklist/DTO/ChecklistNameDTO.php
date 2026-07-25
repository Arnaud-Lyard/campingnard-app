<?php

namespace App\Domain\Checklist\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChecklistNameDTO
{
  public function __construct(
    #[Assert\NotNull]
    #[Assert\Type('string')]
    public readonly string $name,

  ) {}
}
