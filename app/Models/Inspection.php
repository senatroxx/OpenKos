<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'inspection_template_id',
    'template_name',
    'property_id',
    'unit_id',
    'lease_id',
    'inspection_type',
    'inspection_date',
    'status',
    'notes',
    'damage_observations',
    'inspector_id',
    'completed_by',
    'completed_at',
])]
class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected static function booted(): void
    {
        static::updating(function (Inspection $inspection): void {
            if ($inspection->getRawOriginal('status') === InspectionStatus::Completed->value) {
                throw new LogicException('Completed inspections are immutable.');
            }

            if ($inspection->isDirty([
                'inspection_template_id',
                'template_name',
                'property_id',
                'unit_id',
                'lease_id',
                'inspection_type',
            ])) {
                throw new LogicException('Inspection identity cannot be changed after creation.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Inspections are historical records and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'inspection_type' => InspectionType::class,
            'inspection_date' => 'date:Y-m-d',
            'status' => InspectionStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function isCompleted(): bool
    {
        return $this->status === InspectionStatus::Completed;
    }
}
