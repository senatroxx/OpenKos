<?php

namespace OpenKOS\Platform\Notification;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class NotificationRegistry implements Arrayable
{
    /** @var array<string, NotificationDriverRegistration> */
    private array $drivers = [];

    public function registerDriver(NotificationDriverRegistration $driver): static
    {
        if (isset($this->drivers[$driver->name])) {
            throw new InvalidArgumentException("Notification driver [{$driver->name}] is already registered.");
        }

        $this->drivers[$driver->name] = $driver;

        return $this;
    }

    /**
     * @return array<string, NotificationDriverRegistration>
     */
    public function drivers(): array
    {
        return $this->drivers;
    }

    /**
     * @return array<int, NotificationDriverRegistration>
     */
    public function forChannel(string $channel): array
    {
        return array_values(array_filter(
            $this->drivers,
            fn (NotificationDriverRegistration $d) => $d->channel === $channel,
        ));
    }

    public function get(string $name): ?NotificationDriverRegistration
    {
        return $this->drivers[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    public function toArray(): array
    {
        return array_values(array_map(
            fn (NotificationDriverRegistration $d) => $d->toArray(),
            $this->drivers,
        ));
    }
}
