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
            return self::allWithDefaults();
        }

        $setting = self::where('key', $key)->first();

        return $setting?->resolveValue() ?? self::defaultValues()[$key] ?? null;
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
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key);
        }

        return $result;
    }

    public static function allWithDefaults(): array
    {
        static $defaults;

        $defaults ??= [
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
            'mail_config' => '[]',
            'whatsapp_config' => '[]',
        ];

        return array_merge($defaults, self::pluck('value', 'key')->all());
    }

    public static function defaultValues(): array
    {
        $typed = [];
        foreach (self::allDefaultsRaw() as $key => [$value, $type]) {
            $typed[$key] = self::resolveValueFromRaw($value, $type);
        }

        return $typed;
    }

    private static function allDefaultsRaw(): array
    {
        return [
            'site_name' => ['OpenKOS', 'string'],
            'country_code' => ['ID', 'string'],
            'locale' => ['id', 'string'],
            'currency' => ['IDR', 'string'],
            'timezone' => ['Asia/Jakarta', 'string'],
            'lease_id_prefix' => ['LSX', 'string'],
            'reminder_enabled' => ['true', 'boolean'],
            'reminder_days_before' => ['3', 'integer'],
            'reminder_overdue_intervals' => ['[1,3,7]', 'array'],
            'reminder_channels' => ['["log"]', 'array'],
            'mail_config' => ['[]', 'encrypted:array'],
            'whatsapp_config' => ['[]', 'encrypted:array'],
        ];
    }

    private static function resolveValueFromRaw(string $value, string $type): mixed
    {
        if ($type === 'encrypted' || $type === 'encrypted:array') {
            return match ($type) {
                'encrypted:array' => json_decode($value, true) ?: [],
                default => $value,
            };
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'integer' => (int) $value,
            'array' => json_decode($value, true),
            default => $value,
        };
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
