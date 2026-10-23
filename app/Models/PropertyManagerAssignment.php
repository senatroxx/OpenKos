<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyManagerAssignment extends Model
{
    use SerializesDatesWithTimezone;

    protected $table = 'property_manager_assignments';

    protected $fillable = [
        'property_id',
        'user_id',
        'assigned_by',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
