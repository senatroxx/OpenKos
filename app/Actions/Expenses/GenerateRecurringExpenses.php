<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\RecurringExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class GenerateRecurringExpenses
{
    private const MAX_OCCURRENCES_PER_DEFINITION = 1000;

    public function execute(): int
    {
        return DB::transaction(function (): int {
            // ponytail: lock the active definition set once; split per definition if contention is measured.
            $definitions = RecurringExpense::query()
                ->active()
                ->whereNotNull('next_due_on')
                ->lockForUpdate()
                ->get();
            $generated = 0;
            $today = CarbonImmutable::today();

            foreach ($definitions as $definition) {
                $generated += $this->generateFor($definition, $today);
            }

            return $generated;
        });
    }

    private function generateFor(RecurringExpense $definition, CarbonImmutable $today): int
    {
        $cursor = $definition->next_due_on?->startOfDay();
        $endDate = $definition->end_date?->startOfDay();
        $generated = 0;
        $iterations = 0;

        while ($cursor !== null
            && $cursor->lessThanOrEqualTo($today)
            && ($endDate === null || $cursor->lessThanOrEqualTo($endDate))) {
            if (++$iterations > self::MAX_OCCURRENCES_PER_DEFINITION) {
                break;
            }

            $exists = Expense::query()
                ->where('recurring_expense_id', $definition->getKey())
                ->whereDate('expense_date', $cursor->toDateString())
                ->exists();

            if (! $exists) {
                Expense::create([
                    'property_id' => $definition->property_id,
                    'expense_category_id' => $definition->expense_category_id,
                    'recurring_expense_id' => $definition->getKey(),
                    'amount' => $definition->amount,
                    'currency' => $definition->currency,
                    'expense_date' => $cursor->toDateString(),
                    'vendor' => $definition->vendor,
                    'description' => $definition->description,
                ]);

                $generated++;
            }

            $nextCursor = $definition->nextOccurrenceAfter($cursor);

            if (! $nextCursor->greaterThan($cursor)) {
                throw new LogicException('Recurring expense generation cursor did not advance.');
            }

            $cursor = $nextCursor;
        }

        $definition->next_due_on = $cursor !== null
            && ($endDate === null || $cursor->lessThanOrEqualTo($endDate))
            ? $cursor->toDateString()
            : null;
        $definition->saveQuietly();

        return $generated;
    }
}
