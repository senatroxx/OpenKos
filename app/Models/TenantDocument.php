<?php

namespace App\Models;

use App\Enums\TenantDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDocument extends Model
{
    protected $fillable = [
        'tenant_id',
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

    public function downloadUrl(): string
    {
        return route('tenants.documents.show', [
            'tenant' => $this->tenant_id,
            'document' => $this->id,
        ]);
    }
}
