<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'room_id',
    'title',
    'description',
    'status',
    'priority',
    'assigned_to',
    'cost',
    'resolved_at',
    'resolution_notes',
])]
class MaintenanceTicket extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
