<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;

trait Timestampable
{
    private ?DateTimeImmutable $createdAt = null;

    private ?DateTimeImmutable $updatedAt = null;

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @internal */
    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /** @internal */
    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
