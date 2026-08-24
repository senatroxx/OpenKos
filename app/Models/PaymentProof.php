<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy path is retained only for unmigrated rows and rollback.
 * New code must use the linked Media record.
 */
#[Fillable([
    'payment_id',
    'media_id',
    'original_name',
    'mime_type',
])]
class PaymentProof extends Model
{
    use HasFactory, SerializesDatesWithTimezone;

    protected $hidden = ['path', 'media_id'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    protected function originalName(): Attribute
    {
        return Attribute::get(fn (mixed $value): mixed => $this->canonicalValue('original_name', $value));
    }

    protected function mimeType(): Attribute
    {
        return Attribute::get(fn (mixed $value): mixed => $this->canonicalValue('mime_type', $value));
    }

    private function canonicalValue(string $attribute, mixed $legacyValue): mixed
    {
        if ($this->media_id === null) {
            return $legacyValue;
        }

        return $this->relationLoaded('media') ? $this->media?->{$attribute} : null;
    }
}
