<?php

namespace App\Data\DataTransfer;

final readonly class ImportValidationResult
{
    /**
     * @param  array<int, array{line: int|null, field: string, message: string}>  $errors
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public int $rowCount,
        public array $rows,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array{valid: bool, row_count: int, error_count: int, errors: array<int, array{line: int|null, field: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'row_count' => $this->rowCount,
            'error_count' => count($this->errors),
            'errors' => $this->errors,
        ];
    }
}
