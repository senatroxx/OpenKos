<?php

namespace App\Http\Controllers;

use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Models\City;
use App\Models\Property;
use App\Models\Region;
use App\Models\Setting;
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
    public function index(Request $request): Response
    {
        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(),
                Column::make('city', 'City')->sortable(
                    fn (Builder $q, string $dir) => $q->orderBy(
                        City::select('name')->whereColumn('cities.id', 'properties.city_id'),
                        $dir,
                    ),
                )->searchable(function (Builder $q, string $search): void {
                    $q->orWhereHas('region', fn (Builder $q) => $q->where(
                        DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%',
                    ));
                    $q->orWhereHas('city', fn (Builder $q) => $q->where(
                        DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%',
                    ));
                }),
                Column::make('rooms_count', 'Total Rooms')->sortable(),
                Column::make('occupied_rooms_count', 'Occupied')->sortable(),
                Column::make('tenants_count', 'Tenants')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'archived'])
                    ->query(fn (Builder $q, string $value) => match ($value) {
                        'active' => $q->whereRaw('is_active is true'),
                        'archived' => $q->whereRaw('is_active is false'),
                        default => $q,
                    }),
            ])
            ->defaultSort('name');

        $query = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->withCount('rooms')
            ->withOccupiedRoomsCount()
            ->withTenantsCount();

        $result = $table->paginate($query, $request, 'properties');

        $countryCode = Setting::get()->country_code;
        $regions = Region::where('country_code', $countryCode)
            ->with('cities')
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/index', [
            ...$result,
            'regions' => $regions,
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
        $this->authorize('update', $property);

        $property->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property updated.')]);

        return to_route('properties.index');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        Property::query()->whereKey($property->id)->update(['is_active' => DB::raw('false')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property archived.')]);

        return to_route('properties.index');
    }
}
