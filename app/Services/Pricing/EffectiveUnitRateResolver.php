<?php

namespace App\Services\Pricing;

use App\Business\Pricing\EffectiveRateResolver;
use App\Data\Pricing\RateData;
use App\Enums\BillingUnit;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\UnitTypeRate;
use Illuminate\Support\Collection;

final class EffectiveUnitRateResolver
{
    public function __construct(private EffectiveRateResolver $resolver) {}

    /**
     * @return Collection<int, array{rate: UnitRate|UnitTypeRate, source: 'unit'|'unit_type'}>
     */
    public function resolve(Unit $unit): Collection
    {
        $unitRates = $unit->relationLoaded('activeRates')
            ? $unit->activeRates
            : $unit->activeRates()->get();
        $unitTypeRates = $unit->unitType === null
            ? collect()
            : ($unit->unitType->relationLoaded('activeRates')
                ? $unit->unitType->activeRates
                : $unit->unitType->activeRates()->get());

        $models = $unitRates->mapWithKeys(fn (UnitRate $rate): array => ['unit|'.$rate->id => $rate]);

        foreach ($unitTypeRates as $rate) {
            $models->put('unit_type|'.$rate->id, $rate);
        }

        return collect($this->resolver->resolve(
            $unitRates->map(fn (UnitRate $rate): RateData => $this->toData($rate, 'unit'))->all(),
            $unitTypeRates->map(fn (UnitTypeRate $rate): RateData => $this->toData($rate, 'unit_type'))->all(),
        ))->map(fn (RateData $rate): array => [
            'rate' => $models->get($rate->source.'|'.$rate->id),
            'source' => $rate->source,
        ])->values();
    }

    /**
     * @return array{rate: UnitRate|UnitTypeRate, source: 'unit'|'unit_type'}|null
     */
    public function find(Unit $unit, int $interval, BillingUnit|string $billingUnit, string $currency): ?array
    {
        $billingUnit = $billingUnit instanceof BillingUnit ? $billingUnit->value : $billingUnit;

        return $this->resolve($unit)->first(fn (array $item): bool => $item['rate']->billing_interval === $interval
            && $item['rate']->billing_unit->value === $billingUnit
            && $item['rate']->currency === $currency
        );
    }

    private function toData(UnitRate|UnitTypeRate $rate, string $source): RateData
    {
        return new RateData(
            id: $rate->id,
            billingInterval: $rate->billing_interval,
            billingUnit: $rate->billing_unit->value,
            amount: (string) $rate->amount,
            currency: $rate->currency,
            source: $source,
        );
    }
}
