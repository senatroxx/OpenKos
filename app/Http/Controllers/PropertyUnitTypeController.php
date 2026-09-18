<?php

namespace App\Http\Controllers;

use App\Enums\AmenityScope;
use App\Http\Requests\Listing\UpdateListingPublicationRequest;
use App\Http\Requests\UnitType\StoreUnitTypeRequest;
use App\Http\Requests\UnitType\UpdateUnitTypeRequest;
use App\Http\Requests\UnitType\UpdateUnitTypeStatusRequest;
use App\Models\Amenity;
use App\Models\Media;
use App\Models\Property;
use App\Models\UnitType;
use App\Services\Listings\ListingReadinessService;
use App\Services\Listings\PublicSlugAllocator;
use App\Support\DelimitedValues;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyUnitTypeController extends Controller
{
    public function show(Property $property, UnitType $unitType, ListingReadinessService $readinessService): Response
    {
        $this->authorize('view', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $property = $property->load(['city', 'region', 'propertyType']);
        $unitType->load([
            'amenities',
            'rates',
            'activeRates',
            'media' => fn ($query) => $query->where('collection', 'photos')->orderBy('position')->orderBy('id'),
        ])->loadCount([
            'units',
            'units as available_units_count' => fn (Builder $query) => $query->availableForAssignment(),
        ]);
        $unitType->setAttribute('gallery', $this->gallery($unitType, $property));
        $unitType->unsetRelation('media');

        $readiness = $readinessService->analyze($property);
        $listing = collect($readiness['unit_types'])->firstWhere('id', $unitType->id);

        return Inertia::render('properties/unit-types/show', [
            'property' => $property,
            'unitType' => $unitType,
            'listing' => $listing,
        ]);
    }

    public function listing(Property $property, UnitType $unitType, ListingReadinessService $readinessService): Response
    {
        $this->authorize('view', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $property = $property->load(['city', 'region', 'propertyType']);
        $unitType->load(['amenities', 'media' => fn ($query) => $query->where('collection', 'photos')->orderBy('position')->orderBy('id')]);
        $unitType->setAttribute('gallery', $this->gallery($unitType, $property));
        $unitType->unsetRelation('media');
        $readiness = $readinessService->analyze($property);

        return Inertia::render('properties/unit-types/listing', [
            'property' => $property,
            'unitType' => $unitType,
            'listing' => collect($readiness['unit_types'])->firstWhere('id', $unitType->id),
        ]);
    }

    public function index(Request $request, Property $property, ListingReadinessService $readinessService): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);
        $rentalOptions = $readinessService->analyze($property)['unit_types'];
        $property->unsetRelation('unitTypes');
        $property->unsetRelation('activePropertyRates');
        $property->unsetRelation('facilities');
        $statusValues = DelimitedValues::normalize($request->query('status'));
        $includesDeleted = in_array('deleted', $statusValues, true);
        $table = Table::make()
            ->columns([
                Column::make('name', 'Unit Type')->sortable()->searchable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'inactive', 'deleted'])
                    ->query(function (Builder $query, string $value): void {
                        if ($value === 'deleted') {
                            $query->whereNotNull('unit_types.deleted_at');
                        } elseif ($value === 'inactive') {
                            $query->whereNull('unit_types.deleted_at')->where('unit_types.is_active', false);
                        } else {
                            $query->whereNull('unit_types.deleted_at')->where('unit_types.is_active', true);
                        }
                    }),
            ])
            ->defaultSort('name');
        $query = $property->unitTypes()
            ->when($includesDeleted, fn (Builder $query) => $query->withTrashed())
            ->withCount('units')
            ->with(['amenities', 'media' => fn ($query) => $query->where('collection', 'photos')->orderBy('position')->orderBy('id')]);
        $result = $table->paginate($query, $request, 'unitTypes');
        $unitTypes = $result['unitTypes'];

        $unitTypes->getCollection()->each(function (UnitType $unitType) use ($property): void {
            $unitType->setAttribute('gallery', $this->gallery($unitType, $property));
            $unitType->unsetRelation('media');
        });

        $unitTypeAmenityIds = $unitTypes
            ->flatMap(fn (UnitType $unitType) => $unitType->amenities->modelKeys())
            ->unique()
            ->values()
            ->all();
        $amenities = Amenity::query()
            ->where(function ($query) use ($unitTypeAmenityIds): void {
                $query->whereIn('scope', [AmenityScope::UnitType->value, AmenityScope::Both->value])
                    ->where('is_active', true)
                    ->when($unitTypeAmenityIds !== [], function ($query) use ($unitTypeAmenityIds): void {
                        $query->orWhereIn('id', $unitTypeAmenityIds);
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'scope', 'is_active']);

        return Inertia::render('properties/unit-types/index', [
            ...$result,
            'property' => $property,
            'amenities' => $amenities,
            'rentalOptions' => $rentalOptions,
        ]);
    }

    public function store(StoreUnitTypeRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('create', [UnitType::class, $property]);

        $validated = $request->validated();
        $amenityIds = $validated['amenity_ids'] ?? [];
        unset($validated['amenity_ids']);

        DB::transaction(function () use ($property, $validated, $amenityIds): void {
            $unitType = $property->unitTypes()->create($validated);
            $unitType->amenities()->sync($amenityIds);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit Type created.')]);

        return back();
    }

    public function update(UpdateUnitTypeRequest $request, Property $property, UnitType $unitType): RedirectResponse
    {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $validated = $request->validated();
        $hasAmenities = array_key_exists('amenity_ids', $validated);
        $amenityIds = $validated['amenity_ids'] ?? [];
        unset($validated['amenity_ids']);

        DB::transaction(function () use ($unitType, $validated, $hasAmenities, $amenityIds): void {
            $unitType->update($validated);

            if ($hasAmenities) {
                $unitType->amenities()->sync($amenityIds);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit Type updated.')]);

        return back();
    }

    public function updatePublication(
        UpdateListingPublicationRequest $request,
        Property $property,
        UnitType $unitType,
        PublicSlugAllocator $slugAllocator,
    ): RedirectResponse {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);
        $isPublished = $request->boolean('is_published');

        abort_if($isPublished && ! $property->rental_mode->supportsUnitInventory(), 422, __('Unit Types cannot be published for a Whole property listing.'));

        $slugAllocator->transaction(function () use ($unitType, $isPublished, $slugAllocator): void {
            $lockedUnitType = UnitType::query()->lockForUpdate()->findOrFail($unitType->id);

            abort_if($isPublished && ! $lockedUnitType->is_active, 422, __('Inactive Unit Types cannot be published.'));

            $attributes = ['is_published' => $isPublished];
            if ($isPublished && empty($lockedUnitType->public_slug)) {
                $attributes['public_slug'] = $slugAllocator->allocate(
                    UnitType::query()->where('property_id', $lockedUnitType->property_id),
                    $lockedUnitType->name,
                    'unit-type',
                );
            }

            $lockedUnitType->forceFill($attributes)->saveOrFail();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($isPublished ? 'Unit Type published.' : 'Unit Type unpublished.'),
        ]);

        return back();
    }

    public function updateStatus(
        UpdateUnitTypeStatusRequest $request,
        Property $property,
        UnitType $unitType,
    ): RedirectResponse {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $unitType->update(['is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($unitType->is_active ? 'Unit Type activated.' : 'Unit Type deactivated.'),
        ]);

        return back();
    }

    public function destroy(Property $property, UnitType $unitType): RedirectResponse
    {
        $this->authorize('delete', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $unitType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit Type deleted.')]);

        return to_route('properties.unit-types.index', $property);
    }

    public function restore(Property $property, UnitType $unitType): RedirectResponse
    {
        $this->authorize('restore', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $unitType->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit Type restored.')]);

        return back();
    }

    /**
     * @return array<int, array{id: int, url: string, position: int, alt: string|null, caption: string|null, original_name: string, mime_type: string}>
     */
    private function gallery(UnitType $unitType, Property $property): array
    {
        /** @var Collection<int, Media> $media */
        $media = $unitType->getRelation('media');

        return $media->map(function (Media $item) use ($property, $unitType): array {
            return [
                'id' => $item->id,
                'url' => route('properties.unit-types.gallery.show', [$property, $unitType, $item]),
                'position' => $item->position,
                'alt' => $item->metadata['alt'] ?? null,
                'caption' => $item->metadata['caption'] ?? null,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
            ];
        })->values()->all();
    }
}
