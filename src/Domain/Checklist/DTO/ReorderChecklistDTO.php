<?php

namespace App\Domain\Checklist\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ReorderChecklistDTO
{
  public function __construct(
    #[Assert\NotNull]
    #[Assert\Type('array')]
    public readonly array $ids,

  ) {}
}
