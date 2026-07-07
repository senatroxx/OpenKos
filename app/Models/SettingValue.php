<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingValue extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['key', 'value', 'type'];

    public static function get(string $key): ?string
    {
        return static::where('key', $key)->value('value');
    }

    public static function set(string $key, mixed $value, string $type = 'string'): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type],
        );
    }
}
