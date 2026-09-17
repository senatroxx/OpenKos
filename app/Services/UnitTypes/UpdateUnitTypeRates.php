<?php

namespace App\Services\UnitTypes;

use App\Models\UnitType;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpdateUnitTypeRates
{
    public function __construct(
        private InstallationCurrencySettings $currencies,
        private MoneyConverter $money,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    public function execute(UnitType $unitType, array $rates, CarbonImmutable $expectedUpdatedAt): UnitType
    {
        return DB::transaction(function () use ($unitType, $rates, $expectedUpdatedAt): UnitType {
            $locked = UnitType::query()->lockForUpdate()->findOrFail($unitType->id);

            if (! $locked->updated_at?->equalTo($expectedUpdatedAt)) {
                throw ValidationException::withMessages([
                    'updated_at' => __('This Unit Type changed while you were editing it. Refresh and try again.'),
                ]);
            }

            $hasNewRate = collect($rates)->contains(fn (array $rate): bool => ! isset($rate['id']));
            if ($hasNewRate) {
                $this->currencies->lockForUpdate();
            }

            $normalized = collect($rates)->map(function (array $rate, int $index) use ($locked): array {
                $stored = isset($rate['id'])
                    ? $locked->rates()->whereKey($rate['id'])->firstOrFail()
                    : null;
                $currency = $stored?->currency ?? ($rate['currency'] ?? null);

                if ($stored !== null && isset($rate['currency']) && strtoupper((string) $rate['currency']) !== $stored->currency) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.currency" => __('An existing Unit Type rate currency cannot be changed; add a new rate variant instead.'),
                    ]);
                }

                if ($stored !== null && isset($rate['billing_interval'])
                    && (int) $rate['billing_interval'] !== $stored->billing_interval) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.billing_interval" => __('An existing Unit Type rate billing interval cannot be changed; add a new rate variant instead.'),
                    ]);
                }

                if ($stored !== null && isset($rate['billing_unit'])
                    && $rate['billing_unit'] !== $stored->billing_unit->value) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.billing_unit" => __('An existing Unit Type rate billing unit cannot be changed; add a new rate variant instead.'),
                    ]);
                }

                try {
                    $currency = $this->money->normalizeCurrency($currency);
                    if ($stored === null && ! $this->currencies->supports($currency, fresh: true)) {
                        throw new InvalidArgumentException('currency');
                    }
                    $rate['currency'] = $currency;
                    $rate['amount'] = $this->money->normalizeAmount((string) $rate['amount'], $currency);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.".($exception->getMessage() === 'currency' ? 'currency' : 'amount') => $exception->getMessage() === 'currency'
                            ? __('The selected currency is not supported.')
                            : __('The amount is invalid for the selected currency.'),
                    ]);
                }

                return $rate;
            })->values()->all();

            $submittedIds = collect($normalized)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
            $locked->rates()->when($submittedIds !== [], fn ($query) => $query->whereNotIn('id', $submittedIds))->update(['is_active' => false]);

            foreach ($normalized as $rate) {
                if (isset($rate['id'])) {
                    $locked->rates()->whereKey($rate['id'])->firstOrFail()->update([
                        'amount' => $rate['amount'],
                        'is_active' => $rate['is_active'] ?? true,
                    ]);
                } else {
                    $locked->rates()->create($rate);
                }
            }

            $locked->touch();

            return $locked->fresh(['rates']);
        });
    }
}
