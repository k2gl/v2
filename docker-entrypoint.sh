#!/bin/sh
set -e

echo "========================================="
echo "FrankenPHP Symfony Entrypoint"
echo "========================================="

wait_for_database() {
    echo "[entrypoint] Waiting for database to be ready..."
    max_attempts=60
    attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
            echo "[entrypoint] Database is ready!"
            return 0
        fi

        attempt=$((attempt + 1))
        echo "[entrypoint] Waiting for database... (attempt $attempt/$max_attempts)"
        sleep 1
    done

    echo "[entrypoint] ERROR: Database did not become ready in time"
    return 1
}

wait_for_redis() {
    echo "[entrypoint] Waiting for Redis to be ready..."
    max_attempts=30
    attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if redis-cli -h redis ping > /dev/null 2>&1; then
            echo "[entrypoint] Redis is ready!"
            return 0
        fi

        attempt=$((attempt + 1))
        echo "[entrypoint] Waiting for Redis... (attempt $attempt/$max_attempts)"
        sleep 1
    done

    echo "[entrypoint] WARNING: Redis did not become ready, continuing..."
    return 0
}

run_migrations() {
    echo "[entrypoint] Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=$APP_ENV
    echo "[entrypoint] Migrations completed"
}

warmup_cache() {
    if [ "$APP_ENV" = 'prod' ]; then
        echo "[entrypoint] Warming up cache..."
        php bin/console cache:warmup --env=prod 2>/dev/null || true
    fi
}

run_scheduler() {
    if [ "$SCHEDULER_ENABLED" = 'true' ]; then
        echo "[entrypoint] Starting Symfony Scheduler..."
        while true; do
            php bin/console schedule:run --no-interaction --env=$APP_ENV
            sleep 60
        done &
    fi
}

healthcheck_handler() {
    echo "[healthcheck] Running health check..."

    if ! php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
        echo "[healthcheck] FAIL: Database connection failed"
        exit 1
    fi

    if ! curl -sf "http://localhost/health" > /dev/null 2>&1; then
        echo "[healthcheck] FAIL: FrankenPHP not responding"
        exit 1
    fi

    echo "[healthcheck] OK: All systems operational"
    exit 0
}

case "$1" in
    healthcheck)
        healthcheck_handler
        ;;
    *)
        if [ "$APP_ENV" = 'prod' ]; then
            wait_for_database || exit 1
            wait_for_redis || true
            run_migrations
            warmup_cache
        fi

        run_scheduler &

        echo "========================================="
        echo "Starting FrankenPHP..."
        echo "========================================="

        exec docker-php-entrypoint "$@"
        ;;
esac
