<?php

namespace OpenKOS\Platform\Settings;

use Illuminate\Contracts\Support\Arrayable;

final readonly class SettingsPage implements Arrayable
{
    public function __construct(
        public string $key,
        public string $title,
        public string $href,
        public ?string $permission = null,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'href' => $this->href,
            'permission' => $this->permission,
        ];
    }
}
