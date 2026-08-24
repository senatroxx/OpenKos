<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\Payments\MoneyConverter;
use InvalidArgumentException;

final class InstallationCurrencySettings
{
    public function __construct(
        private MoneyConverter $money,
    ) {}

    public function default(bool $fresh = false): string
    {
        if ($fresh) {
            return $this->freshDefault();
        }

        try {
            return $this->money->normalizeCurrency(Setting::get('currency'));
        } catch (\Throwable) {
            return $this->money->normalizeCurrency(config('settings.currency.default', 'IDR'));
        }
    }

    /**
     * @return array<int, string>
     */
    public function supported(): array
    {
        return $this->resolveSupported(Setting::get('supported_currencies'), $this->default());
    }

    /**
     * Resolve the latest committed settings directly from the database.
     *
     * This is used immediately before creating a new rate so a scoped
     * settings snapshot cannot allow a currency removed by another request.
     *
     * @return array<int, string>
     */
    public function freshSupported(): array
    {
        $default = $this->freshDefault();
        $configured = $this->storedValue('supported_currencies');

        return $this->resolveSupported($configured, $default);
    }

    public function lockForUpdate(): void
    {
        Setting::query()
            ->whereIn('key', ['currency', 'supported_currencies'])
            ->lockForUpdate()
            ->get();
    }

    public function hasStoredSupportedCurrencies(): bool
    {
        $configured = Setting::get('supported_currencies');

        if (! is_array($configured) || $configured === []) {
            return false;
        }

        try {
            $this->normalize($configured, $this->default());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function supports(?string $currency, bool $fresh = false): bool
    {
        try {
            $normalized = $currency === null
                ? ($fresh ? $this->freshDefault() : $this->default())
                : $this->money->normalizeCurrency($currency);
        } catch (\Throwable) {
            return false;
        }

        $supported = $fresh ? $this->freshSupported() : $this->supported();

        return in_array($normalized, $supported, true);
    }

    /**
     * @param  array<int, mixed>  $currencies
     * @return array<int, string>
     */
    public function normalize(array $currencies, ?string $default = null): array
    {
        if ($currencies === []) {
            throw new InvalidArgumentException('At least one supported currency is required.');
        }

        $normalized = [];
        foreach ($currencies as $currency) {
            if (! is_string($currency)) {
                throw new InvalidArgumentException('Supported currencies must be ISO 4217 codes.');
            }

            $normalized[] = strtoupper(trim($currency));
        }

        $normalized = array_values(array_unique($normalized));

        foreach ($normalized as $currency) {
            $this->money->normalizeCurrency($currency);
        }

        if ($default !== null) {
            $default = $this->money->normalizeCurrency($default);

            if (! in_array($default, $normalized, true)) {
                throw new InvalidArgumentException('The default currency must be supported.');
            }
        }

        return $normalized;
    }

    private function freshDefault(): string
    {
        try {
            return $this->money->normalizeCurrency($this->storedValue('currency'));
        } catch (\Throwable) {
            return $this->money->normalizeCurrency(config('settings.currency.default', 'IDR'));
        }
    }

    private function storedValue(string $key): mixed
    {
        return Setting::query()->where('key', $key)->first()?->resolveValue();
    }

    /**
     * @return array<int, string>
     */
    private function resolveSupported(mixed $configured, string $default): array
    {
        if (! is_array($configured) || $configured === []) {
            return [$default];
        }

        try {
            return $this->normalize($configured, $default);
        } catch (\Throwable) {
            return [$default];
        }
    }
}
