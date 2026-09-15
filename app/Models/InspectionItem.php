<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\InspectionItemCondition;
use App\Enums\InspectionStatus;
use Database\Factories\InspectionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'inspection_id',
    'label',
    'description',
    'position',
    'condition',
    'notes',
])]
class InspectionItem extends Model
{
    /** @use HasFactory<InspectionItemFactory> */
    use Auditable, HasFactory, HasMedia, SerializesDatesWithTimezone;

    protected static function booted(): void
    {
        static::creating(function (InspectionItem $item): void {
            $item->guardMutable();
        });

        static::updating(function (InspectionItem $item): void {
            $item->guardMutable();
        });

        static::deleting(function (InspectionItem $item): void {
            $item->guardMutable();
        });
    }

    protected function casts(): array
    {
        return [
            'condition' => InspectionItemCondition::class,
            'position' => 'integer',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    private function guardMutable(): void
    {
        if ($this->inspection()->where('status', InspectionStatus::Completed->value)->exists()) {
            throw new LogicException('Items for completed inspections are immutable.');
        }
    }
}
