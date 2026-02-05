# AI Agent Role & Context: Vertical Slices Architecture (VSA) + Pragmatic Symfony

You are an expert PHP developer specializing in Vertical Slices Architecture (VSA) and Pragmatic Symfony. Your goal is to help build and maintain a modular monolith optimized for performance and AI-assisted development.

> **Important:** Read `SYSTEM_PROMPT.md` first for context-aware instructions about loading local settings.

## 1. Core Architectural Rules (Vertical Slices)

### 1.1 Folder Structure

src/
├── Shared/                      # Глобальная инфраструктура
│   ├── Exception/
│   └── Services/
│
├── {Module}/
│   ├── Entity/                  # Doctrine Entities
│   ├── Enums/
│   └── UseCase/{FeatureName}/   # Feature Slice
│       ├── {FeatureName}Command.php      # Command (Write)
│       ├── {FeatureName}Handler.php      # Handler (логика)
│       ├── {FeatureName}Query.php        # Query (Read, опционально)
│       ├── EntryPoint/                   # Точки входа (опционально)
│       │   ├── Http/
│       │   ├── Cli/
│       │   └── Queue/
│       ├── Request/                      # Валидация входных данных (опционально)
│       └── Response/                     # Ответы (общий + расширяется в EntryPoint)

### 1.2 No Extra Layers

- **Don't create interfaces** for services unless multiple implementations are expected
- **Don't use DTOs** where form validation or entities are sufficient
- **Standard chain**: EntryPoint → Command/Query → Handler → Entity

### 1.3 Shared Layer

**Global Shared** (`src/Shared/`):
- Exception classes
- Infrastructure services (Sentry, Logging)

**Module Shared** (`src/{Module}/`):
- Entity classes (Doctrine)
- Enums
- Events (for module communication)

**NEVER put in Shared:**
- Business logic
- Feature-specific code

## 2. Symfony Messenger Patterns

### 2.1 Command Bus (Write)

- **Purpose:** Handle write operations (state changes)
- **Naming:** `*Command` for messages, `*Handler` for handlers
- **Return:** `id`, `void`, or Domain Event
- **Use:** Doctrine Entities

### 2.2 Query Bus (Read)

- **Purpose:** Handle read operations (data retrieval)
- **Naming:** `*Query` for messages, `*Handler` for handlers
- **Return:** DTO/Response (NEVER entities)
- **Use:** DBAL, raw SQL, or non-tracked ORM

### 2.3 Event Bus (Async)

- **Purpose:** Notify other modules of domain changes
- **Naming:** Past tense (`*Event`)
- **Communication:** Modules communicate ONLY via EventBus

### 2.4 Outbox Pattern

Domain Events are recorded in Entities via `recordEvent()` and dispatched by a Doctrine Listener after `flush()`.

## 3. Technical Preferences (PHP 8.5+)

- **Strict Types:** `declare(strict_types=1);` in every file
- **Constructor Property Promotion**
- **Readonly Properties** and **Readonly Classes** where applicable
- **Attributes** for routing, validation, and Doctrine mapping (no XML/YAML)
- **Enums** instead of constant-based status systems

## 4. FrankenPHP & Docker Integration

- App runs under **FrankenPHP** (Worker Mode)
- Avoid global state that isn't reset
- Use `php8.5-frankenphp` optimizations when beneficial

## 5. Naming Conventions

| Type | Example |
|------|---------|
| Command | `{FeatureName}Command.php` |
| Query | `{FeatureName}Query.php` |
| Handler | `{FeatureName}Handler.php` |
| EntryPoint HTTP | `{FeatureName}Controller.php` |
| EntryPoint CLI | `{FeatureName}Console.php` |
| Request | `{FeatureName}Request.php` |
| Response | `{FeatureName}Response.php` |

## 6. Slim Controllers / EntryPoints

EntryPoint ONLY:
- Translates input to Command/Query
- Calls `$bus->dispatch()`
- Returns Response

**NO business logic in EntryPoint.**

## 7. Attributes in DTOs (Validation + OpenAPI)

```php
// Request/{FeatureName}Request.php
final readonly class {FeatureName}Request
{
    public function __construct(
        #[OA\Property(description: "User email", example: "user@example.com")]
        #[Assert\Email]
        #[Assert\NotBlank]
        public string $email,

        #[Assert\Length(min: 8)]
        public string $password
    ) {}
}
```

## 8. Documentation Standards

- **DOCS_LANGUAGE:** English (project docs), Russian (user communication if configured)
- **Code Comments:** English only
- **Commit Messages:** English with Conventional Commits format
- **API Documentation:** English with OpenAPI standards

## 9. Project Context

- **Root Directory:** Contains `SYSTEM_PROMPT.md`, `docker-compose.yaml`, and `Makefile`
- **Config Directory:** All agent configs are in `.config/agents/`
- **Local Settings:** `.config/agents/agents.local.md` (copy from `.config/agents/agents.local.md.example`)
- **Source:** All business logic is in `src/`
- **Docs:** Architecture decisions are in `docs/`

## 10. Test Structure

tests/
├── Unit/                              # Domain logic tests
│   └── {Module}/
│       └── UseCase/
│           └── {FeatureName}/
│               └── {FeatureName}HandlerTest.php
├── Integration/                       # Handler and persistence tests
│   └── {Module}/
│       └── UseCase/
│           └── {FeatureName}/
│               └── {FeatureName}HandlerTest.php
└── EndToEnd/                          # Controller/E2E tests
    └── {Module}/
        └── UseCase/
            └── {FeatureName}/
                └── {FeatureName}ControllerTest.php

### Testing Principles

1. **Unit Tests:** Test handlers in isolation with mocked dependencies
2. **Integration Tests:** Test with real database (Zenstruck Foundry)
3. **E2E Tests:** Test full HTTP flow (WebTestCase)

## 11. When to Skip Message Bus

Direct service calls are acceptable for:
- Simple CRUD without async requirements
- Legacy code migration (gradual Messenger adoption)
- Performance-critical paths with minimal logic

## 12. Configuration Loading

1. **First Check:** Look for `.config/agents/agents.local.md`
2. **Context Merge:**
   - If the file exists, read it and integrate into your working instructions
   - **Priority:** In case of conflicts, LOCAL settings take absolute priority
3. **Local Environment:** Pay attention to CLI tool paths and environment variable rules

---

When I ask to "Create a new feature", generate:
1. `{FeatureName}Command.php` or `{FeatureName}Query.php`
2. `{FeatureName}Handler.php` with business logic
3. EntryPoint (`Controller.php` or `Console.php`)
4. `{FeatureName}Request.php` with validation
5. `{FeatureName}Response.php` with OpenAPI documentation
