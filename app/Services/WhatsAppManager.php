<?php

namespace App\Services;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class WhatsAppManager
{
    private array $drivers = [];

    public function driver(?string $name = null): WhatsAppDriver
    {
        $name ??= $this->resolveDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = config("services.whatsapp.drivers.{$name}");

        if (! $config) {
            throw new InvalidArgumentException("WhatsApp driver [{$name}] not found.");
        }

        $class = $config['class'];

        return $this->drivers[$name] = new $class($this->resolveCredentials($name, $config));
    }

    public function send(string $phone, string $message): void
    {
        $this->driver()->send(new WhatsAppMessage($phone, $message));
    }

    public function health(): DriverHealthResult
    {
        return $this->driver()->health();
    }

    public function getPairingQrCode(): ?string
    {
        return $this->driver()->getPairingQrCode();
    }

    private function resolveDefaultDriver(): string
    {
        try {
            return Setting::get()->whatsapp_driver ?? config('services.whatsapp.default', 'log');
        } catch (QueryException) {
            return config('services.whatsapp.default', 'log');
        }
    }

    private function resolveCredentials(string $name, array $config): array
    {
        $envDefaults = array_filter($config, fn ($key) => $key !== 'class', ARRAY_FILTER_USE_KEY);

        try {
            $dbConfig = Setting::get()->whatsapp_config[$name] ?? [];
        } catch (QueryException) {
            $dbConfig = [];
        }

        return array_merge($envDefaults, $dbConfig);
    }
}
