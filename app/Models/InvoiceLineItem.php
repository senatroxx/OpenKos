<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'type',
    'description',
    'amount',
    'utility_reading_id',
    'metadata',
])]
class InvoiceLineItem extends Model
{
    use HasFactory, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function utilityReading(): BelongsTo
    {
        return $this->belongsTo(UtilityReading::class);
    }
}
