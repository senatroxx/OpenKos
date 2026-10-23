<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliation extends Model
{
    protected $fillable = [
        'unit_id',
        'tenant_id',
        'property_id',
        'month',
        'expected_rent',
        'water_bill',
        'waste_bill',
        'total_expected',
        'amount_paid',
        'amount_matched',
        'balance',
        'status',
        'source_import_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m',
            'expected_rent' => 'decimal:2',
            'water_bill' => 'decimal:2',
            'waste_bill' => 'decimal:2',
            'total_expected' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_matched' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'source_import_id');
    }
}
