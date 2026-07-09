<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
])]
class Invoice extends Model
{
    use Auditable, HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if ($invoice->reference === null) {
                // ponytail: orderByRaw sorts by length then value so variable-
                // width suffixes (e.g. 9999 → 10000) order correctly.
                // lockForUpdate serializes existing-row reads; the unique
                // constraint on `reference` guards the initial-gap race.
                $prefix = Setting::get('invoice_id_prefix') ?? 'INV';
                $year = now()->format('Y');
                $pattern = $prefix.$year.'%';

                $max = static::where('reference', 'like', $pattern)
                    ->orderByRaw('LENGTH(reference) DESC, reference DESC')
                    ->lockForUpdate()
                    ->value('reference');

                $seq = $max ? (int) substr($max, -4) + 1 : 1;

                $invoice->reference = $prefix.$year.str_pad((string) $seq, max(4, strlen((string) $seq)), '0', STR_PAD_LEFT);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
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

    public function getOutstandingAttribute(): string
    {
        return number_format((float) $this->total - (float) $this->amount_paid, 2, '.', '');
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

    public function recalculateStatus(): void
    {
        if (in_array($this->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            return;
        }

        $paid = (float) $this->payments()
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
