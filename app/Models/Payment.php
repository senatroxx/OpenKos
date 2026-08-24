<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\PaymentStatus;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'invoice_id',
    'amount',
    'currency',
    'payment_date',
    'payment_method',
    'reference_number',
    'notes',
    'status',
    'confirmed_by',
    'recorded_by',
    'verified_by',
    'verified_at',
])]
class Payment extends Model
{
    use Auditable, HasFactory, HasMedia, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'payment_date' => 'date:Y-m-d',
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $currency = $payment->getAttributeFromArray('currency');

            if ($currency === null && $payment->invoice_id !== null) {
                $currency = Invoice::query()->whereKey($payment->invoice_id)->value('currency');
            }

            $payment->currency = app(MoneyConverter::class)->normalizeCurrency($currency);
        });

        static::updating(function (Payment $payment): void {
            if ($payment->isDirty('currency')) {
                throw new LogicException('Payment currency cannot be changed after creation.');
            }
        });
    }

    public function getCurrencyAttribute(?string $value): string
    {
        return app(MoneyConverter::class)->normalizeCurrency($value);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentAttempt(): HasOne
    {
        return $this->hasOne(PaymentAttempt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
