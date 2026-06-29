<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'site_name',
    'country_code',
    'locale',
    'currency',
    'timezone',
    'lease_id_prefix',
    'reminder_enabled',
    'reminder_days_before',
    'reminder_overdue_intervals',
    'reminder_message_template',
    'reminder_channels',
    'mail_driver',
    'mail_host',
    'mail_port',
    'mail_username',
    'mail_password',
    'mail_encryption',
    'mail_from_address',
    'mail_from_name',
])]
class Setting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reminder_enabled' => 'boolean',
            'reminder_days_before' => 'integer',
            'reminder_overdue_intervals' => 'array',
            'reminder_channels' => 'array',
            'mail_port' => 'integer',
            'mail_password' => 'encrypted',
        ];
    }

    public static function get(): self
    {
        return self::firstOrCreate([], [
            'site_name' => 'OpenKOS',
            'country_code' => 'ID',
            'locale' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'lease_id_prefix' => 'LSX',
            'reminder_enabled' => true,
            'reminder_days_before' => 3,
            'reminder_overdue_intervals' => [1, 3, 7],
            'reminder_channels' => ['log'],
            'mail_driver' => 'smtp',
        ]);
    }
}
