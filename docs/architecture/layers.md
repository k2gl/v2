# Architectural Layers

## Overview

This project follows a strict layered architecture with clear separation of concerns. Each layer has specific responsibilities and dependencies rules.

## 1. Domain Layer (Core)

**Location:** `src/{Module}/Domain/`

The Domain layer contains the **business logic** - the heart of your application.

### Components

- **Entities**: Business objects with unique identity and lifecycle
- **Value Objects**: Immutable objects without identity (e.g., Email, Money, Address)
- **Domain Events**: Significant business occurrences that other parts of the system may care about
- **Repository Interfaces**: Contracts for persistence (defined in Domain, implemented in Infrastructure)

### Rules

- **ZERO dependencies** on Symfony, Doctrine, or any framework
- **Pure PHP** - no attributes for mapping here
- Contains **business rules** and **invariants**

### Example

```php
declare(strict_types=1);

namespace App\User\Domain;

use App\Shared\Domain\AbstractAggregateRoot;
use App\Shared\Domain\ValueObject\Email;

final class User extends AbstractAggregateRoot
{
    public function __construct(
        public readonly int $id,
        private Email $email,
        private UserStatus $status
    ) {}

    public function activate(): void
    {
        if ($this->status !== UserStatus::PENDING) {
            throw new \DomainException('User cannot be activated');
        }
        
        $this->status = UserStatus::ACTIVE;
        $this->recordEvent(new UserActivatedEvent($this->id));
    }
}
```

## 2. Application Layer (Use Cases)

**Location:** `src/{Module}/Application/`

The Application layer orchestrates the flow of work. It contains **use cases** and **service interfaces**.

### Components

- **Handlers**: Mediate between Commands/Queries and Domain
- **DTOs**: Data Transfer Objects for Commands and Queries
- **Service Interfaces**: Contracts for infrastructure (e.g., `EmailSenderInterface`)

### Rules

- Contains **orchestration logic** (not business logic)
- **Depends only on Domain** and abstractions
- Receives validated DTOs
- Returns Response DTOs

### Example

```php
declare(strict_types=1);

namespace App\User\Application;

use App\User\Application\Command\ActivateUserCommand;
use App\User\Domain\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ActivateUserHandler
{
    public function __construct(
        private UserRepository $repository
    ) {}

    public function handle(ActivateUserCommand $command): void
    {
        $user = $this->repository->findById($command->userId);
        $user->activate();
        $this->repository->save($user);
    }
}
```

## 3. Infrastructure Layer

**Location:** `src/{Module}/Infrastructure/`

The Infrastructure layer implements the **technical details** - database access, API clients, framework configuration.

### Components

- **Doctrine Repositories**: Implement Domain repository interfaces
- **API Clients**: External service integrations
- **Framework Adapters**: Symfony config, security, etc.

### Rules

- Implements **abstractions defined in Domain/Application**
- Contains **persistence logic**, **HTTP clients**, **file storage**
- **Framework-dependent**

### Example

```php
declare(strict_types=1);

namespace App\User\Infrastructure;

use App\User\Domain\UserRepository;
use App\User\Infrastructure\Doctrine\UserDoctrineMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserDoctrineMapper $mapper
    ) {}

    public function findById(int $id): ?User
    {
        $entity = $this->em->find(UserEntity::class, $id);
        return $entity ? $this->mapper->toDomain($entity) : null;
    }
}
```

## 4. UI Layer

**Location:** `src/{Module}/UI/`

The UI layer handles **incoming requests** and **outgoing responses**.

### Components

- **Controllers**: HTTP request handling
- **CLI Commands**: Console commands
- **Form Types**: Symfony forms
- **Security**: Voters, token storage

### Rules

- **Thinnest possible** - only dispatches Commands/Queries
- No business logic
- Returns **Response DTOs**

### Example

```php
declare(strict_types=1);

namespace App\User\UI\Http;

use App\User\Application\Command\ActivateUserCommand;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ActivateUserAction extends AbstractController
{
    #[Route('/api/users/{id}/activate', methods: ['POST'])]
    public function __invoke(
        int $id,
        #[MapRequestPayload] ?ActivateUserRequest $request
    ): ActivateUserResponse {
        $command = new ActivateUserCommand($id);
        $this->dispatch($command);
        
        return new ActivateUserResponse(success: true);
    }
}
```

## Dependency Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────────┐
│ UI Layer    │────▶│ Application │────▶│ Domain Layer    │
│ (Thin)      │     │ (Orchestration) │ (Business Logic) │
└─────────────┘     └─────────────┘     └─────────────────┘
        │                   │                     ▲
        │                   │                     │
        ▼                   ▼                     │
┌─────────────┐     ┌─────────────┐               │
│ Infrastructure│◀───│ Abstractions│───────────────┘
│ (Implementations)│     │ (Interfaces)│
└─────────────┘     └─────────────┘
```

## Key Principles

| Layer | Responsibility | Dependencies |
|-------|---------------|--------------|
| **Domain** | Business rules | None (pure) |
| **Application** | Use cases | Domain, Abstractions |
| **Infrastructure** | Technical details | Framework, External |
| **UI** | Request/Response | Application, Framework |

## Anti-Patterns to Avoid

1. **Anemic Domain Model**: Entities without behavior (just getters/setters)
2. **God Classes**: Classes that do too much
3. **Layer Violation**: Domain depending on Application or Infrastructure
4. **Fat Controllers**: Business logic in Controllers
