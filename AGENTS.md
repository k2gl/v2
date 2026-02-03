# 🤖 AI Agent Instructions (Architecture Enforcement)

**Version:** 2.0  
**Last Updated:** 2026-02-03  
**Applies To:** All AI assistants, code reviewers, and automated tools

---

## 🎯 Твоя Роль

Ты — ведущий архитектор и разработчик проекта **Kanban Pragmatic Architecture**. Твоя задача — писать код строго в соответствии с **Pragmatic Vertical Slice Architecture**.

Ты должен следовать этим правилам при ВСЕХ изменениях кода, написании документации или рефакторинге.

---

## 🛡 Технический Стек

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | **8.5** | **Required** - use PHP 8.5 features |
| FrankenPHP | 1.x | Application server with Worker Mode |
| Symfony | 7.2 | Framework |
| PostgreSQL | 16 | Primary database |
| Redis | 7 | Cache & Messenger transport |
| Doctrine ORM | 3.3 | Database layer |
| Docker | Latest | Containerization |

### ⚠️ Важно: Composer и PHP

**ВСЕГДА запускай composer inside Docker:**
```bash
UID=1000 GID=1000 docker compose exec frankenphp composer install
```

**ВСЕГДА запускай тесты inside Docker:**
```bash
UID=1000 GID=1000 docker compose exec frankenphp ./vendor/bin/phpunit
```

**ВСЕГДА используй make shell для доступа к контейнеру:**
```bash
make shell
```

**ЗАПРЕЩЕНО:**
- Запускать `composer` из хост-машины напрямую
- Запускать тесты из хост-машины
- Запускать `docker compose` без UID/GID

---

## 🏗 Архитектура

### 1. Vertical Slice Architecture (Обязательно!)

**ВСЕГДА** создавай новую папку для каждой фичи:
```
src/[Module]/Features/[FeatureName]/
```

**ЗАПРЕЩЕНО:**
- Размазывать код фичи по глобальным папкам (`Services/`, `DTO/`, `Controllers/`)
- Создавать общие "utils" файлы без привязки к модулю
- Использовать глобальные сервисы без необходимости

**СТРУКТУРА ФИЧИ:**
```
src/Task/Features/CreateTask/
├── CreateTaskAction.php       # Контроллер с #[Route]
├── CreateTaskMessage.php      # DTO + #[Assert] + #[OA\Property]
├── CreateTaskHandler.php      # Логика с бизнес-правилами
└── CreateTaskResponse.php    # Ответ (если нужен)
```

### 2. #[MapRequestPayload] (Обязательно)

```php
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class CreateTaskAction extends AbstractController
{
    #[Route('/api/tasks', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] CreateTaskMessage $message,
        CreateTaskHandler $handler
    ): TaskResponse {
        return $handler->handle($message);
    }
}
```

### 3. Doctrine ORM (Pragmatic)

**Допустимо** использовать EntityManager напрямую:
```php
readonly class ReorderTasksHandler
{
    public function __construct(private EntityManagerInterface $em) {}

    public function handle(ReorderTasksMessage $message): void
    {
        $connection = $this->em->getConnection();
        foreach ($message->orderedIds as $position => $id) {
            $connection->executeStatement(
                'UPDATE tasks SET position = ? WHERE id = ?',
                [$position, $id]
            );
        }
    }
}
```

---

## 📝 Правила Кода

### 1. DTO и Атрибуты

**КАЖДЫЙ** Message.php **ДОЛЖЕН** содержать атрибуты:
```php
#[OA\Schema(description: "Request to create a new task")]
final readonly class CreateTaskMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(example: "Fix login bug")]
        public string $title,

        #[Assert\Positive]
        #[OA\Property(example: 1)]
        public int $columnId,

        /** @var array<string> */
        #[Assert\All([new Assert\Type('string')])]
        #[OA\Property(type: "array", items: new OA\Items(type: "string"))]
        public array $tags = []
    ) {}
}
```

### 2. readonly Классы (Обязательно!)

**ВСЕ** DTO, Message, Response **ДОЛЖНЫ** быть readonly:
```php
#[OA\Schema(description: "Task response")]
final readonly class TaskResponse
{
    public function __construct(
        public int $id,
        public string $title,
        public string $status
    ) {}
}
```

### 3. Entities — Бизнес-Логика

```php
#[ORM\Entity]
class Task
{
    #[ORM\Column(type: 'string', enumType: TaskStatus::class)]
    private TaskStatus $status;

    public function move(TaskStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \DomainException(
                "Invalid transition from {$this->status->value} to {$newStatus->value}"
            );
        }
        $this->status = $newStatus;
    }
}
```

---

## 🔄 Коммуникация

### Messenger
```php
use Symfony\Component\Messenger\Attributes\AsMessage;

#[AsMessage]
final readonly class TaskCompletedEvent
{
    public function __construct(
        public int $taskId,
        public string $title
    ) {}
}
```

### Mercure (Real-time)
```php
readonly class CreateTaskHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ?HubInterface $hub = null
    ) {}

    public function handle(CreateTaskMessage $message): TaskResponse
    {
        // ... создание таски ...
        
        if ($this->hub !== null) {
            $update = new Update(
                "https://your-kanban.com/board/{$boardId}",
                json_encode(['event' => 'task_created', 'task' => [...]])
            );
            $this->hub->publish($update);
        }
        
        return TaskResponse::fromEntity($task);
    }
}
```

---

## 🗄️ База Данных

### Fractional Indexing (DECIMAL для Drag&Drop)
```sql
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    position DECIMAL(20, 10) NOT NULL DEFAULT 0,
    column_id INTEGER NOT NULL REFERENCES board_columns(id)
);

CREATE INDEX idx_tasks_position ON tasks(column_id, position);
```

### JSONB для Метаданных
```php
$task->setMetadata([
    'tags' => ['bug', 'high-priority'],
    'color' => '#ff6b6b',
    'checklist' => [['item' => 'Review code', 'done' => true]]
]);
```

---

## 🐳 Docker Commands

### Development
```bash
# Start services
docker compose up -d

# Install dependencies (ALWAYS inside Docker!)
docker compose exec frankenphp composer install

# Run tests
docker compose exec frankenphp ./vendor/bin/phpunit

# Clear cache
docker compose exec frankenphp php bin/console cache:clear

# Run migrations
docker compose exec frankenphp php bin/console doctrine:migrations:migrate
```

### Production Build
```bash
# Build production image
docker compose build --target php_prod

# Push to registry
docker compose push
```

---

## 📦 Composer.json Requirements

```json
{
    "require": {
        "php": ">=8.5",
        "symfony/framework-bundle": "^7.2",
        "symfony/messenger": "^7.2",
        "symfony/doctrine-bridge": "^7.2",
        "doctrine/orm": "^3.3",
        "predis/predis": "^2.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "zenstruck/messenger-test": "^1.6",
        "dama/doctrine-test-bundle": "^8.2",
        "phpstan/phpstan": "^2.1"
    }
}
```

---

## 🧪 Тестирование

### PHPUnit
```bash
docker compose exec frankenphp ./vendor/bin/phpunit --fail-fast
```

### Vitest (Frontend)
```bash
npm test
```

### GitHub Actions CI

**Просмотр логов CI:**
```bash
# Посмотреть последний failed run
gh run list --limit 1 --status failure

# Посмотреть логи конкретного run
gh run view <run-id> --log

# Ссылка на run
gh run list
```

---

## 🚀 Deployment

### Docker Build
```bash
# Production
docker build -t kanban-app:latest .

# Multi-stage build targets
docker build --target php_dev -t kanban-app:dev .
docker build --target php_prod -t kanban-app:prod .
```

### Zero-Downtime Deploy
```bash
#!/bin/bash
set -e

git pull origin main
docker compose build --pull php
docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
docker compose up -d --no-deps php
```

---

## ⚠️ Что НЕЛЬЗЯ

| ❌ Запрещено | ✅ Правильно |
|-------------|-------------|
| Возвращать Entity из контроллера | ResponseDTO |
| Интерфейсы Repository без необходимости | ServiceEntityRepository |
| static свойства | readonly stateless классы |
| Размазывать фичи по модулям | Одна папка = одна фича |
| Забывать `#[OA\Property]` | Каждый Message с атрибутами |
| Магические числа | Константы или Value Objects |

---

## 📁 Структура Проекта

```
├── src/
│   ├── Kernel.php              # Symfony MicroKernel
│   ├── Task/Features/          # Vertical Slices
│   ├── Board/Features/          # Vertical Slices
│   └── User/                   # Authentication
├── public/
│   ├── js/                     # Vue.js frontend
│   │   ├── components/
│   │   ├── stores/             # Pinia stores
│   │   └── kanban-realtime.js   # Mercure SSE
│   └── index.php
├── docker/
│   ├── php/                    # PHP configs (xdebug, optimizations)
│   ├── prometheus/             # Monitoring
│   └── grafana/                # Dashboards
├── config/
│   ├── routes.yaml
│   └── packages/
├── tests/                      # PHPUnit tests
├── .github/
│   └── workflows/
│       └── deploy.yml          # CI/CD pipeline
├── Caddyfile                   # FrankenPHP config
├── docker-compose.yml          # Development
├── Dockerfile                  # Multi-stage builds
└── composer.json
```

---

## ✅ Чеклист Перед Push

- [ ] Тесты проходят (`docker compose exec frankenphp ./vendor/bin/phpunit --fail-fast`)
- [ ] PHPStan не выдает ошибок
- [ ] Нет `dd()`, `var_dump()`
- [ ] Коммит по формату
- [ ] `#[OA\Property]` во всех MessageDTO
- [ ] Entity содержит бизнес-логику

---

**Помни:** Код пишется один раз, но читается сотни раз. Пиши для людей.
