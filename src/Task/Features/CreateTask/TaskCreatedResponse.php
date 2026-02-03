<?php

namespace App\Task\Features\CreateTask;

use App\Task\Entity\Task;
use App\Task\Domain\ValueObject\TaskStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: "Task creation response")]
final readonly class TaskCreatedResponse
{
    public function __construct(
        #[OA\Property(description: "Task ID", example: 1)]
        public int $id,

        #[OA\Property(description: "Task UUID", example: "550e8400-e29b-41d4-a716-446655440000")]
        public string $uuid,

        #[OA\Property(description: "Task title")]
        public string $title,

        #[OA\Property(description: "Task description", nullable: true)]
        public ?string $description,

        #[OA\Property(description: "Column ID")]
        public int $columnId,

        #[OA\Property(description: "Task status", enum: TaskStatus::class)]
        public string $status,

        #[OA\Property(description: "Task position (fractional indexing)")]
        public string $position,

        #[OA\Property(description: "Creation timestamp")]
        public string $createdAt
    ) {}

    public static function fromEntity(Task $task): self
    {
        return new self(
            id: $task->getId(),
            uuid: $task->getUuid(),
            title: $task->getTitle(),
            description: $task->getDescription(),
            columnId: $task->getColumn()->getId(),
            status: $task->getStatus()->value,
            position: $task->getPosition(),
            createdAt: $task->getCreatedAt()->format('c')
        );
    }
}
