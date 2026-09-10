<?php

namespace App\Services\DataTransfer;

final class ImportValidationContext
{
    /**
     * @param  array<string, mixed>  $constraints
     */
    public function __construct(public array $constraints = []) {}

    /**
     * @var array<int, array{line: int|null, field: string, message: string}>
     */
    public array $errors = [];

    /**
     * @var array<string, mixed>
     */
    public array $state = [];

    public function error(?int $line, string $field, string $message): void
    {
        $this->errors[] = [
            'line' => $line,
            'field' => $field,
            'message' => $message,
        ];
    }

    public function errorsSince(int $count): bool
    {
        return count($this->errors) > $count;
    }
}
