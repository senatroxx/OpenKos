<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\InspectionType;
use Database\Factories\InspectionTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'inspection_type',
    'is_active',
])]
class InspectionTemplate extends Model
{
    /** @use HasFactory<InspectionTemplateFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'inspection_type' => InspectionType::class,
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionTemplateItem::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
