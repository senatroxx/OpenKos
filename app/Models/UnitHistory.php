<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'unit_id',
    'tenant_id',
    'previous_tenant_id',
    'property_id',
    'manager_id',
    'move_in_at',
    'move_out_at',
    'status',
    'notes',
])]
class UnitHistory extends Model
{
    use SerializesDatesWithTimezone;

    protected $table = 'unit_history';

    protected function casts(): array
    {
        return [
            'move_in_at' => 'datetime',
            'move_out_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function previousTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'previous_tenant_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
