<?php

namespace App\Actions\Units;

use App\Models\Unit;
use App\Models\UnitRate;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use InvalidArgumentException;

final class CreateUnitRate
{
    public function __construct(
        private InstallationCurrencySettings $currencies,
        private MoneyConverter $money,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Unit $unit, array $attributes): UnitRate
    {
        $this->currencies->lockForUpdate();

        $currency = $this->money->normalizeCurrency($attributes['currency'] ?? null);

        if (! $this->currencies->supports($currency, fresh: true)) {
            throw new InvalidArgumentException('This currency is not enabled for new pricing rates.');
        }

        $attributes['currency'] = $currency;
        $attributes['amount'] = $this->money->normalizeAmount((string) $attributes['amount'], $currency);

        return $unit->rates()->create($attributes);
    }
}
