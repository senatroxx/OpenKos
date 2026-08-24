<?php

use App\Models\Setting;
use App\Services\Settings\InstallationCurrencySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('falls back to the default currency when supported currencies are missing', function () {
    Setting::set('currency', 'USD');

    expect(app(InstallationCurrencySettings::class)->supported())->toBe(['USD']);
});

it('preserves normalized supported-currency order', function () {
    $settings = app(InstallationCurrencySettings::class);

    expect($settings->normalize([' usd ', 'IDR'], default: 'USD'))
        ->toBe(['USD', 'IDR']);
});

it('falls back safely when persisted supported currencies are malformed', function () {
    Setting::set('currency', 'IDR');
    Setting::set('supported_currencies', ['unknown']);

    expect(app(InstallationCurrencySettings::class)->supported())->toBe(['IDR']);
});

it('emits row-level locking SQL on supported production database drivers', function () {
    if (! in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'], true)) {
        $this->markTestSkipped('SQLite does not provide row-level SELECT ... FOR UPDATE locking.');
    }

    Setting::set('currency', 'IDR');
    Setting::set('supported_currencies', ['IDR']);
    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    DB::transaction(function (): void {
        app(InstallationCurrencySettings::class)->lockForUpdate();
    });

    expect(collect(DB::connection()->getQueryLog())
        ->pluck('query')
        ->contains(fn (string $query): bool => str_contains(strtolower($query), 'for update')))
        ->toBeTrue();
});
