<?php

namespace OpenKOS\Platform\Notification;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use OpenKOS\Core\Contracts\NotificationDriver;

class NotificationRegistry implements Arrayable
{
    /** @var array<string, class-string<NotificationDriver>|NotificationDriver> */
    private array $drivers = [];

    /**
     * @param  class-string<NotificationDriver>|NotificationDriver  $driver
     */
    public function registerDriver(string $name, string|NotificationDriver $driver): static
    {
        if (isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Notification driver [{$name}] is already registered.");
        }

        $this->drivers[$name] = $driver;

        return $this;
    }

    /**
     * @return array<string, class-string<NotificationDriver>|NotificationDriver>
     */
    public function drivers(): array
    {
        return $this->drivers;
    }

    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    public function toArray(): array
    {
        return array_keys($this->drivers);
    }
}
