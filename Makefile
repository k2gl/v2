.PHONY: help shell install test lint migrate

# Default UID/GID for proper file permissions
UID := 1000
GID := 1000

help:
	@echo "Available commands:"
	@echo "  make shell      - Access PHP container shell"
	@echo "  make install    - Install PHP dependencies"
	@echo "  make test       - Run PHPUnit tests"
	@echo "  make lint       - Run PHP CS Fixer"
	@echo "  make analyze    - Run PHPStan analysis"
	@echo "  make migrate    - Run database migrations"
	@echo "  make up         - Start Docker containers"
	@echo "  make down       - Stop Docker containers"

shell:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp sh

install:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp composer install --no-scripts

test:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp ./vendor/bin/phpunit --fail-fast

lint:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp ./vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp ./vendor/bin/php-cs-fixer fix

analyze:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp ./vendor/bin/phpstan analyze src/ --level=5

migrate:
	UID=$(UID) GID=$(GID) docker compose exec frankenphp php bin/console doctrine:migrations:migrate --no-interaction

up:
	UID=$(UID) GID=$(GID) docker compose up -d

down:
	UID=$(UID) GID=$(GID) docker compose down

build:
	UID=$(UID) GID=$(GID) docker compose build --pull

logs:
	UID=$(UID) GID=$(GID) docker compose logs -f frankenphp
