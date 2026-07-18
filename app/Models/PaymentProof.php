<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id',
    'path',
    'original_name',
    'mime_type',
])]
class PaymentProof extends Model
{
    use HasFactory;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
