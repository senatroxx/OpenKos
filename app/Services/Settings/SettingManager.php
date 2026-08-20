<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class SettingManager
{
    public const CACHE_GENERATION_KEY = 'openkos:settings:version';

    public const CACHE_GENERATION_LOCK_KEY = 'openkos:settings:version:lock';

    public const CACHE_SNAPSHOT_PREFIX = 'openkos:settings:snapshot:';

    /**
     * @var array<string, array{value: mixed, type: string}>
     */
    private array $storedSettings = [];

    /**
     * @var array<string, mixed>
     */
    private array $resolvedSettings = [];

    private bool $snapshotLoaded = false;

    private bool $cacheResolved = false;

    private bool $cacheUnavailable = false;

    private ?CacheRepository $cacheRepository = null;

    public function __construct(
        private SettingRegistry $registry,
        private SettingCaster $caster,
        private CacheFactory $cacheFactory,
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
            $connection->afterCommit(function (): void {
                $this->invalidatePersistentSnapshot();
            });
            $connection->afterRollBack(function (): void {
                $this->forgetSnapshot();
            });
        } else {
            $this->invalidatePersistentSnapshot();
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

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $generation = $this->currentGeneration();

            if ($generation !== null) {
                $cached = $this->readPersistentSnapshot($generation);

                if ($cached !== null) {
                    $this->storedSettings = $cached;
                    $this->snapshotLoaded = true;

                    return;
                }
            }

            $this->loadDatabaseSnapshot();

            if ($generation === null || ! $this->generationIsCurrent($generation)) {
                if ($generation !== null && ! $this->cacheUnavailable) {
                    $this->forgetSnapshot();

                    continue;
                }

                $this->snapshotLoaded = true;

                return;
            }

            $this->writePersistentSnapshot($generation);
            $this->snapshotLoaded = true;

            return;
        }

        $this->loadDatabaseSnapshot();
        $this->snapshotLoaded = true;
    }

    private function loadDatabaseSnapshot(): void
    {
        $this->storedSettings = [];

        foreach (Setting::query()->select(['key', 'value', 'type'])->get() as $setting) {
            $this->storedSettings[$setting->key] = [
                'value' => $setting->value,
                'type' => $setting->type,
            ];
        }
    }

    private function currentGeneration(): ?string
    {
        $cache = $this->persistentCache();

        if ($cache === null) {
            return null;
        }

        try {
            $generation = $cache->get(self::CACHE_GENERATION_KEY);

            if (is_string($generation) && $generation !== '') {
                return $generation;
            }

            if ($generation !== null) {
                throw new \RuntimeException('Settings cache generation is malformed.');
            }

            $store = $cache->getStore();
            if (! $store instanceof LockProvider) {
                throw new \RuntimeException('Settings cache store does not support atomic locks.');
            }

            return $store->lock(self::CACHE_GENERATION_LOCK_KEY, 10)->block(5, function () use ($cache): string {
                $generation = $cache->get(self::CACHE_GENERATION_KEY);

                if (is_string($generation) && $generation !== '') {
                    return $generation;
                }

                if ($generation !== null) {
                    throw new \RuntimeException('Settings cache generation is malformed.');
                }

                $generation = (string) Str::uuid();

                if (! $cache->forever(self::CACHE_GENERATION_KEY, $generation)) {
                    throw new \RuntimeException('Settings cache generation could not be initialized.');
                }

                return $generation;
            });
        } catch (\Throwable $exception) {
            $this->disablePersistentCache($exception);

            return null;
        }
    }

    /**
     * @return array<string, array{value: mixed, type: string}>|null
     */
    private function readPersistentSnapshot(string $generation): ?array
    {
        $cache = $this->persistentCache();

        if ($cache === null) {
            return null;
        }

        try {
            $snapshot = $cache->get($this->snapshotKey($generation));

            if ($snapshot === null) {
                return null;
            }

            if (! $this->isValidSnapshot($snapshot)) {
                throw new \RuntimeException('Settings cache snapshot is malformed.');
            }

            return $snapshot;
        } catch (\Throwable $exception) {
            $this->disablePersistentCache($exception);

            return null;
        }
    }

    private function writePersistentSnapshot(string $generation): void
    {
        $cache = $this->persistentCache();

        if ($cache === null || ! $this->generationIsCurrent($generation)) {
            return;
        }

        try {
            if (! $cache->put($this->snapshotKey($generation), $this->storedSettings, $this->persistentCacheTtl())) {
                throw new \RuntimeException('Settings cache snapshot could not be written.');
            }
        } catch (\Throwable $exception) {
            $this->disablePersistentCache($exception);
        }
    }

    private function generationIsCurrent(string $generation): bool
    {
        $current = $this->currentGeneration();

        return $current !== null && hash_equals($generation, $current);
    }

    private function invalidatePersistentSnapshot(): void
    {
        $cache = $this->persistentCache();

        if ($cache === null) {
            return;
        }

        try {
            $store = $cache->getStore();
            if (! $store instanceof LockProvider) {
                throw new \RuntimeException('Settings cache store does not support atomic locks.');
            }

            $store->lock(self::CACHE_GENERATION_LOCK_KEY, 10)->block(5, function () use ($cache): void {
                if (! $cache->forever(self::CACHE_GENERATION_KEY, (string) Str::uuid())) {
                    throw new \RuntimeException('Settings cache generation could not be invalidated.');
                }
            });
        } catch (\Throwable $exception) {
            $this->disablePersistentCache($exception);
        }
    }

    private function persistentCache(): ?CacheRepository
    {
        if ($this->cacheUnavailable) {
            return null;
        }

        if ($this->cacheResolved) {
            return $this->cacheRepository;
        }

        $this->cacheResolved = true;
        $store = config('settings_cache.store');

        if (! is_string($store) || trim($store) === '') {
            return null;
        }

        try {
            return $this->cacheRepository = $this->cacheFactory->store($store);
        } catch (\Throwable $exception) {
            $this->disablePersistentCache($exception);

            return null;
        }
    }

    private function disablePersistentCache(\Throwable $exception): void
    {
        if ($this->cacheUnavailable) {
            return;
        }

        $this->cacheUnavailable = true;
        $this->cacheRepository = null;
        report($exception);
    }

    private function persistentCacheTtl(): int
    {
        return max(1, (int) config('settings_cache.ttl', 3600));
    }

    private function snapshotKey(string $generation): string
    {
        return self::CACHE_SNAPSHOT_PREFIX.$generation;
    }

    private function isValidSnapshot(mixed $snapshot): bool
    {
        if (! is_array($snapshot)) {
            return false;
        }

        foreach ($snapshot as $key => $setting) {
            if (
                ! is_string($key) ||
                ! is_array($setting) ||
                ! array_key_exists('value', $setting) ||
                ! is_string($setting['type'] ?? null)
            ) {
                return false;
            }
        }

        return true;
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
