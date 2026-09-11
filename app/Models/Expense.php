<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\ExpenseStatus;
use App\Services\Payments\MoneyConverter;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'property_id',
    'expense_category_id',
    'recurring_expense_id',
    'amount',
    'currency',
    'expense_date',
    'vendor',
    'description',
    'notes',
    'reference',
    'status',
    'voided_at',
    'voided_by',
    'void_reason',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, HasFactory, HasMedia, SerializesDatesWithTimezone;

    protected $attributes = [
        'status' => ExpenseStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'expense_date' => 'date:Y-m-d',
            'status' => ExpenseStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            $converter = app(MoneyConverter::class);
            $expense->currency = $converter->normalizeCurrency($expense->currency);
            $expense->amount = $converter->normalizeAmount((string) $expense->amount, $expense->currency);
        });

        static::updating(function (Expense $expense): void {
            if ($expense->getRawOriginal('status') === ExpenseStatus::Voided->value) {
                throw new LogicException('Voided expenses are immutable.');
            }

            if ($expense->isDirty('currency')) {
                throw new LogicException('Expense currency snapshots are immutable.');
            }

            if ($expense->isDirty('recurring_expense_id')) {
                throw new LogicException('Expense recurring source is immutable.');
            }

            if ($expense->isDirty('amount')) {
                $expense->amount = app(MoneyConverter::class)->normalizeAmount(
                    (string) $expense->amount,
                    (string) $expense->currency,
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

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', ExpenseStatus::Active->value);
    }

    public function scopeVoided(Builder $query): void
    {
        $query->where('status', ExpenseStatus::Voided->value);
    }

    public function isVoided(): bool
    {
        return $this->status === ExpenseStatus::Voided;
    }
}
