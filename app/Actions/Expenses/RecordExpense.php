<?php

namespace App\Actions\Expenses;

use App\Data\Expense\RecordExpenseData;
use App\Models\Expense;
use App\Services\Media\MediaManager;
use Illuminate\Support\Facades\DB;

final class RecordExpense
{
    public function __construct(private MediaManager $mediaManager) {}

    public function execute(RecordExpenseData $data): Expense
    {
        return DB::transaction(function () use ($data): Expense {
            $expense = Expense::create($data->attributes);

            if ($data->receipt !== null) {
                $this->mediaManager->store($expense, 'receipts', $data->receipt);
            }

            return $expense;
        });
    }
}
