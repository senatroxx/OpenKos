<?php

namespace App\Data\Lease;

use App\Enums\DepositSettlementStatus;
use Carbon\CarbonImmutable;

final readonly class DepositSettlementData
{
    /**
     * @param  array<int, DepositDeductionData>  $deductions
     */
    public function __construct(
        public DepositSettlementStatus $status,
        public CarbonImmutable $settlementDate,
        public string $refundAmount,
        public array $deductions = [],
        public ?string $refundReference = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array{status: string, settlement_date?: string|null, refund_amount?: string|int|null, deductions?: array<int, array{amount: string|int, reason: string, description?: string|null}>, refund_reference?: string|null, notes?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: DepositSettlementStatus::from($data['status']),
            settlementDate: CarbonImmutable::parse($data['settlement_date'] ?? now()->toDateString()),
            refundAmount: (string) ($data['refund_amount'] ?? '0'),
            deductions: array_map(
                fn (array $deduction): DepositDeductionData => DepositDeductionData::fromArray($deduction),
                $data['deductions'] ?? [],
            ),
            refundReference: $data['refund_reference'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
