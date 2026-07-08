<?php

namespace OpenKOS\Platform\Settings;

use Illuminate\Contracts\Support\Arrayable;

final readonly class SettingDefinition implements Arrayable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'string',
        public mixed $default = null,
        public array $rules = [],
        public ?string $page = null,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'default' => $this->default,
            'rules' => $this->rules,
            'page' => $this->page,
        ];
    }
}
