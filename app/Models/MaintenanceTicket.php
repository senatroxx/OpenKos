<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'property_id',
    'room_id',
    'location',
    'title',
    'description',
    'status',
    'priority',
    'assigned_to',
    'created_by',
    'cost',
    'resolved_at',
    'resolution_notes',
    'reference',
])]
class MaintenanceTicket extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MaintenanceTicket $ticket) {
            if ($ticket->reference === null) {
                $year = now()->format('Y');
                $pattern = 'TKT'.$year.'%';

                $max = static::where('reference', 'like', $pattern)
                    ->orderBy('reference', 'desc')
                    ->value('reference');

                $seq = $max ? (int) substr($max, -4) + 1 : 1;

                $ticket->reference = 'TKT'.$year.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'priority' => MaintenancePriority::class,
            'cost' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
