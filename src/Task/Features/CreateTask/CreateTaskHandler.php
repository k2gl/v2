<?php

namespace App\Task\Features\CreateTask;

use App\Board\Entity\Column;
use App\Task\Entity\Task;
use App\Task\Entity\TaskRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsMessageHandler]
readonly class CreateTaskHandler
{
    private const FRACTIONAL_STEP = 1000.0;

    public function __construct(
        private EntityManagerInterface $em,
        private TaskRepository $taskRepository,
        private ?HubInterface $hub = null
    ) {}

    public function handle(CreateTaskMessage $message, User $owner): TaskCreatedResponse
    {
        $column = $this->findColumn($message->columnId);
        $maxPosition = $this->taskRepository->getMaxPosition($message->columnId);
        $newPosition = $this->calculateNewPosition($maxPosition);

        $task = new Task(
            title: $message->title,
            column: $column,
            owner: $owner,
            description: $message->description
        );

        $task->setPosition($newPosition);

        if (!empty($message->tags)) {
            $task->setMetadata(['tags' => $message->tags]);
        }

        $this->em->persist($task);
        $this->em->flush();

        $this->publishToMercure($column, $task);

        return TaskCreatedResponse::fromEntity($task);
    }

    private function findColumn(int $columnId): Column
    {
        $column = $this->em->find(Column::class, $columnId);

        if ($column === null) {
            throw new \DomainException("Column with ID {$columnId} not found");
        }

        return $column;
    }

    private function calculateNewPosition(float $maxPosition): string
    {
        $newPosition = $maxPosition + self::FRACTIONAL_STEP;
        return number_format($newPosition, 10, '.', '');
    }

    private function publishToMercure(Column $column, Task $task): void
    {
        if ($this->hub === null) {
            return;
        }

        $board = $column->getBoard();
        if ($board === null) {
            return;
        }

        $update = new Update(
            "https://your-kanban.com/board/{$board->getId()}",
            json_encode([
                'event' => 'task_created',
                'task' => [
                    'id' => $task->getId(),
                    'uuid' => $task->getUuid(),
                    'title' => $task->getTitle(),
                    'columnId' => $column->getId(),
                    'position' => $task->getPosition(),
                    'status' => $task->getStatus()->value,
                ]
            ])
        );

        $this->hub->publish($update);
    }
}
