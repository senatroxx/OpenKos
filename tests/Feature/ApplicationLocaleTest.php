<?php

use App\Models\Setting;
use App\Services\Localization\ApplicationLocale;

test('installation locales are normalized to supported application locales', function () {
    $locale = app(ApplicationLocale::class);

    expect($locale->normalize('en-US'))->toBe('en')
        ->and($locale->normalize('id_ID'))->toBe('id')
        ->and($locale->normalize('fr'))->toBeNull();
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
