<?php

namespace App\Models;

use App\Services\Settings\SettingCaster;
use App\Services\Settings\SettingManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'type'];

    public static function get(?string $key = null): mixed
    {
        return app(SettingManager::class)->get($key);
    }

    public static function set(string $key, mixed $value, ?string $type = null): static
    {
        return app(SettingManager::class)->set($key, $value, $type);
    }

    public static function some(array $keys): array
    {
        return app(SettingManager::class)->some($keys);
    }

    public function resolveValue(): mixed
    {
        return app(SettingCaster::class)->deserialize($this->value, $this->type);
    }
}
