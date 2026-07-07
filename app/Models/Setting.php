<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'type'];

    public static function get(?string $key = null): mixed
    {
        if ($key === null) {
            return self::pluck('value', 'key')->all();
        }

        $setting = self::where('key', $key)->first();

        return $setting?->resolveValue();
    }

    public static function set(string $key, mixed $value, string $type = 'string'): static
    {
        $stored = match ($type) {
            'array' => json_encode($value),
            'encrypted:array' => encrypt(json_encode($value)),
            'encrypted' => encrypt($value),
            default => (string) $value,
        };

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );
    }

    public static function some(array $keys): array
    {
        $all = self::get();

        return array_merge(
            array_combine($keys, array_fill(0, count($keys), null)),
            array_intersect_key($all, array_flip($keys)),
        );
    }

    public static function allWithDefaults(): array
    {
        $defaults = [
            'site_name' => 'OpenKOS',
            'country_code' => 'ID',
            'locale' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'lease_id_prefix' => 'LSX',
            'reminder_enabled' => 'true',
            'reminder_days_before' => '3',
            'reminder_overdue_intervals' => '[1,3,7]',
            'reminder_channels' => '["log"]',
            'mail_driver' => 'smtp',
        ];

        return array_merge($defaults, self::pluck('value', 'key')->all());
    }

    public function resolveValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'integer' => (int) $this->value,
            'array' => json_decode($this->value, true),
            'encrypted' => decrypt($this->value),
            'encrypted:array' => json_decode(decrypt($this->value), true) ?: [],
            default => $this->value,
        };
    }
}
