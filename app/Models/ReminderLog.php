<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
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
