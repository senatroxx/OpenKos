<?php

namespace OpenKOS\Platform\Installation;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class InstallationStepRegistry implements Arrayable
{
    /** @var array<string, InstallationStep> */
    private array $steps = [];

    public function registerStep(InstallationStep $step): static
    {
        if (isset($this->steps[$step->key])) {
            throw new InvalidArgumentException("Installation step [{$step->key}] is already registered.");
        }

        $this->steps[$step->key] = $step;

        return $this;
    }

    /** @return array<string, InstallationStep> */
    public function all(): array
    {
        return $this->steps;
    }

    /** @return array<string, InstallationStep> */
    public function after(string $after): array
    {
        return array_values(
            array_filter($this->steps, fn (InstallationStep $step) => $step->after === $after)
        );
    }

    public function has(string $key): bool
    {
        return isset($this->steps[$key]);
    }

    public function toArray(): array
    {
        return array_values(array_map(fn (InstallationStep $step) => $step->toArray(), $this->steps));
    }
}
