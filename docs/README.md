# Pragmatic Symfony Architecture - Kanban Implementation Guide

## Overview

Production-ready Kanban board with Symfony 8.3+, FrankenPHP, PostgreSQL, and Vue 3.

## Architecture Stack

### Backend
- **Framework**: Symfony 8.3+ with Vertical Slice Architecture
- **Server**: FrankenPHP (Caddy + PHP Worker Mode)
- **Database**: PostgreSQL 15 with JSONB
- **Cache**: Redis 7 for Messenger async transport
- **Real-time**: Mercure Hub (built into FrankenPHP)

### Frontend
- **Framework**: Vue 3 with Composition API
- **State Management**: Pinia
- **Drag&Drop**: vuedraggable
- **HTTP Client**: Axios

## Project Structure

```
src/
├── Board/                    # Domain Module
│   ├── Entity/
│   │   ├── Board.php        # Board aggregate
│   │   └── Column.php       # Column entity
│   ├── Repository/
│   │   └── BoardRepository.php
│   └── Features/
│       ├── GetBoard/        # Get board with tasks
│       └── CreateBoard/     # Create new board
│
├── Task/                     # Domain Module
│   ├── Entity/
│   │   └── Task.php         # Task with status workflow
│   ├── Domain/
│   │   └── ValueObject/
│   │       └── TaskStatus.php
│   ├── Repository/
│   │   └── TaskRepository.php
│   └── Features/
│       ├── MoveTask/        # Move task between columns
│       └── ReorderTasks/    # Drag&Drop reordering
│
├── User/                     # Domain Module
│   ├── Entity/
│   │   └── User.php         # User with GitHub OAuth
│   ├── Features/
│   │   ├── Login/           # JWT authentication
│   │   └── GitHubAuth/      # GitHub OAuth2
│   └── Infrastructure/
│       └── EventListener/
│           └── JWTCreatedListener.php
│
└── SharedKernel/
    └── Event/
        └── TaskCompletedEvent.php

public/js/
├── stores/
│   └── kanban.js            # Pinia store
├── components/
│   └── KanbanBoard.vue     # Main Kanban component
└── auth/
    ├── api-client.js       # Axios with JWT
    └── AuthCallback.vue    # OAuth callback

docs/
├── ARCHITECTURE.md          # Core architecture
├── KANBAN.md               # Kanban module details
├── MERCURE.md             # Real-time configuration
└── KANBAN_IMPLEMENTATION.md # Complete implementation
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

### 2. Fractional Indexing (Drag&Drop)
```php
// Insert between position 1 and 2: new position = 1.5
$newPos = ($prevPos + $nextPos) / 2;

UPDATE tasks SET position = 1.5 WHERE id = $taskId;
```

### 3. JSONB Metadata
```php
// Store tags, colors, checklists
$task->setMetadata([
    'tags' => ['bug', 'high-priority'],
    'color' => '#ff6b6b'
]);

// Query by tag
SELECT * FROM tasks WHERE metadata @> '{"tags": ["bug"]}';
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login_check` | JWT login |
| GET | `/api/connect/github` | GitHub OAuth |
| GET | `/api/connect/github/check` | OAuth callback |

### Boards
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/boards/{id}` | Get full board structure |
| POST | `/api/boards` | Create new board |
| DELETE | `/api/boards/{id}` | Delete board |

### Tasks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/tasks/{id}/move` | Move task (status transition) |
| POST | `/api/tasks/reorder` | Reorder with fractional indexing |

## Configuration Files

### Docker Compose
- `docker-compose.yml` - PostgreSQL, Redis, FrankenPHP

### FrankenPHP
- `docker/frankenphp/Caddyfile` - Server config with Mercure
- `docker/php/conf.d/app.ini` - PHP optimization

### CI/CD
- `.github/workflows/openapi_check.yaml` - OpenAPI drift check

## Quick Start

```bash
# Start infrastructure
docker-compose up -d

# Run migrations
php bin/console doctrine:migrations:migrate

# Generate JWT keys
php bin/console lexik:jwt:generate-keypair

# Generate OpenAPI docs
php bin/console nelmio:apidoc:dump --format=yaml > public/openapi.yaml

# Access
# - API: http://localhost/api
# - Swagger: http://localhost/docs
# - Health: http://localhost/health
```

## Development Commands

```bash
make init          # Initialize project
make build        # Build containers
make up           # Start containers
make db-migrate   # Run migrations
make docs         # Generate OpenAPI
make test         # Run tests
make shell        # Enter PHP container
```

## Performance Optimizations

### FrankenPHP Worker Mode
- Application stays in memory
- No kernel reinitialisation
- 2-4x faster than PHP-FPM

### PostgreSQL
- JSONB for flexible metadata
- Fractional indexing O(1)
- Eager loading (no N+1)

### Caddy Server
- HTTP/3 (QUIC)
- Zstd compression
- Static file serving

## Security

### JWT Authentication
- Stateless with LexikJWTAuthenticationBundle
- Token includes user roles and Mercure subscriptions

### GitHub OAuth
- knpuniversity/oauth2-client-bundle
- Auto user creation on first login

### Access Control
- Board ownership verification
- Row-level security via Doctrine
- JWT validation on every request

## Real-time Updates

### Mercure Configuration
```yaml
mercure:
    publisher_jwt: { env: MERCURE_PUBLISHER_JWT_KEY }
    subscriber_jwt: { env: MERCURE_SUBSCRIBER_JWT_KEY }
```

### Frontend Subscription
```javascript
const url = new URL('/.well-known/mercure', baseUrl);
url.searchParams.append('topic', `/board/${boardId}`);
const eventSource = new EventSource(url);
```

## Database Schema

```
users (GitHub OAuth)
  ↓
boards (UUID, owner_id)
  ↓
board_columns (position DECIMAL)
  ↓
tasks (position DECIMAL, metadata JSONB)
```

## Testing

```bash
# Integration tests with DAMA/DoctrineTestBundle
php bin/phpunit

# Test coverage
php bin/phpunit --coverage-html var/coverage
```

## Documentation Pipeline

1. **Build-time**: `nelmio:apidoc:dump` → `public/openapi.yaml`
2. **CI/CD**: Drift check ensures docs match code
3. **Swagger UI**: Static `/docs` via Caddy
4. **Frontend**: TypeScript generation from OpenAPI
