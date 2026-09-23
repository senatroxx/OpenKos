<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationTargetType;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'property_id', 'unit_type_id', 'target_type', 'status',
        'applicant_name', 'applicant_email', 'applicant_phone',
        'intended_move_in_date', 'intended_move_in_timeframe', 'applicant_message',
        'operator_notes', 'applicant_feedback', 'open_application_key',
        'reviewed_by', 'reviewed_at', 'converted_tenant_id', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'target_type' => ApplicationTargetType::class,
            'intended_move_in_date' => 'date',
            'reviewed_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }
}
