<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    protected $fillable = [
        'uploaded_by',
        'file_name',
        'file_path',
        'statement_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(BankStatementRow::class, 'bank_statement_import_id');
    }
}
