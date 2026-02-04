<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Event\AbstractDomainEvent;

abstract class AbstractAggregateRoot
{
    /** @var AbstractDomainEvent[] */
    private array $events = [];

    final public function __construct()
    {
    }

    protected function recordEvent(AbstractDomainEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return AbstractDomainEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        
        return $events;
    }

    public function pullEvents(): iterable
    {
        while ($event = array_shift($this->events)) {
            yield $event;
        }
    }
}
