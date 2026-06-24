<?php

namespace App\Tables;

use Closure;

class Column
{
    public string $key;

    public string $label;

    public bool $sortable = false;

    public bool $searchable = false;

    public ?Closure $sortUsing = null;

    public ?Closure $searchUsing = null;

    public function __construct(string $key, string $label)
    {
        $this->key = $key;
        $this->label = $label;
    }

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public function sortable(?Closure $using = null): self
    {
        $this->sortable = true;
        $this->sortUsing = $using;

        return $this;
    }

    public function searchable(?Closure $using = null): self
    {
        $this->searchable = true;
        $this->searchUsing = $using;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
        ];
    }
}
