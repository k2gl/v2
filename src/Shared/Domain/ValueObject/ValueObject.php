<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

abstract class ValueObject
{
    abstract public function equals(mixed $other): bool;

    public function isNull(): bool
    {
        return false;
    }
}
