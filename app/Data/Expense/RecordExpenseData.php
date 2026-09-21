<?php

namespace App\Data\Expense;

use Illuminate\Http\UploadedFile;

final readonly class RecordExpenseData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
        public ?UploadedFile $receipt = null,
    ) {}
}
