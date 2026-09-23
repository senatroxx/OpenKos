<?php

namespace App\Actions\Applications;

use App\Data\Application\SubmitApplicationData;
use App\Enums\ApplicationTargetType;
use App\Models\Application;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use App\Results\Application\ApplicationResult;
use App\Services\Payments\MoneyConverter;
use App\Services\Pricing\EffectiveUnitRateResolver;
use Illuminate\Support\Facades\DB;

final class SubmitApplication
{
    public function __construct(
        private EffectiveUnitRateResolver $effectiveUnitRateResolver,
        private MoneyConverter $money,
    ) {}

    public function execute(User $user, SubmitApplicationData $data): ApplicationResult
    {
        $property = Property::query()->where('public_slug', $data->propertySlug)->first();
        if (! $property || ! $property->isPubliclyVisible()) {
            return ApplicationResult::error(__('This listing is no longer available for applications.'));
        }

        if ($data->targetType === ApplicationTargetType::WholeProperty) {
            if ($data->unitTypeSlug !== null || ! $property->rental_mode->supportsWholePropertyRental()) {
                return ApplicationResult::error(__('Choose a valid whole-property offering.'));
            }
        } elseif ($data->targetType !== ApplicationTargetType::UnitType || $data->unitTypeSlug === null || ! $property->rental_mode->supportsUnitInventory()) {
            return ApplicationResult::error(__('Choose a valid unit type offering.'));
        }

        $unitType = $data->unitTypeSlug === null ? null : UnitType::query()
            ->where('public_slug', $data->unitTypeSlug)
            ->where('property_id', $property->id)
            ->viablePublicOffering()
            ->first();

        if ($data->targetType === ApplicationTargetType::UnitType && $unitType === null) {
            return ApplicationResult::error(__('This unit type is no longer available for applications.'));
        }

        $rate = $this->resolveRate($property, $unitType, $data);

        if ($rate === null) {
            return ApplicationResult::error(__('Choose a valid rental option.'));
        }

        return DB::transaction(function () use ($user, $data, $property, $unitType, $rate): ApplicationResult {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $key = implode('|', [$user->id, $data->targetType->value, $property->id, $unitType?->id ?? 'property']);
            if (Application::query()->where('open_application_key', $key)->lockForUpdate()->exists()) {
                return ApplicationResult::error(__('You already have an open application for this offering.'));
            }

            return ApplicationResult::success(Application::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
                'unit_type_id' => $unitType?->id,
                'target_type' => $data->targetType,
                'status' => 'new',
                'applicant_name' => $user->name,
                'applicant_email' => $user->email,
                'applicant_phone' => $user->phone,
                'intended_move_in_date' => $data->intendedMoveInDate,
                'rental_billing_unit' => $rate['billing_unit'],
                'rental_billing_interval' => $rate['billing_interval'],
                'rental_currency' => $rate['currency'],
                'rental_amount' => $rate['amount'],
                'applicant_message' => $data->applicantMessage,
                'open_application_key' => $key,
            ]));
        });
    }

    /** @return array{billing_unit: string, billing_interval: int, currency: string, amount: string}|null */
    private function resolveRate(Property $property, ?UnitType $unitType, SubmitApplicationData $data): ?array
    {
        $matches = [];

        if ($unitType === null) {
            foreach ($property->activePropertyRates()->get() as $rate) {
                if ($rate->billing_unit->value === $data->rentalBillingUnit
                    && $rate->billing_interval === $data->rentalBillingInterval
                    && $rate->currency === $data->rentalCurrency) {
                    $matches[] = $rate;
                }
            }
        } else {
            foreach ($unitType->units()->get() as $unit) {
                foreach ($this->effectiveUnitRateResolver->resolve($unit) as $resolved) {
                    $rate = $resolved['rate'];
                    if ($rate->billing_unit->value === $data->rentalBillingUnit
                        && $rate->billing_interval === $data->rentalBillingInterval
                        && $rate->currency === $data->rentalCurrency) {
                        $matches[] = $rate;
                    }
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        $rate = collect($matches)->sort(fn ($left, $right): int => $this->money->compare((string) $left->amount, (string) $right->amount))->first();

        return [
            'billing_unit' => $rate->billing_unit->value,
            'billing_interval' => $rate->billing_interval,
            'currency' => $rate->currency,
            'amount' => (string) $rate->amount,
        ];
    }
}
