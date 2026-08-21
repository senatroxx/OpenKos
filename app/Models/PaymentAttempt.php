<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Services\Payments\MoneyConverter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;
use OpenKOS\Core\Enums\PaymentStatus;

#[Fillable([
    'invoice_id',
    'payment_id',
    'gateway_key',
    'reference',
    'provider_reference',
    'amount',
    'currency',
    'status',
    'expires_at',
    'checkout_instructions',
    'metadata',
    'initiated_at',
    'settled_at',
    'failed_at',
    'expired_at',
    'canceled_at',
])]
class PaymentAttempt extends Model
{
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected static function booted(): void
    {
        static::creating(function (PaymentAttempt $attempt): void {
            $attempt->initiated_at ??= now();
        });

        static::updating(function (PaymentAttempt $attempt): void {
            if ($attempt->isDirty(['amount', 'currency'])) {
                throw new LogicException('Payment attempt amount and currency cannot be changed after creation.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'checkout_instructions' => 'array',
            'metadata' => 'array',
            'initiated_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'expired_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function providerCreationState(): ?string
    {
        $state = ($this->metadata ?? [])['provider_creation_state'] ?? null;

        return is_string($state) ? $state : null;
    }

    public function hasUncertainProviderCreation(): bool
    {
        return $this->providerCreationState() === 'uncertain';
    }

    public function hasInProgressProviderCreation(): bool
    {
        return $this->providerCreationState() === 'in_progress';
    }

    public function hasOrphanedProviderCreation(CarbonInterface $staleBefore): bool
    {
        return $this->provider_reference === null
            && in_array($this->providerCreationState(), ['in_progress', 'uncertain'], true)
            && $this->updated_at?->lte($staleBefore) === true;
    }

    public function hasSupersededProviderCreation(): bool
    {
        return $this->providerCreationState() === 'superseded';
    }

    public function scopeReconciliationCandidate(
        Builder $query,
        CarbonInterface $staleBefore,
        CarbonInterface $now,
    ): Builder {
        return $query
            ->where('status', PaymentStatus::Pending->value)
            ->where(function (Builder $query) use ($staleBefore, $now): void {
                $query
                    ->where('updated_at', '<=', $staleBefore)
                    ->orWhere('expires_at', '<=', $now)
                    ->orWhereJsonContains('metadata->provider_creation_state', 'uncertain');
            });
    }

    public function setCurrencyAttribute(string $currency): void
    {
        $this->attributes['currency'] = app(MoneyConverter::class)->normalizeCurrency($currency);
    }

    public function setMetadataAttribute(?array $metadata): void
    {
        if ($metadata === null) {
            $this->attributes['metadata'] = null;

            return;
        }

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || (! is_bool($value) && ! is_int($value) && ! is_string($value) && $value !== null)) {
                throw new InvalidArgumentException('Payment attempt metadata must contain only scalar values.');
            }
        }

        $this->attributes['metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
    }
}
