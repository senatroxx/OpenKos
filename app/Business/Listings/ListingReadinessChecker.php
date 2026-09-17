<?php

namespace App\Business\Listings;

use App\Enums\AmenityScope;
use App\Enums\BillingUnit;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\UnitType;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ListingReadinessChecker
{
    public function __construct(private MoneyConverter $money) {}

    /**
     * @return array{
     *     can_publish: bool,
     *     is_published: bool,
     *     is_publicly_visible: bool,
     *     public_url: string|null,
     *     unassigned_units_count: int,
     *     blockers: list<array{key: string, message: string, action: array{label: string, url: string}|null}>,
     *     recommendations: list<array{key: string, message: string, action: array{label: string, url: string}|null}>,
     *     whole_property: array{
     *         is_listed: bool,
     *         has_active_pricing: bool,
     *         starting_price: array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null,
     *         reason: string|null,
     *         action: array{label: string, url: string}|null,
     *     }|null,
     *     unit_types: list<array{
     *         id: int,
     *         name: string,
     *         is_active: bool,
     *         is_included: bool,
     *         is_viable_if_included: bool,
     *         physical_units: int,
     *         available_units: int,
     *         has_active_pricing: bool,
     *         starting_price: array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null,
     *         status: 'ready'|'excluded'|'blocked'|'inactive',
     *         reason: string|null,
     *         action: array{label: string, url: string}|null,
     *     }>
     * }
     */
    public function analyze(Property $property): array
    {
        $this->loadRelations($property);

        $hasViablePublicOffering = $property->hasViablePublicOffering();
        $canPublish = $property->is_active && $hasViablePublicOffering;
        $isPubliclyVisible = $property->isPubliclyVisible();
        $unitTypes = $property->rental_mode->supportsUnitInventory()
            ? $property->unitTypes
            : new Collection;
        $unitTypeCards = $unitTypes
            ->map(fn (UnitType $unitType): array => $this->unitTypePayload($property, $unitType))
            ->values()
            ->all();
        $unassignedUnitsCount = $this->unassignedUnitsCount($property);
        $wholeProperty = $property->rental_mode->supportsWholePropertyRental()
            ? $this->wholePropertyPayload($property, $isPubliclyVisible)
            : null;

        return [
            'can_publish' => $canPublish,
            'is_published' => (bool) $property->is_published,
            'is_publicly_visible' => $isPubliclyVisible,
            'public_url' => $isPubliclyVisible
                ? route('public.portal.show', ['property' => $property->public_slug], absolute: false)
                : null,
            'whole_property' => $wholeProperty,
            'unassigned_units_count' => $unassignedUnitsCount,
            'blockers' => $this->blockers($property, $canPublish, $unitTypeCards),
            'recommendations' => $this->recommendations(
                $property,
                $canPublish,
                $unitTypeCards,
                $unassignedUnitsCount,
            ),
            'unit_types' => $unitTypeCards,
        ];
    }

    private function loadRelations(Property $property): void
    {
        $property->loadMissing(['activePropertyRates', 'facilities']);

        if ($property->rental_mode->supportsUnitInventory()) {
            $property->loadMissing([
                'unitTypes' => function ($query): void {
                    $query
                        ->withCount([
                            'units',
                            'units as available_units_count' => fn (Builder $query) => $query->availableForAssignment(),
                            'units as priced_units_count' => fn (Builder $query) => $query->whereHas(
                                'rates',
                                fn (Builder $query) => $query->where('is_active', true),
                            ),
                            'units as eligible_public_units_count' => fn (Builder $query) => $query->eligibleForPublicOffering(),
                        ])
                        ->with(['units' => fn ($query) => $query->with('activeRates')])
                        ->orderBy('name');
                },
            ]);
        }
    }

    /**
     * @param  list<array{
     *     id: int,
     *     name: string,
     *     is_active: bool,
     *     is_included: bool,
     *     is_viable_if_included: bool,
     *     physical_units: int,
     *     available_units: int,
     *     has_active_pricing: bool,
     *     starting_price: array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null,
     *     status: 'ready'|'excluded'|'blocked'|'inactive',
     *     reason: string|null,
     *     reason_label: string|null,
     *     action: array{label: string, url: string}|null,
     * }>  $unitTypeCards
     * @return list<array{key: string, message: string, action: array{label: string, url: string}|null}>
     */
    private function blockers(Property $property, bool $canPublish, array $unitTypeCards): array
    {
        $blockers = [];

        if (! $property->is_active) {
            $blockers[] = $this->issue(
                'inactive_property',
                'Inactive properties cannot be published.',
                $this->action('Manage property', route('properties.index', absolute: false)),
            );
        }

        if ($canPublish) {
            return $blockers;
        }

        $hasViableWholePropertyOffering = $property->hasViableWholePropertyOffering();
        $hasViableUnitTypeOffering = $property->hasViableUnitTypeOffering();

        if ($property->rental_mode->supportsWholePropertyRental() && ! $hasViableWholePropertyOffering) {
            $blockers[] = $this->issue(
                'property_rate_required',
                'Add an active property rate before publishing the whole-property offering.',
                $this->action('Open Pricing', route('properties.pricing.index', $property, absolute: false)),
            );
        }

        if ($property->rental_mode->supportsUnitInventory() && ! $hasViableUnitTypeOffering) {
            $includedUnitTypes = collect($unitTypeCards)->filter(
                fn (array $unitType): bool => $unitType['is_included'],
            );

            if ($includedUnitTypes->isEmpty()) {
                $blockers[] = $this->issue(
                    'unit_type_required',
                    'Include at least one viable Unit Type before publishing the unit offering.',
                    $this->action('Open Unit Types', route('properties.unit-types.index', $property, absolute: false)),
                );
            } elseif ($includedUnitTypes->every(
                fn (array $unitType): bool => ! $unitType['has_active_pricing'],
            )) {
                $blockers[] = $this->issue(
                    'unit_rate_required',
                    'Add active pricing to at least one included Unit Type before publishing the unit offering.',
                    $this->unitRateAction($property, $includedUnitTypes->pluck('id')->all()),
                );
            } else {
                $blockers[] = $this->issue(
                    'unit_inventory_required',
                    'At least one included Unit Type needs eligible inventory before publishing the unit offering.',
                    $this->action('Open Units', route('properties.units.index', $property, absolute: false)),
                );
            }
        }

        return $blockers === []
            ? [$this->issue('viable_offering_required', 'This property does not have a viable public offering yet.', null)]
            : $blockers;
    }

    /**
     * @param  list<array{
     *     id: int,
     *     name: string,
     *     is_active: bool,
     *     is_included: bool,
     *     is_viable_if_included: bool,
     *     physical_units: int,
     *     available_units: int,
     *     has_active_pricing: bool,
     *     starting_price: array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null,
     *     status: 'ready'|'excluded'|'blocked'|'inactive',
     *     reason: string|null,
     *     action: array{label: string, url: string}|null,
     * }>  $unitTypeCards
     * @return list<array{key: string, message: string, action: array{label: string, url: string}|null}>
     */
    private function recommendations(
        Property $property,
        bool $canPublish,
        array $unitTypeCards,
        int $unassignedUnitsCount,
    ): array {
        $recommendations = [];

        if (blank($property->description)) {
            $recommendations[] = $this->issue(
                'description',
                'Add a description to help customers understand this property.',
                $this->listingSectionAction($property, 'Add description', 'listing-description'),
            );
        }

        if ($this->photoCount($property) === 0) {
            $recommendations[] = $this->issue(
                'photos',
                'Add photos to improve the public listing.',
                $this->listingSectionAction($property, 'Open gallery', 'listing-gallery'),
            );
        }

        $hasPublicAmenities = $property->facilities->contains(
            fn (Amenity $amenity): bool => $amenity->is_active
                && in_array($amenity->scope->value, [AmenityScope::Property->value, AmenityScope::Both->value], true),
        );

        if (! $hasPublicAmenities) {
            $recommendations[] = $this->issue(
                'amenities',
                'Add amenities to help customers compare this property.',
                $this->listingSectionAction($property, 'Open amenities', 'listing-amenities'),
            );
        }

        if ($property->rental_mode->supportsUnitInventory() && $unassignedUnitsCount > 0) {
            $recommendations[] = $this->issue(
                'unassigned_units',
                trans_choice(
                    ':count Unit is not assigned to a Unit Type.|:count Units are not assigned to a Unit Type.',
                    $unassignedUnitsCount,
                    ['count' => $unassignedUnitsCount],
                ),
                $this->action('Open Units', route('properties.units.index', $property, absolute: false)),
            );
        }

        $excludedUnitTypes = collect($unitTypeCards)->filter(
            fn (array $unitType): bool => $unitType['is_active']
                && ! $unitType['is_included']
                && $unitType['is_viable_if_included'],
        );

        if ($excludedUnitTypes->isNotEmpty()) {
            $recommendations[] = $this->issue(
                'excluded_unit_types',
                trans_choice(
                    ':count eligible Unit Type is excluded from the listing.|:count eligible Unit Types are excluded from the listing.',
                    $excludedUnitTypes->count(),
                    ['count' => $excludedUnitTypes->count()],
                ),
                $this->action('Review Unit Types', route('properties.unit-types.index', $property, absolute: false)),
            );
        }

        if ($property->rental_mode->supportsWholePropertyRental()
            && ! $property->hasViableWholePropertyOffering()
            && $canPublish
        ) {
            $recommendations[] = $this->issue(
                'property_rate_recommendation',
                'Add a property rate if this property should also be available as a whole-property offering.',
                $this->action('Open Pricing', route('properties.pricing.index', $property, absolute: false)),
            );
        }

        if (blank($property->address) || $property->city === null || $property->region === null) {
            $recommendations[] = $this->issue(
                'property_information',
                'Complete the optional public property information.',
                $this->action('Edit property information', route('properties.index', absolute: false)),
            );
        }

        return $recommendations;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     is_active: bool,
     *     is_included: bool,
     *     is_viable_if_included: bool,
     *     physical_units: int,
     *     available_units: int,
     *     has_active_pricing: bool,
     *     starting_price: array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null,
     *     status: 'ready'|'excluded'|'blocked'|'inactive',
     *     reason: string|null,
     *     action: array{label: string, url: string}|null,
     * }
     */
    private function unitTypePayload(Property $property, UnitType $unitType): array
    {
        $physicalUnits = (int) ($unitType->units_count ?? 0);
        $availableUnits = (int) ($unitType->available_units_count ?? 0);
        $pricedUnits = (int) ($unitType->priced_units_count ?? 0);
        $eligiblePublicUnits = (int) ($unitType->eligible_public_units_count ?? 0);
        $isIncluded = (bool) $unitType->is_published;
        $isViableIfIncluded = $unitType->is_active
            && $pricedUnits > 0
            && $eligiblePublicUnits > 0;
        $isViable = $isIncluded && filled($unitType->public_slug) && $isViableIfIncluded;

        if (! $unitType->is_active) {
            $status = 'inactive';
            $reason = 'This Unit Type is inactive.';
            $reasonLabel = 'Inactive';
            $action = $this->action('Manage Unit Types', route('properties.unit-types.index', $property, absolute: false));
        } elseif ($isViable || (! $isIncluded && $isViableIfIncluded)) {
            $status = $isIncluded ? 'ready' : 'excluded';
            $reason = null;
            $reasonLabel = null;
            $action = null;
        } elseif ($physicalUnits === 0) {
            $status = 'blocked';
            $reason = 'No Units are assigned to this Unit Type.';
            $reasonLabel = 'No inventory';
            $action = $this->action('Open Units', route('properties.units.index', $property, absolute: false));
        } elseif ($pricedUnits === 0) {
            $status = 'blocked';
            $reason = 'No active pricing is configured for these Units.';
            $reasonLabel = 'Pricing missing';
            $action = $this->unitRateAction($property, [$unitType->id]);
        } elseif ($eligiblePublicUnits === 0) {
            $status = 'blocked';
            $reason = 'No eligible Units can support this public offering.';
            $reasonLabel = 'No eligible inventory';
            $action = $this->action('Open Units', route('properties.units.index', $property, absolute: false));
        } else {
            $status = 'blocked';
            $reason = 'Complete this Unit Type public setup before including it.';
            $reasonLabel = 'Needs setup';
            $action = $this->action('Manage Unit Types', route('properties.unit-types.index', $property, absolute: false));
        }

        return [
            'id' => $unitType->id,
            'name' => $unitType->name,
            'is_active' => (bool) $unitType->is_active,
            'is_included' => $isIncluded,
            'is_viable_if_included' => $isViableIfIncluded,
            'physical_units' => $physicalUnits,
            'available_units' => $availableUnits,
            'has_active_pricing' => $pricedUnits > 0,
            'starting_price' => $this->startingPrice($unitType),
            'status' => $status,
            'reason' => $reason,
            'reason_label' => $reasonLabel,
            'action' => $action,
        ];
    }

    /**
     * @return array{
     *     is_listed: bool,
     *     has_active_pricing: bool,
     *     starting_price: array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null,
     *     reason: string|null,
     *     action: array{label: string, url: string}|null,
     * }
     */
    private function wholePropertyPayload(Property $property, bool $isPubliclyVisible): array
    {
        $hasActivePricing = $property->hasViableWholePropertyOffering();

        return [
            'is_listed' => $isPubliclyVisible && $hasActivePricing,
            'has_active_pricing' => $hasActivePricing,
            'starting_price' => $hasActivePricing ? $this->propertyStartingPrice($property) : null,
            'reason' => $hasActivePricing ? null : 'No active property pricing is configured.',
            'action' => $this->action('Manage pricing', route('properties.pricing.index', $property, absolute: false)),
        ];
    }

    /**
     * @return array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null
     */
    private function propertyStartingPrice(Property $property): ?array
    {
        $rate = $property->defaultActivePropertyRate();

        if (! $rate instanceof PropertyRate) {
            return null;
        }

        return [
            'amount' => (string) $rate->amount,
            'currency' => $rate->currency,
            'billing_interval' => $rate->billing_interval,
            'billing_unit' => $rate->billing_unit->value,
            'billing_label' => $this->billingLabel($rate->billing_interval, $rate->billing_unit),
        ];
    }

    /**
     * @param  list<int>  $unitTypeIds
     * @return array{label: string, url: string}
     */
    private function unitRateAction(Property $property, array $unitTypeIds): array
    {
        $unitTypeId = collect($unitTypeIds)->first();
        $unitType = $property->unitTypes->firstWhere('id', $unitTypeId);
        $unit = $unitType?->units->first(fn (Unit $unit): bool => $unit->activeRates->isEmpty())
            ?? $unitType?->units->first();

        if ($unit !== null) {
            return $this->action(
                'Manage pricing',
                route('properties.units.rates', [$property, $unit], absolute: false),
            );
        }

        return $this->action('Open Units', route('properties.units.index', $property, absolute: false));
    }

    /**
     * @return array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}|null
     */
    private function startingPrice(UnitType $unitType): ?array
    {
        $prices = [];

        foreach ($unitType->units as $unit) {
            foreach ($unit->activeRates as $rate) {
                /** @var UnitRate $rate */
                $billingUnit = $rate->billing_unit->value;
                $key = implode('|', [$rate->currency, $rate->billing_interval, $billingUnit]);
                $amount = (string) $rate->amount;

                if (! isset($prices[$key]) || $this->money->compare($amount, $prices[$key]['amount']) < 0) {
                    $prices[$key] = [
                        'amount' => $amount,
                        'currency' => $rate->currency,
                        'billing_interval' => $rate->billing_interval,
                        'billing_unit' => $billingUnit,
                        'billing_label' => $this->billingLabel($rate->billing_interval, $rate->billing_unit),
                    ];
                }
            }
        }

        $prices = array_values($prices);
        usort($prices, function (array $left, array $right): int {
            $currency = strcmp($left['currency'], $right['currency']);

            if ($currency !== 0) {
                return $currency;
            }

            $billingOrder = ['day' => 1, 'week' => 2, 'month' => 3, 'year' => 4];

            return [
                $billingOrder[$left['billing_unit']],
                $left['billing_interval'],
            ] <=> [
                $billingOrder[$right['billing_unit']],
                $right['billing_interval'],
            ];
        });

        return $prices[0] ?? null;
    }

    private function billingLabel(int $interval, BillingUnit $unit): string
    {
        return match ($unit) {
            BillingUnit::Day => $interval === 1 ? '/day' : "/ {$interval} days",
            BillingUnit::Week => $interval === 1 ? '/week' : "/ {$interval} weeks",
            BillingUnit::Month => $interval === 1 ? '/month' : "/ {$interval} months",
            BillingUnit::Year => $interval === 1 ? '/year' : "/ {$interval} years",
        };
    }

    private function photoCount(Property $property): int
    {
        if ($property->relationLoaded('media')) {
            return $property->media->count();
        }

        return (int) $property->media()->where('collection', 'photos')->count();
    }

    private function unassignedUnitsCount(Property $property): int
    {
        $count = $property->getAttribute('unassigned_units_count');

        return $count === null
            ? (int) $property->units()->whereNull('unit_type_id')->count()
            : (int) $count;
    }

    /**
     * @return array{key: string, message: string, action: array{label: string, url: string}|null}
     */
    private function issue(string $key, string $message, ?array $action): array
    {
        return [
            'key' => $key,
            'message' => __($message),
            'action' => $action,
        ];
    }

    /**
     * @return array{label: string, url: string}
     */
    private function action(string $label, string $url): array
    {
        return [
            'label' => __($label),
            'url' => $url,
        ];
    }

    /**
     * @return array{label: string, url: string}
     */
    private function listingSectionAction(Property $property, string $label, string $section): array
    {
        return $this->action(
            $label,
            route('properties.listing', ['property' => $property], absolute: false).'#'.$section,
        );
    }
}
