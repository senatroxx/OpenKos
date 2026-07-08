<?php

namespace App\Data\Settings;

readonly class SettingUpdateData
{
    public function __construct(
        public array $values,
        public array $original,
        public string $group,
    ) {}
}
