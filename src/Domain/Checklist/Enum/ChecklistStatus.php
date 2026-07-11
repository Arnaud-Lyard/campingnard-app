<?php

namespace App\Domain\Checklist\Enum;

enum ChecklistStatus: string
{
    case InProgress = "In progress";
    case Completed = "Completed";

    public function key(): string
    {
        return match ($this) {
            self::InProgress => "in_progress",
            self::Completed => "complete",
        };
    }

    public static function fromKey(string $key): self
    {
        return match ($key) {
            "in_progress" => self::InProgress,
            "complete" => self::Completed,
            default => throw new \ValueError(sprintf('Unknown checklist status key "%s".', $key)),
        };
    }
}
