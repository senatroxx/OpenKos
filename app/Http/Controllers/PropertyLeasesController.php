<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyLeasesController extends Controller
{
    public function __invoke(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->sortable()->searchable(function (Builder $q, string $search): void {
                    $s = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('lower(leases.reference)'), 'like', $s)
                        ->orWhereHas('tenants', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', $s))
                        ->orWhereHas('room', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', $s));
                }),
                Column::make('start_date', 'Start')->sortable(),
                Column::make('end_date', 'End')->sortable(),
                Column::make('rent_amount', 'Rent')->sortable(),
                // leases joins rooms (hasManyThrough) and both have a status column
                Column::make('status', 'Status')->sortable(fn (Builder $q, string $direction) => $q->orderBy('leases.status', $direction)),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'terminated'])
                    ->query(fn (Builder $q, string $value) => $q->where('leases.status', $value)),
            ])
            ->defaultSort('-start_date');

        $result = $table->paginate(
            $property->leases()->with(['room:id,name,property_id', 'tenants:id,name,phone', 'primaryTenant:id,name,phone']),
            $request,
            'leases',
        );

        return Inertia::render('properties/leases', [
            ...$result,
            'property' => Property::withWorkspaceStats()->findOrFail($property->id),
        ]);
    }
}
