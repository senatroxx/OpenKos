<?php

namespace App\Data\Lease;

final readonly class DepositDeductionData
{
    public function __construct(
        public string $amount,
        public string $reason,
        public ?string $description = null,
    ) {}

    /**
     * @param  array{amount: string|int, reason: string, description?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (string) $data['amount'],
            reason: $data['reason'],
            description: $data['description'] ?? null,
        );
    }
}
