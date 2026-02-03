# Kanban Module - Pragmatic DDD Architecture

## Overview

Kanban module with Board and Task domains, implementing Vertical Slice Architecture with FrankenPHP Worker Mode optimization.

## Domain Structure

```
src/
├── Task/                           # Task Domain Module
│   ├── Entity/
│   │   └── Task.php               # Task aggregate
│   ├── Domain/
│   │   ├── ValueObject/
│   │   │   └── TaskStatus.php     # Status enum with transition rules
│   │   └── Event/
│   │       └── TaskCompletedEvent.php
│   └── Features/
│       ├── MoveTask/              # Vertical Slice: Move task between columns
│       │   ├── MoveTaskAction.php
│       │   ├── MoveTaskMessage.php
│       │   └── MoveTaskHandler.php
│       └── ReorderTasks/         # Vertical Slice: Drag&Drop reordering
│           ├── ReorderTasksAction.php
│           ├── ReorderTasksMessage.php
│           └── ReorderTasksHandler.php
│
└── Board/                         # Board Domain Module
    └── Entity/
        ├── Board.php             # Board aggregate (contains columns)
        └── Column.php            # Column entity (part of board)
```

## TaskStatus Value Object

```php
namespace App\Task\Domain\ValueObject;

enum TaskStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function canTransitionTo(self $target): bool
    {
        return match($this) {
            self::Backlog => $target === self::Todo,
            self::Todo => $target === self::InProgress,
            self::InProgress => in_array($target, [self::Todo, self::Done]),
            self::Done => $target === self::InProgress,
        };
    }
}
```

## Task Entity

```php
namespace App\Task\Entity;

#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: TaskStatus::class)]
    private TaskStatus $status;

    #[ORM\Column]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'column_id')]
    private int $columnId;

    #[ORM\Column(name: 'board_id')]
    private int $boardId;

    #[ORM\Column(name: 'position')]
    private int $position = 0;

    public function move(TaskStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \DomainException(
                "Invalid status transition from {$this->status->value} to {$newStatus->value}"
            );
        }
        $this->status = $newStatus;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

## Vertical Slices

### MoveTask Feature

**Request DTO** (`MoveTaskMessage.php`):
```php
#[OA\Schema(description: "Request to move task between columns")]
final readonly class MoveTaskMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $taskId,

        #[Assert\NotNull]
        public TaskStatus $newStatus
    ) {}
}
```

**Handler** (`MoveTaskHandler.php`):
```php
readonly class MoveTaskHandler
{
    public function handle(MoveTaskMessage $message): MoveTaskResult
    {
        $task = $this->taskRepository->find($message->taskId)
            ?? throw new \DomainException("Task {$message->taskId} not found");

        $task->move($message->newStatus);
        $this->entityManager->flush();

        $this->eventBus->dispatch(
            new TaskCompletedEvent(
                taskId: $task->getId(),
                title: $task->getTitle(),
                completedAt: new \DateTimeImmutable()
            )
        );

        return new MoveTaskResult(/* ... */);
    }
}
```

### ReorderTasks Feature (Drag&Drop)

**Request DTO** (`ReorderTasksMessage.php`):
```php
#[OA\Schema(description: "Request to reorder tasks within a column")]
final readonly class ReorderTasksMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $columnId,

        /** @var array<int> */
        #[Assert\All([new Assert\Type('int')])]
        #[Assert\Count(min: 1)]
        public array $orderedIds
    ) {}
}
```

**Handler with Bulk Update** (`ReorderTasksHandler.php`):
```php
readonly class ReorderTasksHandler
{
    public function handle(ReorderTasksMessage $message): void
    {
        $connection = $this->entityManager->getConnection();

        $this->entityManager->wrapInTransaction(function() use ($connection, $message) {
            foreach ($message->orderedIds as $position => $taskId) {
                $connection->executeStatement(
                    'UPDATE tasks SET position = :position, column_id = :column_id WHERE id = :id',
                    [
                        'position' => $position,
                        'column_id' => $message->columnId,
                        'id' => $taskId
                    ]
                );
            }
        });
    }
}
```

## Board Module

### Board Entity

```php
namespace App\Board\Entity;

#[ORM\Entity]
#[ORM\Table(name: 'boards')]
class Board
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private string $title;

    /** @var Collection<int, Column> */
    #[ORM\OneToMany(mappedBy: 'board', targetEntity: Column::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $columns;

    public function addColumn(string $name, int $position): Column
    {
        $column = new Column($this, $name, $position);
        $this->columns->add($column);
        return $column;
    }
}
```

## Cross-Module Events

```php
namespace App\Task\Domain\Event;

final readonly class TaskCompletedEvent
{
    public function __construct(
        public int $taskId,
        public string $title,
        public \DateTimeImmutable $completedAt,
        public ?int $assigneeId = null
    ) {}
}
```

## Performance Considerations

### FrankenPHP Worker Mode

- All DTOs and handlers are stateless (`readonly` classes)
- Bulk SQL updates bypass Doctrine UnitOfWork for performance
- No static state between requests

### Database Optimization

- Direct SQL for bulk position updates (`ReorderTasksHandler`)
- Indexed columns: `board_id`, `column_id`, `position`
- Transactions for atomic reorder operations

## API Endpoints

| Method | Endpoint | Feature | Description |
|--------|----------|---------|-------------|
| POST | `/api/tasks/{id}/move` | MoveTask | Move task to new column/status |
| POST | `/api/tasks/reorder` | ReorderTasks | Reorder tasks within/between columns |

## Testing Strategy

```php
public function testTasksAreReordered(): void
{
    $client = static::createClient();
    
    $client->request('POST', '/api/tasks/reorder', content: json_encode([
        'columnId' => 1,
        'orderedIds' => [3, 1, 2]
    ]));

    $this->assertResponseIsSuccessful();
    
    // Verify positions in DB
    $task1 = $this->repository->find(1);
    $this->assertEquals(1, $task1->getPosition());
}
```

## Quick Start

```bash
# Initialize project
make init

# Run tests
make test

# Generate documentation
make docs
```

## Summary

This Kanban module provides:
- **Task Management**: Create, move, reorder tasks
- **Board Structure**: Flexible column configuration
- **Status Workflow**: Validated transitions (Backlog → Todo → In Progress → Done)
- **Event-Driven**: Cross-module notifications via events
- **Performance**: Bulk SQL updates for drag-and-drop operations
