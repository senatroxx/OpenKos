<?php

namespace App\Support;

final class DelimitedValues
{
    /**
     * @return array<int, string>
     */
    public static function normalize(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        $normalized = [];

        foreach ($values as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            foreach (explode(',', trim((string) $item)) as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $normalized[] = $part;
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
