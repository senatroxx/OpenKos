<?php

namespace App\Results\Application;

final readonly class ApplicationResult
{
    public function __construct(public bool $successful, public ?string $error = null, public mixed $value = null) {}

    public function succeeded(): bool
    {
        return $this->successful;
    }

    public function failed(): bool
    {
        return ! $this->successful;
    }

    public static function success(mixed $value = null): self
    {
        return new self(true, null, $value);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }
}
