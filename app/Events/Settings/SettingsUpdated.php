<?php

namespace App\Events\Settings;

use Illuminate\Foundation\Events\Dispatchable;

class SettingsUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $group,
        public readonly array $keys,
        public readonly ?int $actorId = null,
    ) {}
}
