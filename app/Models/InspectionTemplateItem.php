<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use Database\Factories\InspectionTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'inspection_template_id',
    'label',
    'description',
    'position',
])]
class InspectionTemplateItem extends Model
{
    /** @use HasFactory<InspectionTemplateItemFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }
}
