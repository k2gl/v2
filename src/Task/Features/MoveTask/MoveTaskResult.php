<?php

namespace App\Task\Features\MoveTask;

use DateTimeImmutable;

readonly class MoveTaskResult
{
    public function __construct(
        public int $taskId,
        public string $title,
        public string $previousStatus,
        public string $newStatus,
        public ?DateTimeImmutable $updatedAt
    ) {}
}
