<?php

namespace App\Results\Unit;

final readonly class BulkAssignUnitTypeResult
{
    public function __construct(
        public ?string $error = null,
        public ?string $errorField = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->error === null;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public static function success(): self
    {
        return new self;
    }

    public static function error(string $error, string $errorField): self
    {
        return new self(error: $error, errorField: $errorField);
    }
}
