<?php

namespace App\Services\Settings;

class SettingCaster
{
    public function serialize(mixed $value, string $cast): string
    {
        return match ($cast) {
            'array' => json_encode($value, JSON_THROW_ON_ERROR),
            'encrypted:array' => encrypt(json_encode($value)),
            'encrypted' => encrypt($value),
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) $value,
            default => (string) $value,
        };
    }

    public function deserialize(string $value, string $cast): mixed
    {
        if ($cast === 'encrypted' || $cast === 'encrypted:array') {
            return match ($cast) {
                'encrypted:array' => json_decode(decrypt($value), true) ?: [],
                default => decrypt($value),
            };
        }

        return match ($cast) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'integer' => (int) $value,
            'array' => json_decode($value, true),
            default => $value,
        };
    }
}
