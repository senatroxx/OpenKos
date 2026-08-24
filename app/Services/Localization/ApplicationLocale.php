<?php

namespace App\Services\Localization;

use App\Models\Setting;

final class ApplicationLocale
{
    private const DEFAULT_LOCALE_ALIASES = [
        'en' => 'en',
        'en-us' => 'en',
        'id' => 'id',
        'id-id' => 'id',
    ];

    private const DEFAULT_INTL_LOCALES = [
        'en' => 'en-US',
        'id' => 'id-ID',
    ];

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = $this->config('app.supported_locales', [
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
        ]);

        return is_array($options) ? $options : [];
    }

    public function normalize(mixed $locale): ?string
    {
        if (! is_string($locale) || trim($locale) === '') {
            return null;
        }

        $normalized = strtolower(str_replace('_', '-', trim($locale)));
        $aliases = $this->config('app.locale_aliases', self::DEFAULT_LOCALE_ALIASES);
        $resolved = is_array($aliases) ? ($aliases[$normalized] ?? null) : null;

        return is_string($resolved) ? $resolved : null;
    }

    public function resolve(mixed $locale = null): string
    {
        if ($locale === null) {
            try {
                $locale = Setting::get('locale');
            } catch (\Throwable) {
                $locale = null;
            }
        }

        return $this->normalize($locale)
            ?? $this->normalize((string) $this->config('app.fallback_locale', 'en'))
            ?? 'en';
    }

    public function apply(mixed $locale = null): string
    {
        $resolved = $this->resolve($locale);

        app()->setLocale($resolved);

        return $resolved;
    }

    public function current(): string
    {
        return $this->resolve(app()->getLocale());
    }

    public function intlLocale(mixed $locale = null): string
    {
        $resolved = $this->resolve($locale);

        $locales = $this->config('app.intl_locales', self::DEFAULT_INTL_LOCALES);

        return is_array($locales) ? ($locales[$resolved] ?? $resolved) : $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(?string $locale = null): array
    {
        $path = lang_path($this->resolve($locale).'.json');

        if (! is_file($path)) {
            return [];
        }

        $messages = json_decode((string) file_get_contents($path), true);

        return is_array($messages) ? $messages : [];
    }

    public function fallback(): string
    {
        return $this->normalize((string) $this->config('app.fallback_locale', 'en')) ?? 'en';
    }

    private function config(string $key, mixed $default): mixed
    {
        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
