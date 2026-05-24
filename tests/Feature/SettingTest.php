<?php

use App\Models\Setting;

describe('Setting::get()', function () {
    it('creates a record with Indonesia defaults when none exists', function () {
        $setting = Setting::get();

        expect($setting)->toBeInstanceOf(Setting::class);
        expect($setting->site_name)->toBe('OpenKOS');
        expect($setting->country_code)->toBe('ID');
        expect($setting->locale)->toBe('id');
        expect($setting->currency)->toBe('IDR');
        expect($setting->timezone)->toBe('Asia/Jakarta');
        expect(Setting::count())->toBe(1);
    });

    it('returns existing record on subsequent calls', function () {
        Setting::create([
            'site_name' => 'Kos Pak Budi',
            'country_code' => 'ID',
            'locale' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ]);

        $setting = Setting::get();

        expect($setting->site_name)->toBe('Kos Pak Budi');
        expect(Setting::count())->toBe(1);
    });

    it('preserves overridden values', function () {
        Setting::create([
            'site_name' => 'My Boarding House',
            'country_code' => 'XX',
            'locale' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $setting = Setting::get();

        expect($setting->country_code)->toBe('XX');
        expect($setting->locale)->toBe('en');
        expect($setting->currency)->toBe('USD');
        expect($setting->timezone)->toBe('UTC');
    });
});
