<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lease_id',
    'reference',
    'period_start',
    'period_end',
    'due_date',
    'status',
    'total',
    'amount_paid',
    'invoice_pdf_fingerprint',
])]
class Invoice extends Model
{
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if ($invoice->reference === null) {
                $invoice->reference = static::nextReferences(1)[0];
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public static function nextReferences(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $prefix = Setting::get('invoice_id_prefix') ?? 'INV';
        $year = now()->format('Y');
        $pattern = $prefix.$year.'%';

        // ponytail: one locked max lookup allocates a batch; retry the batch
        // when another database connection wins the initial empty-table race.
        $max = static::query()
            ->where('reference', 'like', $pattern)
            ->orderByRaw('LENGTH(reference) DESC, reference DESC')
            ->lockForUpdate()
            ->value('reference');

        $startingSequence = $max ? (int) substr($max, strlen($prefix.$year)) + 1 : 1;

        return collect(range($startingSequence, $startingSequence + $count - 1))
            ->map(fn (int $sequence): string => $prefix.$year.str_pad(
                (string) $sequence,
                max(4, strlen((string) $sequence)),
                '0',
                STR_PAD_LEFT,
            ))
            ->all();
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'status' => InvoiceStatus::class,
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function getOutstandingAttribute(): string
    {
        return number_format((float) $this->total - (float) $this->amount_paid, 2, '.', '');
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status->value;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)
            && $this->due_date->endOfDay()->isPast();
    }

    public function scopePayable(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value]);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->payable()->whereDate('due_date', '<', now());
    }

    /**
     * Recalculate a collection of already-locked invoices from confirmed payments.
     *
     * @param  Collection<int, self>  $invoices
     */
    public static function recalculateStatuses(Collection $invoices): void
    {
        if ($invoices->isEmpty()) {
            return;
        }

        $confirmedTotals = Payment::query()
            ->whereIn('invoice_id', $invoices->modelKeys())
            ->where('status', PaymentStatus::Confirmed->value)
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id');

        foreach ($invoices as $invoice) {
            $invoice->recalculateStatus((float) ($confirmedTotals[$invoice->getKey()] ?? 0));
        }
    }

    public function recalculateStatus(?float $confirmedPaymentTotal = null): void
    {
        if (in_array($this->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            return;
        }

        $paid = $confirmedPaymentTotal ?? (float) $this->payments()
            ->where('status', PaymentStatus::Confirmed->value)
            ->sum('amount');

        $this->update([
            'amount_paid' => $paid,
            'status' => match (true) {
                $paid >= (float) $this->total => InvoiceStatus::Paid,
                $paid > 0 => InvoiceStatus::Partial,
                default => InvoiceStatus::Pending,
            },
        ]);
    }
}
