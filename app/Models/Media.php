<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'mediable_type',
    'mediable_id',
    'collection',
    'disk',
    'path',
    'mime_type',
    'size',
    'original_name',
    'position',
    'metadata',
])]
class Media extends Model
{
    protected $hidden = ['disk', 'path'];

    /** @use HasFactory<MediaFactory> */
    use HasFactory, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'mediable_id' => 'integer',
            'size' => 'integer',
            'position' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
