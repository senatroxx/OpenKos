<?php

namespace App\Http\Controllers;

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Enums\BillingUnit;
use App\Enums\PropertyRentalMode;
use App\Models\Amenity;
use App\Models\Media;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\Payments\MoneyConverter;
use App\Services\Pricing\EffectiveUnitRateResolver;
use App\Services\PublicPortal\PublicPortalMetadataResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class PublicListingController extends Controller
{
    public function __construct(
        private MoneyConverter $money,
        private EffectiveUnitRateResolver $effectiveUnitRateResolver,
        private PublicPortalMetadataResolver $metadata,
    ) {}

    public function pageIndex(): Response
    {
        $listings = $this->indexData();

        return Inertia::render('public/listings/index', [
            'listings' => $listings,
            'metadata' => $this->metadata->homepage(
                route('public.portal.index', absolute: false),
                $listings[0]['gallery'][0]['url'] ?? null,
            )->toArray(),
        ]);
    }

    public function pageShow(Property $property): Response
    {
        $listing = $this->propertyData($property);

        return Inertia::render('public/listings/show', [
            'listing' => $listing,
            'metadata' => $this->metadata->property(
                $listing['name'],
                $listing['description'],
                route('public.portal.show', [
                    'property' => $property->public_slug,
                ], absolute: false),
                $listing['gallery'][0]['url'] ?? null,
            )->toArray(),
        ]);
    }

    public function pageUnitType(Property $property, UnitType $unitType): Response
    {
        $listing = $this->unitTypeData($property, $unitType);

        return Inertia::render('public/listings/unit-type', [
            'listing' => $listing,
            'metadata' => $this->metadata->unitType(
                $listing['unit_type']['name'],
                $listing['property']['name'],
                $listing['unit_type']['description'],
                route('public.portal.unit-types.show', [
                    'property' => $property->public_slug,
                    'unitType' => $unitType->public_slug,
                ], absolute: false),
                $listing['unit_type']['gallery'][0]['url'] ?? null,
            )->toArray(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexData(): array
    {
        $properties = $this->publicProperties()->get();
        $propertiesWithUnitInventory = $properties->filter(
            fn (Property $property): bool => $property->rental_mode->supportsUnitInventory(),
        );
        $propertiesWithPropertyRates = $properties->filter(
            fn (Property $property): bool => $property->rental_mode->supportsWholePropertyRental(),
        );

        if ($propertiesWithUnitInventory->isNotEmpty()) {
            $propertiesWithUnitInventory->load([
                'unitTypes' => fn ($query) => $query
                    ->viablePublicOffering()
                    ->orderBy('name')
                    ->with($this->unitTypeRelations()),
            ]);
        }

        if ($propertiesWithPropertyRates->isNotEmpty()) {
            $propertiesWithPropertyRates->load('activePropertyRates');
        }

        $availableCounts = $this->availableUnitCounts($this->unitTypeIds($properties));

        return $properties->map(
            fn (Property $property): array => $this->propertyPayload($property, $availableCounts),
        )->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function propertyData(Property $property): array
    {
        abort_unless($this->isPublicProperty($property), 404);

        $property->load($this->publicRelations($property->rental_mode->supportsUnitInventory()));
        if ($property->rental_mode->supportsWholePropertyRental()) {
            $property->load('activePropertyRates');
        }

        $availableCounts = $property->rental_mode->supportsUnitInventory()
            ? $this->availableUnitCounts($property->unitTypes->modelKeys())
            : [];

        return $this->propertyPayload($property, $availableCounts);
    }

    /**
     * @return array{property: array{slug: string, name: string}, unit_type: array<string, mixed>}
     */
    private function unitTypeData(Property $property, UnitType $unitType): array
    {
        abort_unless($this->isPublicProperty($property), 404);
        abort_unless($property->rental_mode->supportsUnitInventory(), 404);
        abort_unless(
            $unitType->property_id === $property->id
            && $unitType->isViablePublicOffering(),
            404,
        );

        $unitType->load($this->unitTypeRelations());
        $availableCounts = $this->availableUnitCounts([$unitType->id]);

        return [
            'property' => [
                'slug' => $property->public_slug,
                'name' => $property->name,
                'rental_mode' => $property->rental_mode->value,
            ],
            'unit_type' => $this->unitTypePayload($unitType, $availableCounts),
        ];
    }

    private function publicProperties(): Builder
    {
        return Property::query()
            ->publiclyVisible()
            ->with($this->publicRelations(includeUnitInventory: false))
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicRelations(bool $includeUnitInventory = true): array
    {
        $relations = [
            'city:id,name',
            'region:id,name',
            'propertyType:id,slug,label',
            'facilities' => fn ($query) => $query
                ->where('amenities.is_active', true)
                ->whereIn('amenities.scope', [AmenityScope::Property->value, AmenityScope::Both->value])
                ->orderBy('amenities.name'),
            'media' => fn ($query) => $query
                ->where('collection', 'photos')
                ->orderBy('position')
                ->orderBy('id'),
        ];

        if ($includeUnitInventory) {
            $relations['unitTypes'] = fn ($query) => $query
                ->viablePublicOffering()
                ->orderBy('name')
                ->with($this->unitTypeRelations());
        }

        return $relations;
    }

    /**
     * @return array<string, mixed>
     */
    private function unitTypeRelations(): array
    {
        return [
            'amenities' => fn ($query) => $query
                ->where('amenities.is_active', true)
                ->whereIn('amenities.scope', [AmenityScope::UnitType->value, AmenityScope::Both->value])
                ->orderBy('amenities.name'),
            'media' => fn ($query) => $query
                ->where('collection', 'photos')
                ->orderBy('position')
                ->orderBy('id'),
            'units' => fn ($query) => $query
                ->select(['units.id', 'units.unit_type_id'])
                ->with(['activeRates', 'unitType.activeRates']),
        ];
    }

    /**
     * An available unit is a physical unit that can currently accept an
     * assignment, using the existing capacity-aware multi-occupancy rules.
     *
     * @param  array<int, int>  $unitTypeIds
     * @return array<int, int>
     */
    private function availableUnitCounts(array $unitTypeIds): array
    {
        if ($unitTypeIds === []) {
            return [];
        }

        return Unit::query()
            ->availableForAssignment()
            ->whereIn('unit_type_id', $unitTypeIds)
            ->select('unit_type_id')
            ->selectRaw('COUNT(*) AS available_units_count')
            ->groupBy('unit_type_id')
            ->pluck('available_units_count', 'unit_type_id')
            ->mapWithKeys(fn (mixed $count, mixed $unitTypeId): array => [
                (int) $unitTypeId => (int) $count,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Property>  $properties
     * @return array<int, int>
     */
    private function unitTypeIds(Collection $properties): array
    {
        return $properties
            ->filter(fn (Property $property): bool => $property->rental_mode->supportsUnitInventory())
            ->flatMap(fn (Property $property) => $property->unitTypes->modelKeys())
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $availableCounts
     * @return array<string, mixed>
     */
    private function propertyPayload(Property $property, array $availableCounts): array
    {
        $payload = [
            'slug' => $property->public_slug,
            'name' => $property->name,
            'type' => $property->type,
            'type_label' => $property->type_label,
            'rental_mode' => $property->rental_mode->value,
            'location' => [
                'address' => $property->address,
                'postal_code' => $property->postal_code,
                'city' => $property->city?->name,
                'region' => $property->region?->name,
            ],
            'description' => $property->description,
            'amenities' => $property->facilities
                ->map(fn (Amenity $amenity): array => $this->amenityPayload($amenity))
                ->values()
                ->all(),
            'gallery' => $this->gallery($property->media),
        ];

        if ($property->rental_mode === PropertyRentalMode::WholeProperty) {
            $payload['whole_property_offering'] = $this->wholePropertyOfferingPayload($property);

            return $payload;
        }

        $unitTypes = $property->unitTypes->map(
            fn (UnitType $unitType): array => $this->unitTypePayload($unitType, $availableCounts),
        )->values()->all();

        if ($property->rental_mode === PropertyRentalMode::Unit || $unitTypes !== []) {
            $payload['inventory'] = [
                'total_units' => array_sum(array_map(
                    fn (array $unitType): int => $unitType['inventory']['total_units'],
                    $unitTypes,
                )),
                'available_units' => array_sum(array_map(
                    fn (array $unitType): int => $unitType['inventory']['available_units'],
                    $unitTypes,
                )),
            ];
            $payload['unit_types'] = $unitTypes;
        }

        if ($property->rental_mode === PropertyRentalMode::Hybrid) {
            $wholePropertyOffering = $this->wholePropertyOfferingPayload($property);

            if ($wholePropertyOffering !== null) {
                $payload['whole_property_offering'] = $wholePropertyOffering;
            }
        }

        return $payload;
    }

    /**
     * @return array{type: string, availability: string, starting_price: array<string, mixed>, rates: array<int, array<string, mixed>>}|null
     */
    private function wholePropertyOfferingPayload(Property $property): ?array
    {
        $rates = $property->relationLoaded('activePropertyRates')
            ? $property->activePropertyRates
            : $property->activePropertyRates()->get();

        if ($rates->isEmpty()) {
            return null;
        }

        $startingPrice = $property->defaultActivePropertyRate();

        if ($startingPrice === null) {
            return null;
        }

        return [
            'type' => PropertyRentalMode::WholeProperty->value,
            'availability' => $property->rental_mode === PropertyRentalMode::Hybrid
                ? ($property->activeLeases()->exists() ? 'unavailable' : 'available_for_inquiry')
                : ($property->activeWholePropertyLeases()->exists() ? 'unavailable' : 'available_for_inquiry'),
            'starting_price' => $this->propertyRatePayload($startingPrice),
            'rates' => $rates
                ->map(fn (PropertyRate $rate): array => $this->propertyRatePayload($rate))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}
     */
    private function propertyRatePayload(PropertyRate $rate): array
    {
        return [
            'amount' => (string) $rate->amount,
            'currency' => $rate->currency,
            'billing_interval' => $rate->billing_interval,
            'billing_unit' => $rate->billing_unit->value,
            'billing_label' => $this->billingLabel($rate->billing_interval, $rate->billing_unit),
        ];
    }

    /**
     * @param  array<int, int>  $availableCounts
     * @return array<string, mixed>
     */
    private function unitTypePayload(UnitType $unitType, array $availableCounts): array
    {
        return [
            'slug' => $unitType->public_slug,
            'name' => $unitType->name,
            'description' => $unitType->description,
            'bedrooms' => $unitType->bedrooms,
            'bathrooms' => $unitType->bathrooms,
            'size_sqm' => $unitType->size_sqm,
            'furnishing' => $unitType->furnishing,
            'amenities' => $unitType->amenities
                ->map(fn (Amenity $amenity): array => $this->amenityPayload($amenity))
                ->values()
                ->all(),
            'gallery' => $this->gallery($unitType->media),
            'inventory' => [
                'total_units' => $unitType->units->count(),
                'available_units' => $availableCounts[$unitType->id] ?? 0,
            ],
            'starting_prices' => $this->startingPrices($unitType),
        ];
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, array{url: string, position: int, alt: string|null, caption: string|null, mime_type: string}>
     */
    private function gallery(Collection $media): array
    {
        return $media->map(fn (Media $item): array => [
            'url' => route('public.portal.media', $item),
            'position' => $item->position,
            'alt' => $item->metadata['alt'] ?? null,
            'caption' => $item->metadata['caption'] ?? null,
            'mime_type' => $item->mime_type,
        ])->values()->all();
    }

    /**
     * @return array{name: string, icon: string|null}
     */
    private function amenityPayload(Amenity $amenity): array
    {
        return [
            'name' => $amenity->name,
            'icon' => AmenityIcon::tryFrom((string) $amenity->icon)?->value,
        ];
    }

    /**
     * @return array<int, array{amount: string, currency: string, billing_interval: int, billing_unit: string, billing_label: string}>
     */
    private function startingPrices(UnitType $unitType): array
    {
        $prices = [];

        foreach ($unitType->units as $unit) {
            foreach ($this->effectiveUnitRateResolver->resolve($unit) as $item) {
                $rate = $item['rate'];
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

        return $prices;
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

    private function isPublicProperty(Property $property): bool
    {
        return $property->isPubliclyVisible();
    }
}
