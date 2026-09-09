<?php

namespace App\Actions\Utility;

use App\Enums\InvoiceStatus;
use App\Enums\UtilityReadingKind;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\UtilityReading;
use App\Services\Payments\MoneyConverter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillUtilityReadings
{
    public function __construct(private MoneyConverter $money) {}

    public function execute(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice): int {
            $lockedInvoice = Invoice::query()
                ->with(['lease.unit'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if (in_array($lockedInvoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
                return 0;
            }

            $readings = UtilityReading::query()
                ->with(['meter', 'previousReading', 'correctsReading.invoiceLineItem'])
                ->whereHas('meter', fn ($query) => $query->where('unit_id', $lockedInvoice->lease->unit_id))
                ->whereDoesntHave('invoiceLineItem')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $billed = 0;

            foreach ($readings as $reading) {
                if ($reading->reading_kind === UtilityReadingKind::Correction) {
                    if ($reading->correctsReading?->invoiceLineItem?->invoice_id !== $lockedInvoice->id) {
                        continue;
                    }

                    if ($reading->currency !== $lockedInvoice->currency) {
                        continue;
                    }

                    $amount = $this->correctionAmount($reading);
                    $metadata = $this->correctionMetadata($reading);
                    $type = 'utility_adjustment';
                    $description = 'Utility correction · '.$reading->meter->identifier.' · '.$reading->period_start->format('F Y');
                } else {
                    if (! $this->eligibleForInvoice($reading, $lockedInvoice)) {
                        continue;
                    }

                    if ($reading->currency !== $lockedInvoice->currency) {
                        continue;
                    }

                    $amount = $this->chargeAmount((string) $reading->consumption, (string) $reading->rate, $reading->currency);
                    $metadata = $this->readingMetadata($reading);
                    $type = 'utility';
                    $description = $reading->meter->utility_type->value.' · '.$reading->consumption.' '.$reading->meter->measurement_unit.' · '.$reading->period_start->format('F Y');
                }

                $this->appendLineItem($lockedInvoice, $reading, $type, $description, $amount, $metadata);
                $billed++;
            }

            return $billed;
        });
    }

    private function eligibleForInvoice(UtilityReading $reading, Invoice $invoice): bool
    {
        /**
         * MVP eligibility is whole-period containment: the reading must be
         * inside both the invoice period and lease ownership window. A
         * previous reading from before the lease makes the consumption
         * unattributable, so it remains unbilled instead of being prorated.
         */
        $lease = $invoice->lease;
        $invoiceStart = CarbonImmutable::instance($invoice->period_start);
        $invoiceEnd = CarbonImmutable::instance($invoice->period_end);
        $leaseStart = CarbonImmutable::instance($lease->start_date);
        $leaseEnd = $lease->end_date
            ? CarbonImmutable::instance($lease->end_date)
            : null;
        $periodStart = CarbonImmutable::instance($reading->period_start);
        $periodEnd = CarbonImmutable::instance($reading->period_end);

        if ($periodStart->lt($invoiceStart) || $periodEnd->gt($invoiceEnd)) {
            return false;
        }

        if ($periodStart->lt($leaseStart) || ($leaseEnd !== null && $periodEnd->gt($leaseEnd))) {
            return false;
        }

        $previous = $reading->previousReading;

        return $previous === null
            || (
                CarbonImmutable::instance($previous->period_start)->gte($leaseStart)
                && ($leaseEnd === null || CarbonImmutable::instance($previous->period_end)->lte($leaseEnd))
            );
    }

    private function appendLineItem(
        Invoice $invoice,
        UtilityReading $reading,
        string $type,
        string $description,
        string $amount,
        array $metadata,
    ): void {
        InvoiceLineItem::query()->create([
            'invoice_id' => $invoice->id,
            'type' => $type,
            'description' => $description,
            'amount' => $amount,
            'utility_reading_id' => $reading->id,
            'metadata' => $metadata,
        ]);

        $total = BigDecimal::of((string) $invoice->total)
            ->plus($amount)
            ->toScale($this->money->scale($invoice->currency), RoundingMode::Unnecessary)
            ->toString();

        if (BigDecimal::of($total)->isNegative()) {
            throw ValidationException::withMessages([
                'reading' => __('This correction would make the invoice total negative.'),
            ]);
        }

        $invoice->update(['total' => $total]);
        $invoice->recalculateStatus();
    }

    private function chargeAmount(string $consumption, string $rate, string $currency): string
    {
        return $this->money->normalizeAmount(
            BigDecimal::of($consumption)->multipliedBy($rate)->toString(),
            $currency,
        );
    }

    private function correctionAmount(UtilityReading $reading): string
    {
        $original = $reading->correctsReading;

        return BigDecimal::of((string) $reading->adjustment_consumption)
            ->multipliedBy((string) $original->rate)
            ->toScale($this->money->scale($reading->currency), RoundingMode::HalfEven)
            ->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function readingMetadata(UtilityReading $reading): array
    {
        return [
            'meter_id' => $reading->meter->id,
            'meter_identifier' => $reading->meter->identifier,
            'utility_type' => $reading->meter->utility_type->value,
            'measurement_unit' => $reading->meter->measurement_unit,
            'reading_id' => $reading->id,
            'reading_kind' => $reading->reading_kind->value,
            'previous_reading' => (string) $reading->previous_reading,
            'current_reading' => (string) $reading->current_reading,
            'consumption' => (string) $reading->consumption,
            'rate' => (string) $reading->rate,
            'currency' => $reading->currency,
            'period_start' => $reading->period_start->toDateString(),
            'period_end' => $reading->period_end->toDateString(),
            'reading_reference' => $reading->reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function correctionMetadata(UtilityReading $reading): array
    {
        $metadata = $this->readingMetadata($reading);
        $original = $reading->correctsReading;

        return [
            ...$metadata,
            'adjustment_consumption' => (string) $reading->adjustment_consumption,
            'corrects_reading_id' => $original->id,
            'original_invoice_line_item_id' => $original->invoiceLineItem?->id,
            'original_consumption' => (string) $original->consumption,
            'original_amount' => (string) $original->invoiceLineItem?->amount,
        ];
    }
}
