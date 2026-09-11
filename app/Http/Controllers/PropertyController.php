<?php

namespace App\Http\Controllers;

use App\Actions\Properties\CreateProperty;
use App\Enums\AmenityScope;
use App\Enums\LeaseStatus;
use App\Http\Requests\Listing\UpdateListingPublicationRequest;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Lease;
use App\Models\Media;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Region;
use App\Models\Setting;
use App\Services\Listings\PublicSlugAllocator;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function show(Property $property): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()
            ->findOrFail($property->id);

        return Inertia::render('properties/overview', [
            'property' => $property,
        ]);
    }

    public function listing(Property $property): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()
            ->with([
                'facilities',
                'media' => fn ($query) => $query->where('collection', 'photos')->orderBy('position')->orderBy('id'),
            ])
            ->findOrFail($property->id);

        $property->setAttribute('gallery', $property->media->map(fn (Media $media): array => [
            'id' => $media->id,
            'url' => route('properties.gallery.show', [$property, $media]),
            'position' => $media->position,
            'alt' => $media->metadata['alt'] ?? null,
            'caption' => $media->metadata['caption'] ?? null,
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
        ])->values()->all());
        $property->unsetRelation('media');

        $facilityIds = $property->facilities->modelKeys();
        $amenities = Amenity::query()
            ->where(function (Builder $query) use ($property, $facilityIds): void {
                $query->where(function (Builder $query) use ($property): void {
                    $query->where(function (Builder $query): void {
                        $query->whereIn('scope', [AmenityScope::Property->value, AmenityScope::Both->value])
                            ->whereNull('owner_property_id')
                            ->where('is_active', true);
                    })->orWhere(function (Builder $query) use ($property): void {
                        $query->where('owner_property_id', $property->id);
                    });
                })->orWhereIn('id', $facilityIds);
            })
            ->orderBy('name')
            ->get(['id', 'owner_property_id', 'name', 'scope', 'is_active']);

        return Inertia::render('properties/listing', [
            'property' => $property,
            'amenities' => $amenities,
        ]);
    }

    public function index(Request $request): Response
    {
        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(
                    fn (Builder $q, string $search) => $q->listSearch($search),
                ),
                Column::make('type', 'Type')->sortable(),
                Column::make('city', 'City')->sortable(
                    fn (Builder $q, string $dir) => $q->orderBy(
                        City::select('name')->whereColumn('cities.id', 'properties.city_id'),
                        $dir,
                    ),
                ),
                Column::make('units_count', 'Total Units')->sortable(),
                Column::make('occupied_units_count', 'Occupied')->sortable(),
                Column::make('tenants_count', 'Tenants')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'archived'])
                    ->query(fn (Builder $q, string $value) => $q->statusFilter($value)),
                Filter::select('type', 'Type', PropertyType::ordered()->pluck('slug')->all())
                    ->query(fn (Builder $q, string $value) => $q->where('type', $value)),
            ])
            ->defaultSort('name');

        $query = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->with(['city', 'region', 'propertyType'])
            ->withCount('units')
            ->withOccupiedUnitsCount()
            ->withTenantsCount();

        $result = $table->paginate($query, $request, 'properties');

        $countryCode = Setting::get('country_code');
        $regions = Region::where('country_code', $countryCode)
            ->with('cities')
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/index', [
            ...$result,
            'regions' => $regions,
            'propertyTypes' => PropertyType::active()->ordered()->get(['slug', 'label']),
        ]);
    }

    public function store(StorePropertyRequest $request, CreateProperty $createProperty): RedirectResponse
    {
        DB::transaction(fn (): Property => $createProperty->execute($request->user(), $request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property created.')]);

        return back();
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $property->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property updated.')]);

        return back();
    }

    public function updatePublication(
        UpdateListingPublicationRequest $request,
        Property $property,
        PublicSlugAllocator $slugAllocator,
    ): RedirectResponse {
        $this->authorize('update', $property);
        $isPublished = $request->boolean('is_published');

        $slugAllocator->transaction(function () use ($property, $isPublished, $slugAllocator): void {
            $lockedProperty = Property::withTrashed()->lockForUpdate()->findOrFail($property->id);

            abort_if($isPublished && ! $lockedProperty->is_active, 422, __('Inactive properties cannot be published.'));

            $attributes = ['is_published' => $isPublished];
            if ($isPublished && empty($lockedProperty->public_slug)) {
                $attributes['public_slug'] = $slugAllocator->allocate(
                    Property::withTrashed(),
                    $lockedProperty->name,
                    'property',
                );
            }

            $lockedProperty->forceFill($attributes)->saveOrFail();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($isPublished ? 'Property published.' : 'Property unpublished.'),
        ]);

        return back();
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        if (Lease::whereHas('unit', fn ($q) => $q->withTrashed()->where('property_id', $property->id))
            ->where('status', LeaseStatus::Active)
            ->exists()
        ) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot archive a property with active leases.')]);

            return back();
        }

        Property::query()->whereKey($property->id)->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property archived.')]);

        return to_route('properties.index');
    }

    public function restore(Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        Property::query()->whereKey($property->id)->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property restored.')]);

        return back();
    }
}
