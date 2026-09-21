<?php

namespace App\Results\Unit;

final readonly class DeleteUnitResult
{
    public function __construct(public bool $deleted) {}

    public function succeeded(): bool
    {
        return $this->deleted;
    }

    public function failed(): bool
    {
        return ! $this->deleted;
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function blocked(): self
    {
        return new self(false);
    }
}
