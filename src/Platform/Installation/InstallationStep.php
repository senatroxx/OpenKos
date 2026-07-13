<?php

namespace OpenKOS\Platform\Installation;

use Illuminate\Contracts\Support\Arrayable;

final readonly class InstallationStep implements Arrayable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $route,
        public string $component,
        public ?string $after = null,
        public bool $optional = true,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'route' => $this->route,
            'component' => $this->component,
            'after' => $this->after,
            'optional' => $this->optional,
        ];
    }
}
