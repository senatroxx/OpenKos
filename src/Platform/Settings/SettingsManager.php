<?php

namespace OpenKOS\Platform\Settings;

use App\Events\Settings\SettingsUpdated;
use App\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class SettingsManager
{
    public function __construct(
        private SettingsRegistry $registry,
        private Dispatcher $events,
    ) {}

    public function get(string $key): mixed
    {
        $definition = $this->findDefinition($key);

        return Setting::get($key) ?? $definition->default;
    }

    public function set(string $key, mixed $value, ?Authenticatable $actor = null): void
    {
        $definition = $this->findDefinition($key);

        if ($definition->rules) {
            $safeKey = str_replace('.', '_', $key);
            $validator = Validator::make(
                [$safeKey => $value],
                [$safeKey => $definition->rules],
            );

            if ($validator->fails()) {
                throw new InvalidArgumentException($validator->errors()->first($key));
            }
        }

        Setting::set($key, $value, $definition->type);

        $this->events->dispatch(new SettingsUpdated(
            group: $definition->page ?? 'general',
            keys: [$key],
            actorId: $actor?->getKey(),
        ));
    }

    public function all(?string $page = null): array
    {
        $definitions = $this->registry->definitions($page);
        $values = [];

        foreach ($definitions as $definition) {
            $values[$definition->key] = $this->get($definition->key);
        }

        return $values;
    }

    public function definitions(?string $page = null): array
    {
        return $this->registry->definitions($page);
    }

    private function findDefinition(string $key): SettingDefinition
    {
        $definitions = $this->registry->definitions();
        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException("Setting [{$key}] is not registered.");
        }

        return $definitions[$key];
    }
}
