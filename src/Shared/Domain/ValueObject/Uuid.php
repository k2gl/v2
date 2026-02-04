<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

class Uuid extends ValueObject
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
        return $other instanceof self && $this->value === $other->value;
    }

    public static function generate(): self
    {
        return new self(sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        ));
    }

    private function validate(): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid UUID: %s', $this->value)
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
