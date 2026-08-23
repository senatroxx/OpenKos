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
     * `file_path` remains fillable only for compatibility writes and rollback.
     */
    protected $fillable = [
        'tenant_id',
        'media_id',
        'type',
        'original_name',
        'file_path',
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
}
