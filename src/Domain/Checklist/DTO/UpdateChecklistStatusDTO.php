<?php

namespace App\Domain\Checklist\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateChecklistStatusDTO
{
  public function __construct(
    #[Assert\NotNull]
    #[Assert\Type('string')]
    public readonly string $status,

    #[Assert\NotNull]
    #[Assert\Type('array')]
    public readonly array $ids,

  ) {}
}
