<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lease_id',
    'from_unit_id',
    'to_unit_id',
    'transferred_by',
    'reason',
    'notes',
    'effective_date',
])]
class LeaseUnitHistory extends Model
{
    protected $table = 'lease_unit_histories';

    protected function casts(): array
    {
        return [
            'effective_date' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
