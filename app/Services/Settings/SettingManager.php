<?php

namespace App\Services\Settings;

use App\Models\Setting;

class SettingManager
{
    /**
     * @var array<string, array{value: mixed, type: string}>
     */
    private array $storedSettings = [];

    /**
     * @var array<string, mixed>
     */
    private array $resolvedSettings = [];

    private bool $snapshotLoaded = false;

    public function __construct(
        private SettingRegistry $registry,
        private SettingCaster $caster,
    ) {}

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->allWithDefaults();
        }

        $this->loadSnapshot();

        if (array_key_exists($key, $this->storedSettings)) {
            return $this->resolveStoredSetting($key);
        }

        $def = $this->registry->get($key);

        return $def['default'] ?? null;
    }

    public function set(string $key, mixed $value, ?string $cast = null): Setting
    {
        $cast ??= $this->registry->get($key)['cast'] ?? 'string';

        $stored = $this->caster->serialize($value, $cast);

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $cast],
        );

        if ($this->snapshotLoaded) {
            $this->storedSettings[$key] = [
                'value' => $stored,
                'type' => $cast,
            ];
            unset($this->resolvedSettings[$key]);
        }

        $connection = $setting->getConnection();
        if ($connection->transactionLevel() > 0) {
            $connection->afterRollBack(function (): void {
                $this->forgetSnapshot();
            });
        }

        return $setting;
    }

    public function some(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function getEffectiveMailConfig(): array
    {
        try {
            $stored = Setting::get('mail_config') ?? [];
        } catch (\Throwable) {
            $stored = [];
        }

        if (! is_array($stored)) {
            $stored = [];
        }

        $rawDriver = $stored['driver'] ?? config('mail.default') ?: env('MAIL_MAILER', 'log');

        $driver = match ($rawDriver) {
            'smtp' => 'openkos/smtp',
            'log', 'array', 'sendmail', 'testing' => 'openkos/log',
            default => $rawDriver,
        };

        $envHost = config('mail.mailers.smtp.host') ?: env('MAIL_HOST');
        $envPort = config('mail.mailers.smtp.port') ?: env('MAIL_PORT');
        $envUsername = config('mail.mailers.smtp.username') ?: env('MAIL_USERNAME');
        $envPassword = config('mail.mailers.smtp.password') ?: env('MAIL_PASSWORD');
        $envEncryption = config('mail.mailers.smtp.encryption') ?: env('MAIL_ENCRYPTION');
        $envFromAddress = config('mail.from.address') ?: env('MAIL_FROM_ADDRESS');
        $envFromName = config('mail.from.name') ?: env('MAIL_FROM_NAME');

        $fromAddress = filled(data_get($stored, 'from_address')) ? $stored['from_address'] : $envFromAddress;
        $fromName = filled(data_get($stored, 'from_name')) ? $stored['from_name'] : $envFromName;

        $host = filled(data_get($stored, 'host')) ? (string) $stored['host'] : ($envHost ?: null);
        $port = filled(data_get($stored, 'port')) ? (int) $stored['port'] : ($envPort ? (int) $envPort : 587);
        $username = filled(data_get($stored, 'username')) ? (string) $stored['username'] : ($envUsername ?: null);
        $password = filled(data_get($stored, 'password')) ? (string) $stored['password'] : ($envPassword ?: null);
        $encryption = filled(data_get($stored, 'encryption')) ? (string) $stored['encryption'] : ($envEncryption ?: null);

        if (isset($stored['drivers']) && is_array($stored['drivers'])) {
            $config = $stored;
            $config['driver'] = $driver;
            $config['host'] ??= $host;
            $config['port'] ??= $port;
            $config['username'] ??= $username;
            $config['password'] ??= $password;
            $config['encryption'] ??= $encryption;
            $config['from_address'] ??= $fromAddress;
            $config['from_name'] ??= $fromName;
        } else {
            $config = [
                'driver' => $driver,
                'from' => array_filter([
                    'address' => $fromAddress ?: null,
                    'name' => $fromName ?: null,
                ], static fn ($value) => $value !== null),
                'drivers' => [
                    'openkos/smtp' => array_filter([
                        'host' => $host,
                        'port' => $port,
                        'username' => $username,
                        'password' => $password,
                        'encryption' => $encryption,
                    ], static fn ($value) => $value !== null),
                    'openkos/log' => [
                        'log_body' => false,
                    ],
                ],
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'encryption' => $encryption,
                'from_address' => $fromAddress,
                'from_name' => $fromName,
            ];
        }

        $config['from'] ??= array_filter([
            'address' => $fromAddress ?: null,
            'name' => $fromName ?: null,
        ], static fn ($value) => $value !== null);

        foreach ($config['drivers'] ?? [] as $key => $driverConfig) {
            if (
                isset($driverConfig['encryption']) &&
                (($driverConfig['encryption'] === 'null') || ($driverConfig['encryption'] === ''))
            ) {
                $config['drivers'][$key]['encryption'] = null;
            }
        }

        if (($config['encryption'] ?? null) === 'null' || ($config['encryption'] ?? null) === '') {
            $config['encryption'] = null;
        }

        return $config;
    }

    private function allWithDefaults(): array
    {
        $this->loadSnapshot();

        $defaults = [];
        foreach ($this->registry->all() as $key => $def) {
            $defaults[$key] = $this->caster->serialize($def['default'], $def['cast']);
        }

        $stored = [];
        foreach ($this->storedSettings as $key => $setting) {
            $stored[$key] = $setting['value'];
        }

        return array_merge($defaults, $stored);
    }

    private function loadSnapshot(): void
    {
        if ($this->snapshotLoaded) {
            return;
        }

        foreach (Setting::query()->select(['key', 'value', 'type'])->get() as $setting) {
            $this->storedSettings[$setting->key] = [
                'value' => $setting->value,
                'type' => $setting->type,
            ];
        }

        $this->snapshotLoaded = true;
    }

    private function resolveStoredSetting(string $key): mixed
    {
        if (! array_key_exists($key, $this->resolvedSettings)) {
            $setting = $this->storedSettings[$key];
            $this->resolvedSettings[$key] = $this->caster->deserialize($setting['value'], $setting['type']);
        }

        return $this->resolvedSettings[$key];
    }

    private function forgetSnapshot(): void
    {
        $this->storedSettings = [];
        $this->resolvedSettings = [];
        $this->snapshotLoaded = false;
    }
}
