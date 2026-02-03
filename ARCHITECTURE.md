# Pragmatic Symfony Architecture on FrankenPHP

## Overview

Production-ready Symfony 8.3+ architecture with Vertical Slice Architecture inside Modular Monolith, optimized for FrankenPHP Worker Mode.

## Architecture Principles

- **Vertical Slice**: Group code by features (Action + Message + Handler + Response)
- **Modular Monolith**: Independent domain modules with clear boundaries
- **Pragmatic DDD**: Simple entities in handlers, complex logic in Domain Services only
- **FrankenPHP Optimization**: Worker Mode, readonly classes, stateless services

## Project Structure

```
src/
├── Order/                          # Domain Module
│   ├── Entity/
│   │   └── Order.php
│   └── Features/
│       └── CreateOrder/            # Vertical Slice
│           ├── CreateOrderAction.php
│           ├── CreateOrderMessage.php
│           ├── CreateOrderHandler.php
│           └── OrderResponse.php
│
├── Billing/                        # Domain Module
│   └── ...
│
├── Catalog/                       # Domain Module
│   └── ...
│
└── SharedKernel/                  # Cross-module shared code
    ├── Event/
    │   └── OrderCreatedEvent.php  # Cross-module events
    ├── ValueObject/
    │   ├── Money.php
    │   └── Email.php
    └── Action/
        └── HealthAction.php
```

## Key Components

### DTO with Attributes (Self-Documenting)

```php
namespace App\Order\Features\CreateOrder;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(description: "Request to create an order")]
final readonly class CreateOrderMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(example: "719f979c-7033-4f93-8687-09a80695034c")]
        public string $customerId,

        #[Assert\Count(min: 1)]
        /** @var array<string> */
        #[OA\Property(type: "array", items: new OA\Items(type: "string"))]
        public array $itemIds,
    ) {}
}
```

### Vertical Slice Action

```php
namespace App\Order\Features\CreateOrder;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

final class CreateOrderAction extends AbstractController
{
    #[Route('/api/orders', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] CreateOrderMessage $message,
        CreateOrderHandler $handler
    ): OrderResponse {
        return $handler->handle($message);
    }
}
```

### Handler with Events

```php
namespace App\Order\Features\CreateOrder;

use App\Order\Entity\Order;
use App\SharedKernel\Event\OrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateOrderHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus
    ) {}

    public function handle(CreateOrderMessage $message): OrderResponse
    {
        $order = new Order($message->customerId, $message->itemIds);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->eventBus->dispatch(
            new OrderCreatedEvent($order->getId(), $message->customerId)
        );

        return new OrderResponse($order->getId(), 'created');
    }
}
```

## Infrastructure

### FrankenPHP Configuration

**Caddyfile** (`docker/frankenphp/Caddyfile`):
```caddy
{
    frankenphp
    admin off
    log {
        level INFO
        format json
    }
}

:80 {
    root * public/
    encode zstd gzip

    redir /docs /docs/ 301

    @options { method OPTIONS }
    respond @options 204

    header {
        Access-Control-Allow-Origin *
        Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
        Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    }

    file_server
    php_server {
        index index.php
    }
}
```

### Docker Configuration

**PHP Optimization** (`docker/php/conf.d/app.ini`):
```ini
opcache.enable_cli=1
opcache.validate_timestamps=0
opcache.memory_consumption=256
realpath_cache_size=4096K
realpath_cache_ttl=600
```

### Environment Variables

```env
APP_ENV=prod
APP_SECRET=your-secret
DATABASE_URL="postgresql://symfony:password@db:5432/symfony?serverVersion=15&charset=utf8"
MESSENGER_TRANSPORT_DSN="redis://redis:6379/messages"
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
```

## Documentation Pipeline

### Build-Time Generation

```bash
php bin/console nelmio:apidoc:dump --format=yaml > public/openapi.yaml
```

### Dockerfile Integration

```dockerfile
RUN APP_ENV=prod php bin/console nelmio:apidoc:dump --format=yaml > public/openapi.yaml
```

### Swagger UI

Static HTML at `public/docs/index.html`:
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>API Documentation</title>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/openapi.yaml',
                dom_id: '#swagger-ui',
            });
        };
    </script>
</body>
</html>
```

## CI/CD Pipeline

**GitHub Actions** (`.github/workflows/openapi_check.yaml`):
```yaml
name: OpenAPI Drift Check

on: [push, pull_request]

jobs:
  openapi_lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install
      - name: Generate OpenAPI
        run: php bin/console nelmio:apidoc:dump --format=yaml > openapi_temp.yaml
      - name: Drift Check
        run: diff public/openapi.yaml openapi_temp.yaml
      - name: Lint OpenAPI
        uses: charliemclaughlin/openapi-validator-action@v2
        with:
          openapi-relative-path: public/openapi.yaml
```

## Health Checks

```php
namespace App\SharedKernel\Action;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final readonly class HealthAction {
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/health', methods: ['GET'])]
    public function __invoke(): JsonResponse {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            return new JsonResponse(['status' => 'ok', 'worker_mode' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error'], 500);
        }
    }
}
```

## Testing Strategy

### Integration Tests

```php
namespace App\Tests\Order\Features\CreateOrder;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\SharedKernel\Event\OrderCreatedEvent;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class CreateOrderTest extends WebTestCase
{
    use InteractsWithMessenger;

    public function testOrderIsCreatedSuccessfully(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/orders', content: json_encode([
            'customerId' => '719f979c-7033-4f93-8687-09a80695034c',
            'itemIds' => ['product-1', 'product-2']
        ]));

        $this->assertResponseIsSuccessful();
        $this->messenger('event.bus')->queue()->assertContains(OrderCreatedEvent::class);
    }
}
```

## Developer Commands

```makefile
init:           build up db-migrate docs  # Initialize project from scratch
build:          docker-compose build
up:            docker-compose up -d
down:          docker-compose down
db-migrate:    docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
docs:          docker-compose exec php bin/console nelmio:apidoc:dump --format=yaml > public/openapi.yaml
test:          docker-compose exec php bin/phpunit
vendor:        docker-compose exec php composer install
shell:         docker-compose exec php sh
clean:         docker-compose exec php bin/console cache:clear && rm -rf var/log/*
```

## Performance Characteristics

| Metric | Expected Improvement |
|--------|---------------------|
| Response Time | 2-4x faster than PHP-FPM |
| Memory Usage | Lower due to Worker Mode |
| Static Files | Zero PHP overhead (Caddy file_server) |
| OpenAPI Docs | Pre-generated at build time |
| Cold Starts | Eliminated (application stays in memory) |

## Quick Start

```bash
make init
# Open http://localhost/docs for Swagger UI
# API available at http://localhost/api/orders
```

## Summary

This architecture provides:
- **High Performance**: FrankenPHP Worker Mode + Caddy HTTP/3
- **Developer Experience**: Self-documenting DTOs, Makefile automation
- **Reliability**: Integration tests, health checks, CI validation
- **Maintainability**: Vertical Slice + Modular Monolith
