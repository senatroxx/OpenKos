<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'property_id',
    'unit_id',
    'tenant_id',
    'manager_id',
    'signed_at',
    'version',
    'document_path',
    'notes',
    'status',
])]
class TenantAgreement extends Model
{
    use SerializesDatesWithTimezone;

    protected $table = 'tenant_agreements';

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
