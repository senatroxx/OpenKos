<?php

use App\Models\Setting;

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

    it('includes defaults in allWithDefaults', function () {
        Setting::set('site_name', 'Kos Saya');

        $all = Setting::allWithDefaults();

        expect($all['site_name'])->toBe('Kos Saya');
        expect($all['locale'])->toBe('id');
        expect($all['timezone'])->toBe('Asia/Jakarta');
    });
});
