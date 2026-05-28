<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\City;
use App\Models\Property;
use App\Models\Region;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'city', 'rooms_count', 'occupied_rooms_count', 'tenants_count'];
        $perPageOptions = [10, 15, 25, 50];

        if (! in_array($sort, $sortable)) {
            $sort = 'name';
        }

        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        if (! in_array($perPage, $perPageOptions)) {
            $perPage = 15;
        }

        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->withCount([
                'rooms',
                'rooms as occupied_rooms_count' => fn (Builder $q) => $q->where('status', RoomStatus::Occupied),
                'leases as tenants_count' => fn (Builder $q) => $q->selectRaw('count(distinct tenant_id)'),
            ])
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where(DB::raw('lower(properties.name)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhereHas('region', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'))
                    ->orWhereHas('city', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'));
            }))
            ->when($status === 'active', fn (Builder $q) => $q->whereRaw('is_active is true'))
            ->when($status === 'archived', fn (Builder $q) => $q->whereRaw('is_active is false'))
            ->when($sort === 'city', fn (Builder $q) => $q->orderBy(
                City::select('name')->whereColumn('cities.id', 'properties.city_id'),
                $direction,
            ))
            ->when($sort !== 'city', fn (Builder $q) => $q->orderBy($sort, $direction))
            ->paginate($perPage);

        $countryCode = Setting::get()->country_code;
        $regions = Region::where('country_code', $countryCode)
            ->with('cities')
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/index', [
            'properties' => $properties,
            'regions' => $regions,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $property = Property::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property created.')]);

        return to_route('properties.index');
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $property->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property updated.')]);

        return to_route('properties.index');
    }

    public function destroy(Property $property): RedirectResponse
    {
        Property::query()->whereKey($property->id)->update(['is_active' => DB::raw('false')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property archived.')]);

        return to_route('properties.index');
    }
}
