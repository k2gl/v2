<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

abstract readonly class AbstractDomainEvent
{
    private \DateTimeImmutable $occurredAt;

    final public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): ?string
    {
        return null;
    }

    public function toArray(): array
    {
        return [
            'event' => static::class,
            'occurred_at' => $this->occurredAt->format('c'),
            'payload' => $this->payload(),
        ];
    }

    abstract protected function payload(): array;
}
