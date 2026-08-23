<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\TenantDocumentType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy file_path is retained only for unmigrated rows and rollback.
 * New code must use the linked Media record.
 */
class TenantDocument extends Model
{
    use SerializesDatesWithTimezone;

    protected $hidden = ['file_path', 'media_id'];

    protected $appends = ['download_url'];

    /**
     * Legacy storage coordinates are written only through explicit compatibility paths.
     */
    protected $fillable = [
        'tenant_id',
        'media_id',
        'type',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'type' => TenantDocumentType::class,
            'size' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    protected function downloadUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('tenants.documents.show', [
            'tenant' => $this->tenant_id,
            'document' => $this->id,
        ]));
    }

    protected function originalName(): Attribute
    {
        return Attribute::get(fn (mixed $value): mixed => $this->canonicalValue('original_name', $value));
    }

    protected function mimeType(): Attribute
    {
        return Attribute::get(fn (mixed $value): mixed => $this->canonicalValue('mime_type', $value));
    }

    protected function size(): Attribute
    {
        return Attribute::get(fn (mixed $value): mixed => $this->canonicalValue('size', $value));
    }

    private function canonicalValue(string $attribute, mixed $legacyValue): mixed
    {
        if ($this->media_id === null) {
            return $legacyValue;
        }

        return $this->relationLoaded('media') ? $this->media?->{$attribute} : null;
    }
}
