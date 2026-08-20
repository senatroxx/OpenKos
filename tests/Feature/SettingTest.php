<?php

use App\Models\Setting;
use App\Services\Settings\SettingManager;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenKOS\Core\Contracts\SettingsStore;

describe('Setting', function () {
    it('sets and gets values', function () {
        Setting::set('site_name', 'OpenKOS');

        expect(Setting::get('site_name'))->toBe('OpenKOS');
    });

    it('returns null for missing keys', function () {
        expect(Setting::get('nonexistent'))->toBeNull();
    });

    it('gets all settings as array', function () {
        Setting::set('site_name', 'Kos Ku');
        Setting::set('country_code', 'XX');

        $all = Setting::get();

        expect($all)->toBeArray();
        expect($all['site_name'])->toBe('Kos Ku');
        expect($all['country_code'])->toBe('XX');
    });

    it('returns typed values', function () {
        Setting::set('reminder_enabled', true, 'boolean');
        Setting::set('reminder_days_before', 5, 'integer');
        Setting::set('reminder_overdue_intervals', [1, 3, 7], 'array');

        expect(Setting::get('reminder_enabled'))->toBeTrue();
        expect(Setting::get('reminder_days_before'))->toBe(5);
        expect(Setting::get('reminder_overdue_intervals'))->toBe([1, 3, 7]);
    });

    it('returns only specified keys', function () {
        Setting::set('site_name', 'Kos Budi');
        Setting::set('country_code', 'ID');
        Setting::set('locale', 'id');

        $result = Setting::some(['site_name', 'locale']);

        expect($result)->toHaveKeys(['site_name', 'locale']);
        expect($result)->not->toHaveKey('country_code');
        expect($result['site_name'])->toBe('Kos Budi');
        expect($result['locale'])->toBe('id');
    });

    it('loads stored settings once per lifecycle', function () {
        Setting::set('site_name', 'Kos Ku');
        Setting::set('reminder_enabled', true, 'boolean');

        $settingsQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$settingsQueries): void {
            $sql = strtolower($query->sql);

            if (
                str_contains($sql, ' from "settings"') ||
                str_contains($sql, ' from `settings`') ||
                str_contains($sql, ' from settings')
            ) {
                $settingsQueries++;
            }
        });

        expect(Setting::get('site_name'))->toBe('Kos Ku')
            ->and(Setting::get('reminder_enabled'))->toBeTrue()
            ->and(Setting::some(['site_name', 'reminder_enabled']))->toMatchArray([
                'site_name' => 'Kos Ku',
                'reminder_enabled' => true,
            ])
            ->and(Setting::get())->toBeArray()
            ->and($settingsQueries)->toBe(1);
    });

    it('does not use persistent cache unless explicitly configured', function () {
        config(['settings_cache.store' => null]);
        Setting::set('site_name', 'No L2');

        app()->forgetScopedInstances();

        $settingsQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$settingsQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, ' from "settings"') || str_contains($sql, ' from settings')) {
                $settingsQueries++;
            }
        });

        expect(Setting::get('site_name'))->toBe('No L2');

        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('No L2')
            ->and($settingsQueries)->toBe(2);
    });

    it('uses a warm persistent snapshot for a new lifecycle', function () {
        config([
            'settings_cache.store' => 'array',
            'settings_cache.ttl' => 60,
        ]);
        Cache::store('array')->flush();
        Setting::set('site_name', 'Warm L2');

        app()->forgetScopedInstances();

        $settingsQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$settingsQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, ' from "settings"') || str_contains($sql, ' from settings')) {
                $settingsQueries++;
            }
        });

        expect(Setting::get('site_name'))->toBe('Warm L2');

        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('Warm L2')
            ->and($settingsQueries)->toBe(1);
    });

    it('keeps the generation marker durable while snapshots expire', function () {
        config([
            'settings_cache.store' => 'array',
            'settings_cache.ttl' => 60,
        ]);
        Cache::store('array')->flush();
        Setting::set('site_name', 'Durable Generation');

        app()->forgetScopedInstances();
        Setting::get('site_name');

        $cache = Cache::store('array');
        $store = $cache->getStore();
        $generation = $cache->get(SettingManager::CACHE_GENERATION_KEY);
        $entries = $store->all();

        expect($generation)->toBeString()
            ->and($entries[$store->getPrefix().SettingManager::CACHE_GENERATION_KEY]['expiresAt'])->toBe(0)
            ->and($entries[$store->getPrefix().SettingManager::CACHE_SNAPSHOT_PREFIX.$generation]['expiresAt'])->toBeGreaterThan(0);
    });

    it('invalidates the persistent generation only after commit', function () {
        config(['settings_cache.store' => 'array']);
        Cache::store('array')->flush();
        Setting::set('site_name', 'Before');

        app()->forgetScopedInstances();
        expect(Setting::get('site_name'))->toBe('Before');

        $cache = Cache::store('array');
        $beforeGeneration = $cache->get(SettingManager::CACHE_GENERATION_KEY);

        DB::transaction(function () use ($cache, $beforeGeneration): void {
            Setting::set('site_name', 'After');

            expect($cache->get(SettingManager::CACHE_GENERATION_KEY))->toBe($beforeGeneration);
        });

        expect($cache->get(SettingManager::CACHE_GENERATION_KEY))->not->toBe($beforeGeneration);

        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('After');
    });

    it('leaves the persistent generation unchanged after rollback', function () {
        config(['settings_cache.store' => 'array']);
        Cache::store('array')->flush();
        Setting::set('site_name', 'Before');

        app()->forgetScopedInstances();
        expect(Setting::get('site_name'))->toBe('Before');

        $cache = Cache::store('array');
        $beforeGeneration = $cache->get(SettingManager::CACHE_GENERATION_KEY);

        try {
            DB::transaction(function (): void {
                Setting::set('site_name', 'After');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('rollback');
        }

        expect($cache->get(SettingManager::CACHE_GENERATION_KEY))->toBe($beforeGeneration);

        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('Before');
    });

    it('caches encrypted settings without resolving their values', function () {
        config(['settings_cache.store' => 'array']);
        Cache::store('array')->flush();
        Setting::set('mail_config', ['password' => 'top-secret'], 'encrypted:array');

        app()->forgetScopedInstances();

        expect(Setting::get('mail_config'))->toBe(['password' => 'top-secret']);

        $cache = Cache::store('array');
        $generation = $cache->get(SettingManager::CACHE_GENERATION_KEY);
        $snapshot = $cache->get(SettingManager::CACHE_SNAPSHOT_PREFIX.$generation);
        $stored = Setting::query()->where('key', 'mail_config')->value('value');

        expect($snapshot['mail_config']['value'])->toBe($stored)
            ->and($snapshot['mail_config']['type'])->toBe('encrypted:array')
            ->and(json_encode($snapshot))->not->toContain('top-secret');
    });

    it('does not publish a snapshot under a stale generation', function () {
        config(['settings_cache.store' => 'array']);
        Cache::store('array')->flush();

        $cache = Cache::store('array');
        $cache->forever(SettingManager::CACHE_GENERATION_KEY, 'old-generation');
        Setting::create(['key' => 'site_name', 'value' => 'Current', 'type' => 'string']);

        $settingsQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$settingsQueries, $cache): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, ' from "settings"') || str_contains($sql, ' from settings')) {
                $settingsQueries++;
                $cache->forever(SettingManager::CACHE_GENERATION_KEY, 'new-generation');
            }
        });

        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('Current')
            ->and($cache->get(SettingManager::CACHE_SNAPSHOT_PREFIX.'old-generation'))->toBeNull()
            ->and($cache->get(SettingManager::CACHE_SNAPSHOT_PREFIX.'new-generation'))->toBeArray()
            ->and($settingsQueries)->toBe(2);
    });

    it('falls back to PostgreSQL when the persistent cache fails', function () {
        config(['settings_cache.store' => 'broken']);
        Setting::create(['key' => 'site_name', 'value' => 'Database Fallback', 'type' => 'string']);

        $repository = Mockery::mock(CacheRepository::class);
        $repository->shouldReceive('get')
            ->twice()
            ->with(SettingManager::CACHE_GENERATION_KEY)
            ->andThrow(new RuntimeException('cache unavailable'));

        $factory = Mockery::mock(CacheFactory::class);
        $factory->shouldReceive('store')
            ->twice()
            ->with('broken')
            ->andReturn($repository);

        app()->instance(CacheFactory::class, $factory);
        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('Database Fallback')
            ->and(Setting::get('site_name'))->toBe('Database Fallback');

        app()->forgetScopedInstances();

        expect(Setting::get('site_name'))->toBe('Database Fallback');
    });

    it('keeps raw values for all settings and typed values for keyed reads', function () {
        Setting::set('reminder_enabled', true, 'boolean');
        Setting::set('reminder_overdue_intervals', [1, 3], 'array');

        $all = Setting::get();

        expect($all['reminder_enabled'])->toBe('true')
            ->and($all['reminder_overdue_intervals'])->toBe('[1,3]')
            ->and(Setting::get('reminder_enabled'))->toBeTrue()
            ->and(Setting::get('reminder_overdue_intervals'))->toBe([1, 3]);
    });

    it('clears the snapshot after a rolled back setting mutation', function () {
        Setting::set('site_name', 'Before');

        try {
            DB::transaction(function (): void {
                Setting::set('site_name', 'After');

                expect(Setting::get('site_name'))->toBe('After');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('rollback');
        }

        expect(Setting::get('site_name'))->toBe('Before');
    });

    it('preserves outer transaction values after a nested rollback', function () {
        Setting::set('site_name', 'Before');
        Setting::get('site_name');

        DB::transaction(function (): void {
            Setting::set('site_name', 'Outer');

            try {
                DB::transaction(function (): void {
                    Setting::set('site_name', 'Inner');

                    throw new RuntimeException('nested rollback');
                });
            } catch (RuntimeException $exception) {
                expect($exception->getMessage())->toBe('nested rollback');
            }

            expect(Setting::get('site_name'))->toBe('Outer');
        });

        expect(Setting::get('site_name'))->toBe('Outer');
    });

    it('resolves the current scoped manager through the platform store', function () {
        $manager = app(SettingManager::class);
        $store = app(SettingsStore::class);

        app()->forgetScopedInstances();

        expect(app(SettingManager::class))->not->toBe($manager);

        Setting::set('site_name', 'Scoped');

        expect($store->get('site_name'))->toBe('Scoped');
    });

    it('includes defaults when getting all', function () {
        Setting::set('site_name', 'Kos Saya');

        $all = Setting::get();

        expect($all['site_name'])->toBe('Kos Saya');
        expect($all['locale'])->toBe('id');
        expect($all['timezone'])->toBe('Asia/Jakarta');
    });
});
