<?php

namespace App\Business\Pricing;

use App\Data\Pricing\RateData;

final class EffectiveRateResolver
{
    /**
     * @param  array<int, RateData>  $unitRates
     * @param  array<int, RateData>  $unitTypeRates
     * @return array<int, RateData>
     */
    public function resolve(array $unitRates, array $unitTypeRates): array
    {
        $effective = [];

        foreach ($unitTypeRates as $rate) {
            $effective[$rate->identity()] = $rate;
        }

        foreach ($unitRates as $rate) {
            $effective[$rate->identity()] = $rate;
        }

        usort($effective, function (RateData $left, RateData $right): int {
            $billingOrder = ['day' => 1, 'week' => 2, 'month' => 3, 'year' => 4];
            $comparison = ($billingOrder[$left->billingUnit] ?? 5) <=> ($billingOrder[$right->billingUnit] ?? 5);

            if ($comparison !== 0) {
                return $comparison;
            }

            return [$left->billingInterval, $left->currency, $left->id]
                <=> [$right->billingInterval, $right->currency, $right->id];
        });

        return $effective;
    }
}
