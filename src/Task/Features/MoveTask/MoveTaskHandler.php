<?php

namespace App\Task\Features\MoveTask;

use App\Task\Entity\Task;
use App\Task\Entity\TaskRepository;
use App\Task\Entity\TaskStatus;
use App\Task\Features\MoveTask\MoveTaskMessage;
use App\Task\Features\MoveTask\MoveTaskResult;
use App\Task\Domain\Event\TaskCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class MoveTaskHandler
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
        private ?HubInterface $hub = null
    ) {}

    public function handle(MoveTaskMessage $message): MoveTaskResult
    {
        $task = $this->taskRepository->find($message->taskId)
            ?? throw new \DomainException("Task {$message->taskId} not found");

        $previousStatus = $task->getStatus()->value;
        $previousColumnId = $task->getColumnId();
        $task->move($message->newStatus);

        $this->entityManager->flush();

        if ($this->hub !== null) {
            $update = new Update(
                "https://your-kanban.com/task/{$task->getId()}",
                json_encode([
                    'event' => 'task_moved',
                    'taskId' => $task->getId(),
                    'boardId' => $task->getBoardId(),
                    'previousColumnId' => $previousColumnId,
                    'newColumnId' => $task->getColumnId(),
                    'previousStatus' => $previousStatus,
                    'newStatus' => $message->newStatus->value,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
                ])
            );

            $this->hub->publish($update);
        }

        $this->eventBus->dispatch(
            new TaskCompletedEvent(
                taskId: $task->getId(),
                title: $task->getTitle(),
                completedAt: new \DateTimeImmutable(),
                assigneeId: $task->getAssigneeId()
            )
        );

        return new MoveTaskResult(
            taskId: $task->getId(),
            title: $task->getTitle(),
            previousStatus: $previousStatus,
            newStatus: $message->newStatus->value,
            updatedAt: $task->getUpdatedAt()
        );
    }
}
