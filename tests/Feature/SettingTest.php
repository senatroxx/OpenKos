<?php

use App\Models\Setting;
use App\Services\Settings\SettingManager;
use Illuminate\Database\Events\QueryExecuted;
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
