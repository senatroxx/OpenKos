<?php

namespace App\Http\Controllers;

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Enums\BillingUnit;
use App\Enums\PropertyRentalMode;
use App\Models\Amenity;
use App\Models\Media;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PublicListingController extends Controller
{
    public function __construct(private MoneyConverter $money) {}

    public function index(): JsonResponse
    {
        return $this->json([
            'data' => $this->indexData(),
        ]);
    }

    public function pageIndex(): Response
    {
        return Inertia::render('public/listings/index', [
            'listings' => $this->indexData(),
            'canonicalUrl' => route('public.portal.index', absolute: false),
        ]);
    }

    public function show(Property $property): JsonResponse
    {
        return $this->json([
            'data' => $this->propertyData($property),
        ]);
    }

    public function pageShow(Property $property): Response
    {
        return Inertia::render('public/listings/show', [
            'listing' => $this->propertyData($property),
            'canonicalUrl' => route('public.portal.show', [
                'property' => $property->public_slug,
            ], absolute: false),
        ]);
    }

    public function unitType(Property $property, UnitType $unitType): JsonResponse
    {
        return $this->json([
            'data' => $this->unitTypeData($property, $unitType),
        ]);
    }

    public function pageUnitType(Property $property, UnitType $unitType): Response
    {
        return Inertia::render('public/listings/unit-type', [
            'listing' => $this->unitTypeData($property, $unitType),
            'canonicalUrl' => route('public.portal.unit-types.show', [
                'property' => $property->public_slug,
                'unitType' => $unitType->public_slug,
            ], absolute: false),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexData(): array
    {
        $properties = $this->publicProperties()->get();
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

        $property->load($this->publicRelations());
        $availableCounts = $this->availableUnitCounts($property->unitTypes->modelKeys());

        return $this->propertyPayload($property, $availableCounts);
    }

    /**
     * @return array{property: array{slug: string, name: string}, unit_type: array<string, mixed>}
     */
    private function unitTypeData(Property $property, UnitType $unitType): array
    {
        abort_unless($this->isPublicProperty($property), 404);
        abort_unless(
            $unitType->property_id === $property->id
                && $unitType->is_active
                && $unitType->is_published
                && filled($unitType->public_slug),
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
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('rental_mode', '!=', PropertyRentalMode::WholeProperty->value)
            ->whereNotNull('public_slug')
            ->with($this->publicRelations())
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicRelations(): array
    {
        return [
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
            'unitTypes' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_published', true)
                ->whereNotNull('public_slug')
                ->orderBy('name')
                ->with($this->unitTypeRelations()),
        ];
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
                ->with('activeRates'),
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
        $unitTypes = $property->unitTypes->map(
            fn (UnitType $unitType): array => $this->unitTypePayload($unitType, $availableCounts),
        )->values()->all();

        return [
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
            'inventory' => [
                'total_units' => array_sum(array_map(
                    fn (array $unitType): int => $unitType['inventory']['total_units'],
                    $unitTypes,
                )),
                'available_units' => array_sum(array_map(
                    fn (array $unitType): int => $unitType['inventory']['available_units'],
                    $unitTypes,
                )),
            ],
            'unit_types' => $unitTypes,
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
            'url' => route('public.listings.media', $item),
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
            foreach ($unit->activeRates as $rate) {
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
        return ! $property->trashed()
            && $property->is_active
            && $property->is_published
            && $property->rental_mode !== PropertyRentalMode::WholeProperty
            && filled($property->public_slug);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'no-store');
    }
}
