<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'property_id',
    'unit_id',
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
    use HasFactory, SerializesDatesWithTimezone;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MaintenanceTicket $ticket) {
            if ($ticket->reference === null) {
                $year = now()->format('Y');
                $referencePrefix = 'TKT'.$year;
                $pattern = $referencePrefix.'%';

                $max = static::where('reference', 'like', $pattern)
                    ->orderByRaw('LENGTH(reference) DESC, reference DESC')
                    ->value('reference');

                $seq = $max ? (int) substr($max, strlen($referencePrefix)) + 1 : 1;

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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
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
