<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\BillingUnit;
use App\Services\Payments\MoneyConverter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'property_id',
    'expense_category_id',
    'amount',
    'currency',
    'vendor',
    'description',
    'billing_interval',
    'billing_unit',
    'start_date',
    'end_date',
    'is_active',
    'next_due_on',
    'paused_at',
])]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'billing_interval' => 'integer',
            'billing_unit' => BillingUnit::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'next_due_on' => 'date:Y-m-d',
            'paused_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RecurringExpense $recurringExpense): void {
            $converter = app(MoneyConverter::class);
            $recurringExpense->currency = $converter->normalizeCurrency($recurringExpense->currency);
            $recurringExpense->amount = $converter->normalizeAmount(
                (string) $recurringExpense->amount,
                $recurringExpense->currency,
            );
        });

        static::updating(function (RecurringExpense $recurringExpense): void {
            if ($recurringExpense->isDirty(['amount', 'currency'])) {
                $converter = app(MoneyConverter::class);
                $currency = $converter->normalizeCurrency($recurringExpense->currency);
                $recurringExpense->currency = $currency;
                $recurringExpense->amount = $converter->normalizeAmount(
                    (string) $recurringExpense->amount,
                    $currency,
                );
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function firstOccurrence(): CarbonImmutable
    {
        return $this->occurrenceAt(0);
    }

    public function nextOccurrenceAfter(CarbonInterface|string $date): CarbonImmutable
    {
        $target = CarbonImmutable::parse($date)->startOfDay();
        $start = CarbonImmutable::parse($this->start_date)->startOfDay();

        if ($target->lt($start)) {
            return $start;
        }

        $index = $this->approximateOccurrenceIndex($start, $target);
        $occurrence = $this->occurrenceAt($index);

        while ($occurrence->lessThanOrEqualTo($target)) {
            $nextIndex = $index + 1;
            $nextOccurrence = $this->occurrenceAt($nextIndex);

            if (! $nextOccurrence->greaterThan($occurrence)) {
                throw new LogicException('Recurring expense occurrence cursor did not advance.');
            }

            $index = $nextIndex;
            $occurrence = $nextOccurrence;
        }

        return $occurrence;
    }

    public function isEnded(?CarbonInterface $today = null): bool
    {
        return $this->end_date !== null
            && ($this->end_date->startOfDay()->lt(($today ?? now())->startOfDay())
                || $this->next_due_on === null);
    }

    private function occurrenceAt(int $index): CarbonImmutable
    {
        $start = CarbonImmutable::parse($this->start_date)->startOfDay();
        $interval = max(1, (int) $this->billing_interval);
        $unit = $this->billing_unit ?? BillingUnit::Month;

        return match ($unit) {
            BillingUnit::Day => $start->addDays($index * $interval),
            BillingUnit::Week => $start->addWeeks($index * $interval),
            BillingUnit::Month => $this->monthOccurrence($start, $index * $interval),
            BillingUnit::Year => $this->yearOccurrence($start, $index * $interval),
        };
    }

    private function monthOccurrence(CarbonImmutable $start, int $months): CarbonImmutable
    {
        $month = $start->startOfMonth()->addMonthsNoOverflow($months);

        return $month->setDay(min($start->day, $month->daysInMonth));
    }

    private function yearOccurrence(CarbonImmutable $start, int $years): CarbonImmutable
    {
        $year = $start->startOfYear()->addYearsNoOverflow($years)->setMonth($start->month);

        return $year->setDay(min($start->day, $year->daysInMonth));
    }

    private function approximateOccurrenceIndex(CarbonImmutable $start, CarbonImmutable $target): int
    {
        $interval = max(1, (int) $this->billing_interval);
        $unit = $this->billing_unit ?? BillingUnit::Month;

        return match ($unit) {
            BillingUnit::Day => intdiv($start->diffInDays($target), $interval),
            BillingUnit::Week => intdiv(intdiv($start->diffInDays($target), 7), $interval),
            BillingUnit::Month => intdiv(
                (($target->year - $start->year) * 12) + $target->month - $start->month,
                $interval,
            ),
            BillingUnit::Year => intdiv($target->year - $start->year, $interval),
        };
    }
}
