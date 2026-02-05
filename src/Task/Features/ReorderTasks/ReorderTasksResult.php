<?php

namespace App\Task\Features\ReorderTasks;

readonly class ReorderTasksResult
{
    public function __construct(
        public int $taskId,
        public float $newPosition,
        public string $strategy
    ) {}
}
