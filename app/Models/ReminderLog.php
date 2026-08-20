<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory, SerializesDatesWithTimezone;

    public const NON_OVERDUE_DAYS = -1;

    protected $attributes = [
        'overdue_days' => self::NON_OVERDUE_DAYS,
    ];

    protected $fillable = [
        'lease_id',
        'period_start',
        'period_end',
        'reminder_type',
        'overdue_days',
        'notification_class',
        'channel',
        'scheduled_for',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'scheduled_for' => 'date:Y-m-d',
            'sent_at' => 'datetime',
            'overdue_days' => 'integer',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
