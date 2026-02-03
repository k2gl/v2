# Kanban Board Implementation - Complete Documentation

## Overview

Production-ready Kanban board implementation with Symfony, FrankenPHP, PostgreSQL, and Vue.js frontend. Features include real-time updates, GitHub OAuth, and drag-and-drop functionality.

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                     Vue 3 Frontend (SPA)                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐ │
│  │ Pinia Store  │  │  Kanban UI   │  │  Real-time Updates   │ │
│  └──────────────┘  └──────────────┘  └──────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ HTTPS + JWT + SSE
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│               FrankenPHP Server (Caddy + PHP Worker)              │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │                    Mercure Hub (Real-time)                 │  │
│  │  - JWT Authorization    - SSE Broadcasting    - CORS       │  │
│  └─────────────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │                   Symfony Application                       │  │
│  │  - Vertical Slices    - JWT Auth      - Doctrine ORM     │  │
│  └─────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ PostgreSQL + Redis
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PostgreSQL Database                           │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐ │
│  │    Users     │  │   Boards     │  │        Tasks           │ │
│  │ (GitHub OAuth) │             │  │ (JSONB + Positions)    │ │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## Project Structure

```
src/
├── Board/                          # Board Domain Module
│   ├── Entity/
│   │   ├── Board.php              # Board aggregate root
│   │   └── Column.php             # Column entity
│   ├── Repository/
│   │   └── BoardRepository.php    # Board queries with eager loading
│   └── Features/
│       └── GetBoard/
│           ├── BoardResponse.php   # DTOs with OpenAPI annotations
│           └── GetBoardAction.php  # Controller with CurrentUser
│
├── Task/                           # Task Domain Module
│   ├── Entity/
│   │   └── Task.php              # Task with status workflow
│   ├── Domain/
│   │   └── ValueObject/
│   │       └── TaskStatus.php    # Status enum with transitions
│   ├── Repository/
│   │   └── TaskRepository.php    # Task queries with JSONB support
│   └── Features/
│       ├── MoveTask/
│       │   ├── MoveTaskMessage.php
│       │   ├── MoveTaskHandler.php
│       │   └── MoveTaskAction.php
│       └── ReorderTasks/
│           ├── ReorderTasksMessage.php
│           ├── ReorderTasksHandler.php
│           └── ReorderTasksAction.php
│
├── User/                           # User Domain Module
│   ├── Entity/
│   │   └── User.php              # User with GitHub OAuth fields
│   ├── Features/
│   │   ├── Login/
│   │   │   ├── LoginRequest.php
│   │   │   └── LoginResponse.php
│   │   └── GitHubAuth/
│   │       ├── GitHubController.php
│   │       └── GitHubAuthenticator.php
│   └── Infrastructure/
│       └── EventListener/
│           └── JWTCreatedListener.php
│
└── SharedKernel/
    └── Event/
        └── TaskCompletedEvent.php # Cross-module events
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    github_id BIGINT UNIQUE,
    github_username VARCHAR(255),
    avatar_url VARCHAR(512),
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP,
    last_login_at TIMESTAMP
);
```

### Boards Table
```sql
CREATE TABLE boards (
    id SERIAL PRIMARY KEY,
    owner_id INTEGER NOT NULL REFERENCES users(id),
    uuid UUID UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    settings JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP
);

CREATE INDEX idx_boards_uuid ON boards(uuid);
CREATE INDEX idx_boards_owner ON boards(owner_id);
```

### Board Columns Table
```sql
CREATE TABLE board_columns (
    id SERIAL PRIMARY KEY,
    board_id INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    position DECIMAL(10, 5) NOT NULL DEFAULT 0,
    task_count INTEGER DEFAULT 0,
    settings JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP
);

CREATE INDEX idx_columns_board ON board_columns(board_id);
```

### Tasks Table
```sql
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL,
    column_id INTEGER NOT NULL REFERENCES board_columns(id) ON DELETE CASCADE,
    owner_id INTEGER NOT NULL REFERENCES users(id),
    assignee_id INTEGER REFERENCES users(id),
    status VARCHAR(50) NOT NULL DEFAULT 'backlog',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    position DECIMAL(20, 10) NOT NULL DEFAULT 0,
    metadata JSONB DEFAULT '{}',
    sort_order INTEGER DEFAULT 0,
    due_date TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP,
    completed_at TIMESTAMP
);

CREATE INDEX idx_tasks_column ON tasks(column_id);
CREATE INDEX idx_tasks_position ON tasks(column_id, position);
CREATE INDEX idx_tasks_assignee ON tasks(assignee_id);
CREATE INDEX idx_tasks_metadata ON tasks USING GIN (metadata);
CREATE INDEX idx_tasks_status ON tasks(status);
```

## Key Features

### 1. Task Status Workflow
```php
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

### 2. Fractional Indexing for Drag&Drop
```php
// Insert task between positions 1 and 2: new position = 1.5
$newPosition = ($prevPosition + $nextPosition) / 2;

UPDATE tasks 
SET position = 1.5, column_id = 2 
WHERE id = 123;
```

### 3. JSONB Metadata
```php
// Store tags, colors, checklists in single JSONB field
$task->setMetadata([
    'tags' => ['bug', 'high-priority'],
    'color' => '#ff6b6b',
    'checklist' => [
        ['item' => 'Review code', 'done' => false],
        ['item' => 'Write tests', 'done' => true]
    ]
]);

// Query by tag using PostgreSQL JSONB
SELECT * FROM tasks 
WHERE metadata @> '{"tags": ["bug"]}';
```

### 4. Eager Loading (No N+1 Problem)
```php
public function findFullBoard(int $boardId, User $user): ?Board
{
    return $this->createQueryBuilder('b')
        ->select('b', 'c', 't')
        ->leftJoin('b.columns', 'c')
        ->leftJoin('c.tasks', 't')
        ->where('b.id = :id')
        ->andWhere('b.owner = :user')
        ->setParameter('id', $boardId)
        ->setParameter('user', $user)
        ->orderBy('c.position', 'ASC')
        ->addOrderBy('t.position', 'ASC')
        ->getQuery()
        ->getOneOrNullResult();
}
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login_check` | JWT login |
| GET | `/api/connect/github` | GitHub OAuth redirect |
| GET | `/api/connect/github/check` | GitHub OAuth callback |

### Boards
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/boards/{id}` | Get full board with columns and tasks |
| POST | `/api/boards` | Create new board |
| DELETE | `/api/boards/{id}` | Delete board |

### Tasks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/tasks/{id}/move` | Move task to new column/status |
| POST | `/api/tasks/reorder` | Reorder tasks with fractional indexing |

### Real-time
| Method | Endpoint | Description |
|--------|----------|-------------|
| SSE | `/.well-known/mercure?topic=/board/{id}` | Subscribe to board updates |

## Frontend Integration

### Vue Pinia Store
```javascript
import { defineStore } from 'pinia';
import { apiClient } from '@/api/client';

export const useKanbanStore = defineStore('kanban', {
  state: () => ({
    boards: [],
    currentBoard: null,
    tasks: {},
  }),

  actions: {
    async fetchBoard(boardId) {
      const response = await apiClient.get(`/boards/${boardId}`);
      this.currentBoard = response.data;
    },

    async moveTask(taskId, newStatus) {
      await apiClient.post(`/tasks/${taskId}/move`, { newStatus });
    },

    subscribeToUpdates(boardId) {
      const url = new URL('/.well-known/mercure', window.location.origin);
      url.searchParams.append('topic', `/board/${boardId}`);

      const eventSource = new EventSource(url);
      eventSource.onmessage = (event) => {
        const data = JSON.parse(event.data);
        this.handleRemoteUpdate(data);
      };
    },
  },
});
```

## Performance Optimizations

### FrankenPHP Worker Mode
- Application stays in memory between requests
- No kernel reinitialisation overhead
- 2-4x faster than PHP-FPM

### PostgreSQL Optimizations
- JSONB for flexible metadata storage
- Fractional indexing for O(1) drag-drop inserts
- GIN indexes for JSONB queries
- Eager loading prevents N+1 queries

### Caddy Server
- HTTP/3 (QUIC) for faster connections
- Zstd compression (faster than gzip)
- Static file serving for OpenAPI docs
- Built-in Mercure Hub for SSE

## Security Features

### JWT Authentication
- Stateless authentication (no server-side sessions)
- Token-based API access
- JWT payload includes user roles and Mercure subscriptions

### GitHub OAuth
- Automatic user creation on first login
- Avatar URL from GitHub profile
- Email verification via GitHub

### Access Control
- Board ownership verification
- Row-level security via Doctrine
- JWT token validation on every request

## Quick Start

```bash
# Start infrastructure
docker-compose up -d

# Run migrations
php bin/console doctrine:migrations:migrate

# Generate JWT keys
php bin/console lexik:jwt:generate-keypair

# Generate OpenAPI documentation
php bin/console nelmio:apidoc:dump --format=yaml > public/openapi.yaml

# Access application
# - API: http://localhost/api
# - Swagger UI: http://localhost/docs
# - Health check: http://localhost/health
```

## Testing Strategy

```php
class ReorderTasksTest extends WebTestCase
{
    public function testTasksAreReordered(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/tasks/reorder', content: json_encode([
            'columnId' => 1,
            'orderedIds' => [3, 1, 2]
        ]));

        $this->assertResponseIsSuccessful();
        
        // Verify positions in DB
        $task3 = $this->repository->find(3);
        $this->assertEquals(0, $task3->getPosition());
    }
}
```

## Summary

This implementation provides:

- **High Performance**: FrankenPHP Worker Mode + PostgreSQL optimizations
- **Real-time Updates**: Mercure Hub + Server-Sent Events
- **Clean Architecture**: Vertical Slice + Modular Monolith
- **Type Safety**: Full TypeScript support from OpenAPI
- **Security**: JWT + GitHub OAuth
- **Developer Experience**: Self-documenting API, Makefile automation
