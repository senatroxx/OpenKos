<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'label',
    'is_active',
    'sort_order',
])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('label');
    }
}
