# Development Guide

## Overview

This guide covers setting up your development environment and following project conventions.

## Prerequisites

- Docker & Docker Compose
- Make
- PHP 8.5+ (for local tooling only)

## Quick Start

```bash
# 1. Clone and enter directory
git clone git@github.com:your-org/pragmatic-franken.git
cd pragmatic-franken

# 2. Create environment file
make env-create

# 3. Build and start containers
make up

# 4. Install dependencies
make install

# 5. Run database migrations
make db-migrate

# 6. (Optional) Load fixtures
make db-seed

# 7. Verify installation
make test
```

## Daily Development Commands

### Shell Access

```bash
make shell  # Enter PHP container shell
```

### Running Tests

```bash
make test              # Run all tests (fail-fast)
make test-coverage     # Run with coverage report
make coverage-html     # Generate HTML coverage
```

### Code Quality

```bash
make lint              # Run all linters (PHPStan + CS Check)
make phpstan           # Static analysis only
make cs-check          # Code style check only
make cs-fix            # Auto-fix code style
```

### Database Operations

```bash
make db-migrate        # Run migrations
make db-rollback       # Rollback last migration
make db-seed           # Load fixtures
make db-console        # Connect to PostgreSQL
```

### Docker Management

```bash
make up                # Start containers (detached)
make down              # Stop containers
make logs              # Follow logs
make build             # Rebuild images
make rebuild           # Rebuild without cache
```

## Project Structure

```
pragmatic-franken/
├── src/
│   ├── {Module}/
│   │   ├── Domain/       # Entities, Value Objects, Events
│   │   ├── Application/  # Commands, Queries, Handlers
│   │   ├── Infrastructure/ # Doctrine, External Services
│   │   └── UI/           # Controllers, Commands
│   └── Shared/           # Cross-cutting concerns
├── config/              # Symfony configuration
├── docs/
│   ├── architecture/    # ADR and architecture docs
│   └── guides/          # How-to guides
├── tests/
│   ├── Unit/            # Domain logic tests
│   ├── Integration/    # Handler integration tests
│   └── UI/              # Controller/E2E tests
├── docker/
│   ├── frankenphp/      # FrankenPHP config
│   ├── php/            # PHP extensions config
│   └── ...
├── Makefile
├── docker-compose.yml
└── composer.json
```

## Creating a New Feature

### Step 1: Create Module Structure

```bash
# Create module directories
mkdir -p src/Task/{Domain,Application/{Command,Query,Handler},Infrastructure,UI}
```

### Step 2: Define Domain Entity

```php
// src/Task/Domain/Task.php
declare(strict_types=1);

namespace App\Task\Domain;

use App\Shared\Domain\AbstractAggregateRoot;
use App\Task\Domain\Event\TaskCreatedEvent;

final class Task extends AbstractAggregateRoot
{
    public function __construct(
        public readonly int $id,
        private string $title,
        private TaskStatus $status = TaskStatus::TODO
    ) {
        $this->recordEvent(new TaskCreatedEvent($this->id, $this->title));
    }
    
    public function complete(): void
    {
        $this->status = TaskStatus::DONE;
    }
}
```

### Step 3: Create Command

```php
// src/Task/Application/Command/CreateTaskCommand.php
declare(strict_types=1);

namespace App\Task\Application\Command;

final readonly class CreateTaskCommand
{
    public function __construct(
        public string $title
    ) {}
}
```

### Step 4: Create Handler

```php
// src/Task/Application/Handler/CreateTaskHandler.php
declare(strict_types=1);

namespace App\Task\Application\Handler;

use App\Task\Application\Command\CreateTaskCommand;
use App\Task\Application\Dto\TaskResponse;
use App\Task\Infrastructure\TaskRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateTaskHandler
{
    public function __construct(private TaskRepository $repository) {}

    public function handle(CreateTaskCommand $command): TaskResponse
    {
        $task = new Task($command->title);
        $this->repository->save($task);
        
        return TaskResponse::fromEntity($task);
    }
}
```

### Step 5: Create Controller

```php
// src/Task/UI/Http/CreateTaskAction.php
declare(strict_types=1);

namespace App\Task\UI\Http;

use App\Task\Application\Command\CreateTaskCommand;
use App\Task\Application\Dto\TaskResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class CreateTaskAction
{
    #[Route('/api/tasks', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] CreateTaskCommand $command
    ): TaskResponse {
        return $this->dispatch($command);
    }
}
```

### Step 6: Write Tests

```php
// tests/Unit/Task/Domain/TaskTest.php
declare(strict_types=1);

namespace App\Tests\Unit\Task\Domain;

use App\Task\Domain\Task;
use App\Task\Domain\Event\TaskCreatedEvent;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function test_creates_task_with_title(): void
    {
        $task = new Task(1, 'Test Task');
        
        self::assertSame('Test Task', $task->title);
        self::assertSame(TaskStatus::TODO, $task->status);
    }
    
    public function test_records_domain_event(): void
    {
        $task = new Task(1, 'Test Task');
        $events = $task->releaseEvents();
        
        self::assertCount(1, $events);
        self::assertInstanceOf(TaskCreatedEvent::class, $events[0]);
    }
}
```

## Common Patterns

### Validation with Attributes

```php
declare(strict_types=1);

namespace App\Task\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateTaskCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $title,
        
        #[Assert\Positive]
        public ?int $projectId = null
    ) {}
}
```

### Using Enums for Status

```php
// src/Task/Domain/TaskStatus.php
declare(strict_types=1);

namespace App\Task\Domain;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    
    public function canTransitionTo(TaskStatus $target): bool
    {
        return match ($this) {
            self::TODO => $target === self::IN_PROGRESS,
            self::IN_PROGRESS => $target === self::DONE,
            self::DONE => false,
        };
    }
}
```

### Fetching Related Data with Queries

```php
// For reading, use Query Bus - never expose entities directly
final readonly class GetTaskDetailsHandler
{
    public function handle(GetTaskDetailsQuery $query): TaskDetailsDto
    {
        $task = $this->repository->findById($query->taskId);
        $assignee = $this->userRepository->findById($task->assigneeId);
        $comments = $this->commentRepository->findByTaskId($task->id);
        
        return new TaskDetailsDto(
            id: $task->id,
            title: $task->title,
            status: $task->status,
            assignee: $assignee->name,
            comments: array_map(fn($c) => $c->text, $comments)
        );
    }
}
```

## Debugging Tips

### View Logs

```bash
make logs
```

### Xdebug

Configure your IDE:
- Port: `9003`
- Host: `host.docker.internal` (or `docker.for.mac.localhost` on Mac)
- Start listening for debug connections

### Messenger Inspector

```bash
# View failed messages
php bin/console messenger:failed

# Retry failed messages
php bin/console messenger:retry
```

## Performance Tips

1. **Use Redis for sessions and cache**
2. **Enable OPcache in development** (`make composer-chown`)
3. **Use PHP 8.5 optimizations**
4. **Configure Caddy for HTTP/3**
5. **Use connection pooling in production**

## Code Style Enforcement

This project uses:
- **PHP-CS-Fixer** with PSR-12 rules
- **PHPStan** at level 8 (strict)
- **Rector** for automated upgrades (optional)

Run checks before committing:

```bash
make cs-fix && make phpstan && make test
```
