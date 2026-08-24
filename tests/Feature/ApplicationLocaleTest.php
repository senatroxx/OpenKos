<?php

use App\Actions\Reminders\ForceSendReminder;
use App\Models\Lease;
use App\Models\Setting;
use App\Services\Localization\ApplicationLocale;
use App\Services\Payments\MoneyConverter;

test('installation locales are normalized to supported application locales', function () {
    $locale = app(ApplicationLocale::class);

    expect($locale->normalize('en-US'))->toBe('en')
        ->and($locale->normalize('id_ID'))->toBe('id')
        ->and($locale->normalize('fr'))->toBeNull()
        ->and($locale->normalize(['id']))->toBeNull()
        ->and($locale->resolve(['id']))->toBe('en');
});

test('http requests apply the configured locale and share its catalogs', function () {
    Setting::set('locale', 'id');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('app.locale', 'id')
            ->where('app.intl_locale', 'id-ID')
            ->where('i18n.locale', 'id')
            ->where('i18n.messages.General settings', 'Pengaturan umum')
            ->where('i18n.fallback', [])
        );
});

test('unsupported installation locales fall back to english', function () {
    Setting::set('locale', 'fr');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('app.locale', 'en')
            ->where('app.intl_locale', 'en-US')
            ->where('i18n.locale', 'en')
        );
});

test('manual reminder actions apply the configured locale', function () {
    Setting::set('locale', 'id');
    app()->setLocale('en');

    $result = app(ForceSendReminder::class)->execute(Lease::factory()->create());

    expect($result)->toBe('all_paid')
        ->and(app()->getLocale())->toBe('id');
});

test('direct money formatting uses the configured locale', function () {
    Setting::set('locale', 'id');
    app()->setLocale('en');

    expect((new MoneyConverter)->format('1500000', 'USD'))
        ->toBe(app(MoneyConverter::class)->format('1500000', 'USD'));
});
