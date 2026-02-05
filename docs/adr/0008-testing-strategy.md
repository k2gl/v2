# ADR 8: Testing Strategy

**Date:** 2026-02-06
**Status:** Proposed
**Owner:** QA Team

## Context

Choosing a testing framework for a PHP/Symfony project affects:
- Developer productivity
- Code quality gates
- AI agent compatibility
- CI/CD pipeline complexity

Options:
1. **PHPUnit** — Industry standard, mature, extensive ecosystem
2. **Pest** — Modern, expressive, PHP 8+ native, growing adoption

## Decision

We use **PHPUnit** as the primary testing framework.

## Rationale

### PHPUnit Advantages

| Factor | PHPUnit | Pest |
|--------|---------|------|
| **Symfony Integration** | Native support via Symfony Test framework | Requires Pest plugin |
| **AI Agent Compatibility** | Well-documented patterns, predictable structure | Less training data for AI |
| **Team Familiarity** | 95% of PHP developers know PHPUnit | Learning curve for newcomers |
| **CI/CD** | Universal support | Growing but not universal |
| **Static Analysis** | Full PHPStan compatibility | PHPStan requires configuration |

### Symfony Test Framework Integration

PHPUnit integrates seamlessly with Symfony:

```php
// KernelTestCase for container testing
abstract class KernelTestCase extends AbstractKernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }
}

// WebTestCase for HTTP testing
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateTaskControllerTest extends WebTestCase
{
    public function test_creates_task_successfully(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/tasks', [], [], [], json_encode([
            'title' => 'Test Task',
            'columnId' => 1,
        ]));

        self::assertResponseStatusCodeSame(201);
    }
}
```

### Messenger Testing

Zenstruck\Messenger\Test provides async testing for Messenger:

```php
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use App\Task\Features\CreateTask\CreateTaskCommand;

final class CreateTaskHandlerTest extends TestCase
{
    use InteractsWithMessenger;

    public function test_creates_task_and_dispatches_event(): void
    {
        // Given
        $this->bus()->dispatch(new CreateTaskCommand(
            title: 'New Task',
            columnId: 1,
        ));

        // Then
        $this->bus()->dispatched()->assertContains(CreateTaskCommand::class);
    }
}
```

### Pest Consideration

Pest is excellent for simple projects but has drawbacks for enterprise:

```
Pros:              Cons:
- Clean syntax     - Symfony plugin required
- Fast writing     - Less AI training data
- Modern           - Smaller community for edge cases
```

For an AI-Native project, PHPUnit's predictability outweighs Pest's syntax benefits.

## Testing Pyramid

```
        /\
       /E2E\        ← 10% — Full HTTP flow, WebTestCase
      /-----\ 
     /Integ.\      ← 30% — Database integration, Messenger
    /-------\ 
   / Unit   \     ← 60% — Handlers, Services, pure logic
  /_________\
```

### Test Types

| Type | Purpose | Framework | Isolation |
|------|---------|-----------|-----------|
| **Unit** | Business logic | PHPUnit | Full (mocks) |
| **Integration** | Persistence, Messenger | PHPUnit + Foundry | Database per test |
| **E2E** | HTTP endpoints | WebTestCase | Full kernel |

## Project Structure

```
tests/
├── Unit/
│   └── {Module}/
│       └── Features/
│           └── {FeatureName}/
│               └── {FeatureName}HandlerTest.php
├── Integration/
│   └── {Module}/
│       └── Features/
│           └── {FeatureName}/
│               └── {FeatureName}HandlerTest.php
└── EndToEnd/
    └── {Module}/
        └── Features/
            └── {FeatureName}/
                └── {FeatureName}ControllerTest.php
```

## Testing Standards

### Unit Test Pattern

```php
declare(strict_types=1);

namespace App\Tests\Unit\User\Features\Login;

use App\User\Features\Login\LoginHandler;
use App\User\Features\Login\LoginCommand;
use App\User\Features\Login\LoginResponse;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    public function test_returns_token_on_valid_credentials(): void
    {
        // Arrange
        $userRepository = $this->createMock(UserRepository::class);
        $handler = new LoginHandler($userRepository);
        $command = new LoginCommand('user@example.com', 'password123');

        // Act
        $response = $handler->handle($command);

        // Assert
        self::assertInstanceOf(LoginResponse::class, $response);
        self::assertNotEmpty($response->token);
    }
}
```

### Integration Test Pattern

```php
declare(strict_types=1);

namespace App\Tests\Integration\User\Features\Login;

use App\User\Entity\User;
use App\User\Features\Login\LoginCommand;
use App\User\Features\Login\LoginHandler;
use App\User\Features\Login\LoginResponse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LoginHandlerIntegrationTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function test_returns_token_for_existing_user(): void
    {
        // Given
        $user = User::create(email: 'user@example.com', password: 'hashed');
        $this->getContainer()->get(UserRepository::class)->save($user);

        $handler = $this->getContainer()->get(LoginHandler::class);
        $command = new LoginCommand('user@example.com', 'password123');

        // Act
        $response = $handler->handle($command);

        // Assert
        self::assertInstanceOf(LoginResponse::class, $response);
        self::assertNotEmpty($response->token);
    }
}
```

### E2E Test Pattern

```php
declare(strict_types=1);

namespace App\Tests\EndToEnd\User\Features\Login;

use App\User\Features\Login\LoginRequest;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    public function test_login_endpoint_returns_token(): void
    {
        // Given
        $client = self::createClient();

        // When
        $client->request('POST', '/api/login', [], [], [], json_encode([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]));

        // Then
        self::assertResponseStatusCodeSame(200);
        $content = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $content);
    }
}
```

## Test Configuration

### phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         beStrictAboutChangesToGlobalState="true"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">tests/Integration</directory>
        </testsuite>
        <testsuite name="EndToEnd">
            <directory suffix="Test.php">tests/EndToEnd</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="test"/>
    </php>
</phpunit>
```

### CI Configuration

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
          POSTGRES_DB: test
        ports: [5432:5432]
        options: >-
          --health-cmd pg_isready
          --health-cmd="psql -U test -d test"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: PHP Setup
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          tools: composer, phpunit

      - name: Install Dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run PHPUnit
        run: ./vendor/bin/phpunit --testdox

      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse --level=8 src/
```

## Tools & Libraries

| Tool | Purpose |
|------|---------|
| **phpunit/phpunit** | Core testing framework |
| **symfony/test-pack** | Symfony integration (WebTestCase, KernelTestCase) |
| **zenstruck/foundry** | Database fixtures, factories |
| **zenstruck/messenger-test** | Messenger/async testing |
| **phpstan/phpstan** | Static analysis |
| **php-cs-fixer** | Code style |

## Consequences

### Positive

1. **Predictable**: Standard PHPUnit patterns, well-documented
2. **AI-Friendly**: AI agents trained on PHPUnit examples
3. **Symfony Native**: Deep integration with Symfony ecosystem
4. **Mature**: 15+ years of bug fixes and optimizations

### Negative

1. **Boilerplate**: More verbose than Pest
2. **Learning Curve**: SetUp/tearDown patterns require understanding

## References

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Symfony Testing Guide](https://symfony.com/doc/current/testing.html)
- [Zenstruck Foundry](https://github.com/zenstruck/foundry)
- [Zenstruck Messenger Test](https://github.com/zenstruck/messenger-test)
