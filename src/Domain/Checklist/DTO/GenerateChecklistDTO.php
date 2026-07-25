<?php

namespace App\Domain\Checklist\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class GenerateChecklistDTO
{
  public function __construct(
    #[Assert\Type('string')]
    public readonly string $locale,

  ) {}
}
