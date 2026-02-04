<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

class Email extends ValueObject
{
    public function __construct(
        private readonly string $value
    ) {
        $this->validate();
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(mixed $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    private function validate(): void
    {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                sprintf('Invalid email address: %s', $this->value)
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
