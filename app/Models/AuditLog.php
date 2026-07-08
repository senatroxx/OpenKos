<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'operation',
        'before',
        'after',
        'actor_type',
        'actor_id',
        'source',
        'metadata',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        ?Model $auditable,
        string $operation,
        ?array $before = null,
        ?array $after = null,
        ?Model $actor = null,
        string $source = 'UI',
        ?array $metadata = null,
    ): self {
        return static::create([
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'operation' => $operation,
            'before' => $before,
            'after' => $after,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'source' => $source,
            'metadata' => $metadata,
        ]);
    }
}
