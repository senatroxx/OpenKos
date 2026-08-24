<?php

namespace App\Services\Localization;

use App\Models\Setting;

final class ApplicationLocale
{
    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return config('app.supported_locales', [
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
        ]);
    }

    public function normalize(?string $locale): ?string
    {
        if ($locale === null || trim($locale) === '') {
            return null;
        }

        $normalized = strtolower(str_replace('_', '-', trim($locale)));
        $aliases = config('app.locale_aliases', []);

        return is_array($aliases) ? ($aliases[$normalized] ?? null) : null;
    }

    public function resolve(?string $locale = null): string
    {
        if ($locale === null) {
            try {
                $locale = Setting::get('locale');
            } catch (\Throwable) {
                $locale = null;
            }
        }

        return $this->normalize($locale)
            ?? $this->normalize((string) config('app.fallback_locale'))
            ?? 'en';
    }

    public function apply(?string $locale = null): string
    {
        $resolved = $this->resolve($locale);

        app()->setLocale($resolved);

        return $resolved;
    }

    public function current(): string
    {
        return $this->resolve(app()->getLocale());
    }

    public function intlLocale(?string $locale = null): string
    {
        $resolved = $this->resolve($locale);

        $locales = config('app.intl_locales', []);

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
        return $this->normalize((string) config('app.fallback_locale')) ?? 'en';
    }
}
