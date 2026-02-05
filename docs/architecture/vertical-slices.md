# Vertical Slices Architecture — Quick Guide

## Principles

| Principle | Description |
|-----------|-------------|
| **Slices** | Code grouped by business features, not technical layers |
| **CQRS** | Command (write) and Query (read) separation |
| **Low Coupling** | Minimum dependencies between slices |
| **High Cohesion** | Everything for a feature in one folder |

## Project Structure

```
src/
├── Kernel.php              # System core (Symfony MicroKernel)
├── Shared/                 # Global infrastructure
│   ├── Exception/
│   └── Services/
│
├── User/                   # Module
│   ├── Entity/
│   ├── Enums/
│   ├── ValueObject/
│   ├── Event/
│   ├── Services/
│   ├── Clients/
│   ├── Repositories/
│   ├── Exception/
│   └── Features/           # Vertical Slices (flat structure)
│       └── {FeatureName}/
│           ├── {FeatureName}Command.php
│           ├── {FeatureName}Query.php
│           ├── {FeatureName}Handler.php
│           ├── {FeatureName}Request.php
│           └── {FeatureName}Response.php
│
├── Task/                   # Module (same pattern)
├── Board/                  # Module (same pattern)
└── Health/                 # Technical feature (same pattern)
```

## What Goes to Shared?

### Extract (Always)
- Infrastructure wrappers (Helpers)
- Base exceptions

### Do NOT Extract (Never)
- Business logic
- Similar code in different slices (WET > DRY)

## Naming Conventions

| Type | Example |
|------|---------|
| Features | `CreateUser/` |
| Command | `CreateUserCommand.php` |
| Query | `CreateUserQuery.php` |
| Handler | `CreateUserHandler.php` |
| Controller | `CreateUserController.php` |
| Request | `CreateUserRequest.php` |
| Response | `CreateUserResponse.php` |

## Configuration

### services.yaml
```yaml
App\:
    resource: '../src/'
    exclude:
        - '../src/Shared/{Exception,Services}/'
        - '../src/*/{Entity,Enums}/'

App\**\*Controller:
    tags: ['controller.service_arguments']

App\**\*Handler:
    tags: ['messenger.message_handler']
```

### routes.yaml
```yaml
controllers:
    resource: ../src/
    type: attribute
```

## Key Tools

1. **Symfony Messenger** — Command/Query Bus
2. **Deptrac** — Module boundary control
3. **PHPStan** — Static analysis

## AI Agent Benefits

- **Context localization**: One folder = one AI request
- **Simple testing**: One input → one output
- **Safe changes**: Changing one slice doesn't break others

## References

- [ADR 1: Vertical Slices Architecture](0001-vertical-slices.md)
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [Deptrac](https://github.com/qossmic/deptrac)
