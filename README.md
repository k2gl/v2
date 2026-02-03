# 🚀 Kanban Pragmatic Architecture (Symfony + FrankenPHP + Vue)

## 📌 О Проекте

Высокопроизводительный Kanban-сервис, построенный на базе **Modular Monolith** с применением **Vertical Slice Architecture**.

### Технологический стек:

| Layer | Technology |
|-------|------------|
| **Backend** | Symfony 7, PHP 8.3 (FrankenPHP Worker Mode) |
| **Database** | PostgreSQL 15 (Fractional Indexing, JSONB) |
| **Real-time** | Mercure Hub (SSE) |
| **Auth** | JWT (LexikJWT) + GitHub OAuth2 |
| **Frontend** | Vue 3, Pinia, vuedraggable |
| **Monitoring** | Sentry (full-stack tracing) |
| **CI/CD** | GitHub Actions + Docker |

---

## 🏗 Архитектурные Правила

### 1. Vertical Slice (Срезы)

Весь код фичи живет в одной папке: `src/[Module]/Features/[FeatureName]/`.

```
src/Task/Features/MoveTask/
├── MoveTaskAction.php      # Контроллер (входная точка)
├── MoveTaskMessage.php     # DTO (Запрос) + Атрибуты OpenAPI/Assert
├── MoveTaskHandler.php     # Бизнес-логика
└── MoveTaskResponse.php   # DTO (Ответ)
```

**Обязательные компоненты:**
- **Action.php** — контроллер с маршрутом и атрибутами
- **Message.php** — DTO с валидацией и документацией
- **Handler.php** — логика обработки с зависимостями
- **Response.php** — структурированный ответ (опционально)

### 2. Pragmatic DDD

**Entities** — содержат бизнес-логику и атрибуты Doctrine:
```php
#[ORM\Entity]
class Task
{
    #[ORM\Column(type: 'string', enumType: TaskStatus::class)]
    private TaskStatus $status;
    
    public function move(TaskStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \DomainException(...);
        }
        $this->status = $newStatus;
    }
}
```

**Value Objects** — для статусов и сложных типов:
```php
enum TaskStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function canTransitionTo(self $target): bool { ... }
}
```

**SharedKernel** — единственное место для обмена данными между модулями:
```
src/SharedKernel/Event/
├── OrderCreatedEvent.php
└── TaskCompletedEvent.php
```

### 3. OpenAPI & Автодокументация

Документация генерируется автоматически из атрибутов в `Message.php`:

```php
#[OA\Schema(description: "Request to move task between columns")]
final readonly class MoveTaskMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(example: 1)]
        public int $taskId,

        #[Assert\NotNull]
        #[OA\Property(example: "todo", enum: {...})]
        public TaskStatus $newStatus
    ) {}
}
```

**Команды:**
```bash
make docs                    # Обновить схему
php bin/console nelmio:apidoc:dump --format=yaml > public/openapi.yaml
```

**Доступ:**
- Swagger UI: `http://localhost/docs`
- OpenAPI spec: `http://localhost/openapi.yaml`

### 4. PostgreSQL & Fractional Indexing

**Структура таблиц:**
```sql
-- Tasks с дробным позиционированием
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    position DECIMAL(20, 10) NOT NULL DEFAULT 0,
    metadata JSONB DEFAULT '{}'
);

-- Индексы для быстрого поиска
CREATE INDEX idx_tasks_position ON tasks(column_id, position);
CREATE INDEX idx_tasks_metadata ON tasks USING GIN (metadata);
```

**Drag&Drop без перезаписи:**
```php
// Вставить между позициями 1 и 2: новая позиция = 1.5
$newPosition = ($prevPos + $nextPos) / 2;
```

### 5. Real-time (Mercure Hub)

**Публикация из Handler:**
```php
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

readonly class MoveTaskHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ?HubInterface $hub = null
    ) {}

    public function handle(MoveTaskMessage $message): void
    {
        // ... логика обновления ...
        
        if ($this->hub !== null) {
            $update = new Update(
                "https://your-kanban.com/board/{$boardId}",
                json_encode(['event' => 'task_moved', ...])
            );
            $this->hub->publish($update);
        }
    }
}
```

**Подписка на фронтенде:**
```javascript
const url = new URL('/.well-known/mercure', baseUrl);
url.searchParams.append('topic', `/board/${boardId}`);
const eventSource = new EventSource(url);
```

---

## 🛠 Команды Разработки

### Docker & Инфраструктура
```bash
make init              # Полный запуск проекта с нуля
make build            # Сборка контейнеров
make up               # Запуск контейнеров
make down             # Остановка контейнеров
make restart          # Перезапуск
make shell            # Вход в контейнер PHP
```

### База Данных
```bash
make db-migrate       # Применение миграций
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Документация
```bash
make docs            # Генерация OpenAPI схемы
```

### Тестирование
```bash
make test            # Запуск всех тестов
php bin/phpunit
```

### Кэш & Очистка
```bash
make clean           # Очистка кэша и логов
docker-compose down -v
```

---

## 🚦 Gitflow & Workflow

### Ветки
| Branch | Purpose |
|--------|---------|
| `main` | Стабильная ветка. Деплой происходит автоматически |
| `develop` | Следующий релиз (опционально) |
| `feature/*` | Новые фичи |
| `fix/*` | Исправления багов |
| `refactor/*` | Рефакторинг |

### Формат Коммитов
```
<type>(<module>): <description>

feat(task): add move-to-archive functionality
fix(board): fix column ordering bug
docs(api): update OpenAPI specification
refactor(auth): simplify JWT handling
```

### Типы Коммитов
| Type | Description |
|------|-------------|
| `feat` | Новая функциональность |
| `fix` | Исправление бага |
| `refactor` | Рефакторинг кода |
| `docs` | Изменения в документации |
| `test` | Добавление/изменение тестов |
| `chore` | Обновление зависимостей, CI/CD |

---

## 📁 Структура Проекта

```
src/
├── Board/                   # Модуль досок
│   ├── Entity/
│   │   ├── Board.php
│   │   └── Column.php
│   ├── Repository/
│   │   └── BoardRepository.php
│   └── Features/
│       ├── GetBoard/
│       └── CreateBoard/
│
├── Task/                    # Модуль задач
│   ├── Entity/
│   │   └── Task.php
│   ├── Domain/
│   │   └── ValueObject/
│   │       └── TaskStatus.php
│   ├── Repository/
│   │   └── TaskRepository.php
│   └── Features/
│       ├── MoveTask/
│       └── ReorderTasks/
│
├── User/                    # Модуль пользователей
│   ├── Entity/
│   │   └── User.php
│   ├── Features/
│   │   ├── Login/
│   │   └── GitHubAuth/
│   └── Infrastructure/
│       └── EventListener/
│           └── JWTCreatedListener.php
│
└── SharedKernel/
    └── Event/
        └── TaskCompletedEvent.php

public/
├── js/
│   ├── stores/             # Pinia stores
│   ├── components/         # Vue components
│   └── auth/              # Auth utilities
└── docs/
    └── index.html         # Swagger UI

config/
├── packages/
│   ├── security.yaml
│   ├── sentry.yaml
│   └── doctrine.yaml
└── routes.yaml

docker/
├── frankenphp/
│   └── Caddyfile
└── php/
    └── conf.d/
        └── app.ini

.github/
└── workflows/
    ├── openapi_check.yaml
    └── deploy.yml
```

---

## 🔐 Безопасность

### JWT + Refresh Tokens
```yaml
# config/packages/security.yaml
firewalls:
    login:
        pattern: ^/api/login
        json_login:
            check_path: /api/login_check
    
    refresh:
        pattern: ^/api/token/refresh
        refresh_jwt: ~
    
    api:
        pattern: ^/api
        jwt: ~
```

### GitHub OAuth2
```bash
composer require knpuniversity/oauth2-client-bundle league/oauth2-github
```

---

## 📊 Мониторинг & Логирование

### Sentry (Backend + Frontend)
```bash
composer require sentry/sentry-symfony
npm install @sentry/vue
```

**Теги для Vertical Slices:**
```php
// В ошибках автоматически проставляется module и feature
$scope->setTag('module', 'Task');
$scope->setTag('feature', 'MoveTask');
```

### Health Check
```bash
curl http://localhost/health
```

---

## 🚀 Развертывание (Deployment)

### Docker Build
```bash
docker compose build --pull php
docker compose up -d php
```

### Zero-Downtime Deploy
```bash
# Скрипт: scripts/deploy.sh
1. git pull
2. docker compose build
3. doctrine:migrations:migrate
4. cache:clear && cache:warmup
5. nelmio:apidoc:dump
6. docker compose up -d --no-deps php
```

### CI/CD Pipeline
```yaml
# .github/workflows/deploy.yml
jobs:
  - lint        # PHP CS Fixer, PHPStan
  - test        # PHPUnit
  - openapi     # Drift check
  - security    # Composer audit
  - deploy      # SSH deploy
```

---

## ✨ Лучшие Практики

### 1. Работа с БД (Pragmatic)
```php
// В Handler допустимо использовать EntityManager напрямую
// для простых операций (без создания отдельных сервисов)
readonly class MoveTaskHandler
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function handle(MoveTaskMessage $message): void
    {
        $connection = $this->em->getConnection();
        // Прямой SQL для массовых операций
        $connection->executeStatement(...);
    }
}
```

### 2. API Response (Не отдавайте Entity!)
```php
// ПРАВИЛЬНО: Создаем ResponseDTO
final class TaskResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $status
    ) {}
}

// НЕПРАВИЛЬНО: Return Entity напрямую
// return $task; // Entity может содержать лишние данные
```

### 3. Worker Mode (Stateless)
```php
// ВСЕ сервисы должны быть stateless
// Никаких static свойств
// Никакого кэша в памяти между запросами

readonly class TaskService  // readonly = stateless
{
    public function __construct(
        private TaskRepository $repository,
        private MessageBusInterface $eventBus
    ) {}  // Не хранит состояния!
}
```

---

## 📝 Полезные Ссылки

| Resource | URL |
|----------|-----|
| Swagger UI | http://localhost/docs |
| OpenAPI Spec | http://localhost/openapi.yaml |
| Health Check | http://localhost/health |
| GitHub Repo | https://github.com/your-org/kanban-project |

---

## 🏆 Итог

Этот проект обеспечивает:

- **Высокую производительность** (FrankenPHP Worker Mode)
- **Чистую архитектуру** (Vertical Slice + Modular Monolith)
- **Type Safety** (Full TypeScript из OpenAPI)
- **Real-time** (Mercure Hub)
- **Security** (JWT + GitHub OAuth)
- **Мониторинг** (Sentry full-stack tracing)
- **CI/CD** (Автоматический деплой)

**Помни:** Код пишется для людей, а не для машин. Следуй архитектуре, и проект будет масштабироваться без боли.
