<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementRow extends Model
{
    protected $fillable = [
        'bank_statement_import_id',
        'row_number',
        'transaction_date',
        'description',
        'unit_number',
        'normalized_unit_number',
        'payer_name',
        'amount',
        'currency',
        'matched_unit_id',
        'matched_tenant_id',
        'match_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'matched_unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'matched_tenant_id');
    }
}
