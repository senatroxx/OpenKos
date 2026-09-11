<?php

namespace App\Console\Commands;

use App\Actions\Expenses\GenerateRecurringExpenses;
use Illuminate\Console\Command;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:generate-recurring';

    protected $description = 'Generate expenses for due recurring expense schedules';

    public function handle(GenerateRecurringExpenses $action): int
    {
        $count = $action->execute();

        $this->info("Generated {$count} recurring expense(s).");

        return self::SUCCESS;
    }
}
