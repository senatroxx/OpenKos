<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\BillingStrategy;
use App\Enums\BillingUnit;
use App\Enums\LeaseStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'primary_tenant_id',
    'unit_id',
    'start_date',
    'end_date',
    'rent_amount',
    'billing_interval',
    'billing_unit',
    'billing_strategy',
    'is_custom_price',
    'unit_rate_id',
    'deposit_amount',
    'deposit_paid_at',
    'deposit_refund_amount',
    'deposit_refunded_at',
    'rent_due_day',
    'status',
    'termination_date',
    'termination_reason',
    'notes',
    'previous_lease_id',
    'reference',
])]
class Lease extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Lease $lease) {
            if ($lease->reference === null) {
                $prefix = Setting::get('lease_id_prefix') ?? 'LSX';
                $year = now()->format('Y');
                $pattern = $prefix.$year.'%';

                $max = static::where('reference', 'like', $pattern)
                    ->orderBy('reference', 'desc')
                    ->value('reference');

                $seq = $max ? (int) substr($max, -4) + 1 : 1;

                $lease->reference = $prefix.$year.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $appends = ['monthly_equivalent', 'billing_label'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'billing_interval' => 'integer',
            'billing_unit' => BillingUnit::class,
            'billing_strategy' => BillingStrategy::class,
            'status' => LeaseStatus::class,
            'is_custom_price' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'deposit_refund_amount' => 'decimal:2',
            'deposit_paid_at' => 'datetime',
            'deposit_refunded_at' => 'datetime',
            'rent_due_day' => 'integer',
            'termination_date' => 'date',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'primary_tenant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function unitRate(): BelongsTo
    {
        return $this->belongsTo(UnitRate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    public function previousLease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'previous_lease_id');
    }

    public function renewedLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'previous_lease_id');
    }

    public function unitHistories(): HasMany
    {
        return $this->hasMany(LeaseUnitHistory::class)->orderBy('effective_date');
    }

    public function getMonthlyEquivalentAttribute(): string
    {
        $amount = $this->rent_amount ? (float) $this->rent_amount : 0;
        $interval = $this->billing_interval ?? 1;
        $unit = $this->billing_unit ?? BillingUnit::Month;

        return match ($unit) {
            BillingUnit::Day => number_format($amount * 365 / 12 / $interval, 2, '.', ''),
            BillingUnit::Week => number_format($amount * 52 / 12 / $interval, 2, '.', ''),
            BillingUnit::Month => number_format($amount / $interval, 2, '.', ''),
            BillingUnit::Year => number_format($amount / 12 / $interval, 2, '.', ''),
        };
    }

    public function getBillingLabelAttribute(): string
    {
        $interval = $this->billing_interval ?? 1;
        $unit = $this->billing_unit ?? BillingUnit::Month;

        if ($unit === BillingUnit::Month && $interval === 1) {
            return '/month';
        }

        return match ($unit) {
            BillingUnit::Day => '/day',
            BillingUnit::Week => '/week',
            BillingUnit::Month => "/ {$interval} months",
            BillingUnit::Year => '/year',
        };
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', LeaseStatus::Active->value);
    }

    public function schedule(?int $months = 12): Collection
    {
        if (! $this->rent_amount || ! $this->rent_due_day || ! $this->start_date) {
            return collect();
        }

        $dueDay = $this->rent_due_day;
        $start = Carbon::parse($this->start_date);
        $interval = $this->billing_interval ?? 1;
        $advanceMonths = match ($this->billing_unit) {
            BillingUnit::Year => $interval * 12,
            BillingUnit::Month => $interval,
            default => 0,
        };
        $end = $this->end_date
            ? Carbon::parse($this->end_date)->startOfMonth()
            : now()->startOfMonth()->addMonthsNoOverflow(max(0, $months - 1));

        $cursor = $start->copy()->startOfMonth();
        if ($start->day > $dueDay) {
            $cursor->addMonthsNoOverflow($advanceMonths ?: 1);
        }

        $schedule = collect();

        while ($cursor <= $end) {
            $effectiveDueDay = min($dueDay, $cursor->daysInMonth);
            $periodStart = $cursor->copy()->startOfMonth();
            $periodEnd = $advanceMonths
                ? $cursor->copy()->addMonthsNoOverflow($advanceMonths)->subDay()
                : match ($this->billing_unit) {
                    BillingUnit::Week => $cursor->copy()->addWeeks($interval)->subDay(),
                    BillingUnit::Day => $cursor->copy(),
                    default => $cursor->copy()->endOfMonth(),
                };
            $dueDate = $cursor->copy()->setDay($effectiveDueDay);

            if ($this->end_date) {
                $endDateCarbon = Carbon::parse($this->end_date);
                if ($periodEnd > $endDateCarbon) {
                    $periodEnd = $endDateCarbon;
                }
                if ($dueDate > $endDateCarbon) {
                    $dueDate = $endDateCarbon;
                }
            }

            // Arrears: rent is due one billing period after the advance due
            // date, i.e. after the period has been consumed. Applied after
            // the end-date clamp on purpose — the final arrears payment
            // falls after the lease ends.
            if ($this->billing_strategy === BillingStrategy::Arrears) {
                $dueDate = $advanceMonths
                    ? $dueDate->addMonthsNoOverflow($advanceMonths)
                    : match ($this->billing_unit) {
                        BillingUnit::Week => $dueDate->addWeeks($interval),
                        BillingUnit::Day => $dueDate->addDays($interval),
                        default => $dueDate->addMonthsNoOverflow(1),
                    };
            }

            $status = $dueDate->copy()->endOfDay()->isPast()
                ? 'overdue'
                : ($periodStart->isFuture()
                    ? 'upcoming'
                    : 'due');

            $schedule->push((object) [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'amount' => $this->rent_amount,
                'status' => $status,
            ]);

            $cursor->addMonthsNoOverflow($advanceMonths ?: 1);
        }

        return $schedule;
    }
}
