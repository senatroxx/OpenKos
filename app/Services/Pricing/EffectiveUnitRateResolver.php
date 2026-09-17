<?php

namespace App\Services\Pricing;

use App\Enums\BillingUnit;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\UnitTypeRate;
use Illuminate\Support\Collection;

final class EffectiveUnitRateResolver
{
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

        $effective = $unitTypeRates->mapWithKeys(fn (UnitTypeRate $rate): array => [
            $this->identity($rate) => ['rate' => $rate, 'source' => 'unit_type'],
        ]);

        foreach ($unitRates as $rate) {
            /** @var UnitRate $rate */
            $effective[$this->identity($rate)] = ['rate' => $rate, 'source' => 'unit'];
        }

        return $effective
            ->sortBy(fn (array $item): array => [
                $this->billingOrder($item['rate']->billing_unit->value),
                $item['rate']->billing_interval,
                $item['rate']->currency,
                $item['rate']->id,
            ])
            ->values();
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

    private function identity(UnitRate|UnitTypeRate $rate): string
    {
        return implode('|', [$rate->billing_interval, $rate->billing_unit->value, $rate->currency]);
    }

    private function billingOrder(string $unit): int
    {
        return ['day' => 1, 'week' => 2, 'month' => 3, 'year' => 4][$unit] ?? 5;
    }
}
