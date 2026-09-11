<?php

namespace App\Actions\Leases;

use App\Data\Lease\DepositDeductionData;
use App\Data\Lease\DepositSettlementData;
use App\Enums\DepositSettlementStatus;
use App\Enums\LeaseStatus;
use App\Models\DepositSettlement;
use App\Models\Lease;
use App\Services\Payments\MoneyConverter;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettleLeaseDeposit
{
    public function __construct(private readonly MoneyConverter $money) {}

    public function execute(Lease $lease, DepositSettlementData $data): DepositSettlement
    {
        try {
            return DB::transaction(function () use ($lease, $data): DepositSettlement {
                $lockedLease = Lease::query()
                    ->whereKey($lease->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $lockedLease->status === LeaseStatus::Terminated,
                    422,
                    __('Only terminated leases can have a deposit settlement.'),
                );

                $settlement = DepositSettlement::query()
                    ->where('lease_id', $lockedLease->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($settlement?->status === DepositSettlementStatus::Settled) {
                    throw ValidationException::withMessages([
                        'settlement' => __('This deposit settlement is already settled and cannot be changed.'),
                    ]);
                }

                if ($settlement) {
                    $settlement->deductions()->lockForUpdate()->get();
                    $currency = $this->money->normalizeCurrency((string) $settlement->currency);
                    $originalAmount = $this->money->normalizeAmount(
                        (string) $settlement->original_amount,
                        $currency,
                    );
                } else {
                    $currency = $this->money->normalizeCurrency($lockedLease->currency);

                    if ($lockedLease->deposit_amount === null || trim((string) $lockedLease->deposit_amount) === '') {
                        throw ValidationException::withMessages([
                            'settlement' => __('A lease with no security deposit cannot be settled.'),
                        ]);
                    }

                    $originalAmount = $this->money->normalizeAmount(
                        (string) $lockedLease->deposit_amount,
                        $currency,
                    );

                    if ($this->money->compare($originalAmount, '0') === 0) {
                        throw ValidationException::withMessages([
                            'settlement' => __('A lease with no security deposit cannot be settled.'),
                        ]);
                    }
                }

                $refundAmount = $this->normalizeAmount($data->refundAmount, $currency, 'refund_amount');
                $deductions = array_map(
                    fn (DepositDeductionData $deduction): array => [
                        'amount' => $this->normalizeAmount($deduction->amount, $currency, 'deductions'),
                        'reason' => trim($deduction->reason),
                        'description' => $deduction->description,
                    ],
                    $data->deductions,
                );

                foreach ($deductions as $deduction) {
                    if ($deduction['reason'] === '') {
                        throw ValidationException::withMessages([
                            'deductions' => __('Each deduction must include a reason.'),
                        ]);
                    }
                }

                $deductionsTotal = array_reduce(
                    $deductions,
                    fn (BigDecimal $total, array $deduction): BigDecimal => $total->plus($deduction['amount']),
                    BigDecimal::zero(),
                );
                $allocatedAmount = BigDecimal::of($refundAmount)->plus($deductionsTotal);
                $original = BigDecimal::of($originalAmount);

                if ($allocatedAmount->compareTo($original) > 0) {
                    throw ValidationException::withMessages([
                        'settlement' => __('The refund and deductions cannot exceed the original deposit.'),
                    ]);
                }

                if ($data->status === DepositSettlementStatus::Settled && $allocatedAmount->compareTo($original) !== 0) {
                    throw ValidationException::withMessages([
                        'settlement' => __('A settled deposit must allocate the full original deposit as a refund or deduction.'),
                    ]);
                }

                $settlement ??= DepositSettlement::create([
                    'lease_id' => $lockedLease->getKey(),
                    'original_amount' => $originalAmount,
                    'currency' => $currency,
                    'status' => DepositSettlementStatus::Draft,
                    'settlement_date' => $data->settlementDate,
                    'refund_amount' => $refundAmount,
                    'refund_reference' => $data->refundReference,
                    'notes' => $data->notes,
                ]);

                $settlement->update([
                    'settlement_date' => $data->settlementDate,
                    'refund_amount' => $refundAmount,
                    'refund_reference' => $data->refundReference,
                    'notes' => $data->notes,
                ]);

                $settlement->deductions()
                    ->lockForUpdate()
                    ->get()
                    ->each
                    ->delete();

                foreach ($deductions as $deduction) {
                    $settlement->deductions()->create($deduction);
                }

                $settlement->update(['status' => $data->status]);

                return $settlement->load('deductions');
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($this->isSettlementUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'settlement' => __('A deposit settlement already exists for this lease.'),
                ]);
            }

            throw $exception;
        }
    }

    private function normalizeAmount(string $amount, string $currency, string $attribute): string
    {
        try {
            return $this->money->normalizeAmount($amount, $currency);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                $attribute => __('The :attribute is invalid for the settlement currency.', ['attribute' => $attribute]),
            ]);
        }
    }

    private function isSettlementUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $isUniqueViolation = match (DB::getDriverName()) {
            'pgsql' => (string) ($errorInfo[0] ?? $exception->getCode()) === '23505',
            'mysql', 'mariadb' => (int) ($errorInfo[1] ?? 0) === 1062,
            'sqlite' => (int) ($errorInfo[1] ?? 0) === 19,
            default => false,
        };

        $message = strtolower($exception->getMessage());

        return $isUniqueViolation && (
            str_contains($message, 'deposit_settlements_lease_id_unique')
            || (DB::getDriverName() === 'sqlite' && str_contains($message, 'deposit_settlements.lease_id'))
        );
    }
}
