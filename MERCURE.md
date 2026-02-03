# Mercure Real-time Integration

## Overview

Built-in real-time updates using Mercure Hub (included in FrankenPHP). Enables real-time Kanban updates without Node.js, Socket.io, or Redis Pub/Sub.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│ FrankenPHP + Caddy + Mercure Hub                           │
│ ┌─────────────────────────────────────────────────────┐  │
│ │ PHP Worker (Task Handler)                            │  │
│ │ 1. Update database                                  │  │
│ │ 2. Publish Update to Mercure                       │  │
│ └─────────────────────────────────────────────────────┘  │
│                      ↓                                      │
│ ┌─────────────────────────────────────────────────────┐  │
│ │ Mercure Hub (Caddy module)                         │  │
│ │ - JWT authorization                                │  │
│ │ - SSE broadcasting                                 │  │
│ └─────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Frontend (React/Vue)                                        │
│ - EventSource subscription                                 │
│ - Real-time UI updates                                     │
└─────────────────────────────────────────────────────────────┘
```

## Integration in Handlers

### ReorderTasksHandler with Mercure

```php
namespace App\Task\Features\ReorderTasks;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

readonly class ReorderTasksHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?HubInterface $hub = null
    ) {}

    public function handle(ReorderTasksMessage $message): void
    {
        // Bulk update in database
        $this->entityManager->wrapInTransaction(function() use ($message) {
            foreach ($message->orderedIds as $position => $taskId) {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE tasks SET position = ?, column_id = ? WHERE id = ?',
                    [$position, $message->columnId, $taskId]
                );
            }
        });

        // Publish real-time update via Mercure
        if ($this->hub !== null) {
            $update = new Update(
                "https://your-kanban.com/board/{$message->columnId}",
                json_encode([
                    'event' => 'tasks_reordered',
                    'columnId' => $message->columnId,
                    'newOrder' => $message->orderedIds,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
                ])
            );

            $this->hub->publish($update);
        }
    }
}
```

### MoveTaskHandler with Mercure

```php
namespace App\Task\Features\MoveTask;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

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
        $task = $this->taskRepository->find($message->taskId);
        $previousColumnId = $task->getColumnId();
        $task->move($message->newStatus);

        $this->entityManager->flush();

        // Real-time notification
        if ($this->hub !== null) {
            $update = new Update(
                "https://your-kanban.com/task/{$task->getId()}",
                json_encode([
                    'event' => 'task_moved',
                    'taskId' => $task->getId(),
                    'boardId' => $task->getBoardId(),
                    'previousColumnId' => $previousColumnId,
                    'newColumnId' => $task->getColumnId(),
                    'newStatus' => $message->newStatus->value,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
                ])
            );

            $this->hub->publish($update);
        }

        // Async event for other modules
        $this->eventBus->dispatch(
            new TaskCompletedEvent($task->getId(), $task->getTitle())
        );

        return MoveTaskResult::fromTask($task, $previousColumnId);
    }
}
```

## Caddyfile Configuration

```caddyfile
:80 {
    root * public/

    # Mercure Hub Configuration
    mercure {
        publisher_jwt {$MERCURE_PUBLISHER_JWT_KEY}
        subscriber_jwt {$MERCURE_SUBSCRIBER_JWT_KEY}
        anonymous  # Allow anonymous for development
        cors {
            allowed_origins *
        }
    }

    encode zstd gzip
    php_server {
        index index.php
    }
    file_server
}
```

## Environment Variables

```env
# Generate JWT keys for Mercure
MERCURE_PUBLISHER_JWT_KEY=your-publisher-secret-key-min-32-chars
MERCURE_SUBSCRIBER_JWT_KEY=your-subscriber-secret-key-min-32-chars
```

## Frontend Integration

### Vanilla JavaScript

```javascript
import KanbanRealtime from '/js/kanban-realtime.js';

const realtime = new KanbanRealtime(
    'https://your-kanban.com',
    'board-123',
    {
        onTaskMoved: (data) => {
            console.log('Task moved:', data);
            updateTaskInUI(data.taskId, {
                columnId: data.newColumnId,
                status: data.newStatus
            });
        },
        onTasksReordered: (data) => {
            console.log('Tasks reordered:', data);
            reorderTasksInUI(data.columnId, data.newOrder);
        },
        onError: (error) => {
            console.error('Realtime error:', error);
        }
    }
);

// Connect to real-time updates
realtime.connect();

// Disconnect when leaving page
window.addEventListener('beforeunload', () => {
    realtime.disconnect();
});
```

### React Example

```jsx
import { useEffect, useState } from 'react';

function KanbanBoard({ boardId }) {
    const [columns, setColumns] = useState({});
    const [realtimeConnected, setRealtimeConnected] = useState(false);

    useEffect(() => {
        const realtime = new KanbanRealtime(
            window.location.origin,
            boardId,
            {
                onTaskMoved: (data) => {
                    setColumns(prev => moveTaskBetweenColumns(
                        prev,
                        data.previousColumnId,
                        data.newColumnId,
                        data.taskId
                    ));
                },
                onTasksReordered: (data) => {
                    setColumns(prev => reorderTasksInColumn(
                        prev,
                        data.columnId,
                        data.newOrder
                    ));
                },
                onConnected: () => setRealtimeConnected(true),
                onDisconnected: () => setRealtimeConnected(false)
            }
        );

        realtime.connect();

        return () => realtime.disconnect();
    }, [boardId]);

    return (
        <div className="kanban-board">
            <div className="status">
                Realtime: {realtimeConnected ? '🟢 Connected' : '🔴 Disconnected'}
            </div>
            {/* Board columns */}
        </div>
    );
}
```

## Event Types

| Event | Topic | Description |
|-------|-------|-------------|
| `task_moved` | `/task/{id}` | Single task moved between columns |
| `tasks_reordered` | `/board/{id}` | Multiple tasks reordered |

## Performance Characteristics

- **Zero infrastructure overhead**: Mercure runs inside FrankenPHP
- **Stateless workers**: PHP handlers don't maintain SSE connections
- **Efficient broadcasting**: Single connection per client
- **Automatic reconnection**: Built-in reconnection logic

## Security Considerations

### JWT Authorization

```javascript
// Include JWT token for authorized subscriptions
const url = new URL('/.well-known/mercure', baseUrl);
url.searchParams.append('topic', topic);
url.searchParams.append('authorization', `Bearer ${user.jwtToken}`);
```

### Topic Authorization

Configure in Symfony to restrict topics based on user permissions:

```php
// config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            jwt:
                secret: '%env(MERCURE_PUBLISHER_JWT_KEY)%'
```

## Debugging

Check Mercure connection:

```javascript
const eventSource = new EventSource('/.well-known/mercure?topic=/test');

eventSource.onopen = () => console.log('✅ Mercure connected');
eventSource.onerror = (err) => console.error('❌ Mercure error:', err);
```

## Summary

This implementation provides:

- **Real-time updates** without additional services
- **FrankenPHP integration** via built-in Mercure Hub
- **Seamless frontend** with EventSource API
- **Optimized performance** via SSE broadcasting
- **Security** via JWT authorization
- **Pragmatic approach** - no extra infrastructure needed
