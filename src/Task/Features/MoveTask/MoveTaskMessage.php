<?php

namespace App\Task\Features\MoveTask;

use App\Task\Domain\ValueObject\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(description: "Request to move task between columns")]
final readonly class MoveTaskMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[OA\Property(description: "Task identifier", example: 1)]
        public int $taskId,

        #[Assert\NotNull]
        #[OA\Property(
            description: "New task status",
            example: "todo",
            type: "string",
            enum: ["backlog", "todo", "in_progress", "done"]
        )]
        public TaskStatus $newStatus
    ) {}
}
