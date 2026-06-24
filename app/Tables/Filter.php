<?php

namespace App\Tables;

use Closure;

class Filter
{
    public string $key;

    public string $label;

    public string $type;

    public array|Closure|null $options;

    public ?Closure $queryCallback = null;

    protected function __construct(string $key, string $label, string $type, array|Closure|null $options = null)
    {
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
        $this->options = $options;
    }

    public static function select(string $key, string $label, array|Closure $options): self
    {
        return new self($key, $label, 'select', $options);
    }

    public static function toggle(string $key, string $label): self
    {
        return new self($key, $label, 'toggle');
    }

    public function query(Closure $callback): self
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function resolveOptions(): array
    {
        if ($this->options === null) {
            return [];
        }

        if ($this->options instanceof Closure) {
            return value($this->options);
        }

        return $this->options;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->resolveOptions(),
        ];
    }
}
