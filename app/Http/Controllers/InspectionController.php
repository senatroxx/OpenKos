<?php

namespace App\Http\Controllers;

use App\Actions\Inspections\CompleteInspection;
use App\Actions\Inspections\CreateInspection;
use App\Actions\Inspections\UpdateInspection;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Http\Requests\Inspection\StoreInspectionRequest;
use App\Http\Requests\Inspection\UpdateInspectionRequest;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Lease;
use App\Models\Media;
use App\Models\Property;
use App\Models\Unit;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InspectionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inspection::class);

        $query = Inspection::query()
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'property.users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ));

        return $this->history($request, $query, 'inspections/index', [], true);
    }

    public function propertyIndex(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);

        return $this->history($request, $property->inspections(), 'properties/inspections', [
            'property' => $property,
        ]);
    }

    public function unitIndex(Request $request, Property $property, Unit $unit): Response
    {
        $this->authorize('view', $unit);

        return $this->history($request, $unit->inspections(), 'properties/units/inspections', [
            'property' => $property->only('id', 'slug', 'name'),
            'unit' => $unit->only('id', 'slug', 'name', 'floor', 'status'),
        ]);
    }

    public function leaseIndex(Request $request, Lease $lease): Response
    {
        $this->authorize('view', $lease);

        return $this->history($request, $lease->inspections(), 'leases/inspections', [
            'lease' => $lease->only('id', 'reference', 'status'),
        ]);
    }

    public function show(Inspection $inspection): Response
    {
        $this->authorize('view', $inspection);

        $inspection->load([
            'property:id,name,slug,deleted_at',
            'unit:id,name,slug,property_id,deleted_at',
            'lease:id,reference,unit_id,deleted_at',
            'inspector:id,name',
            'completedBy:id,name',
            'items.media',
        ]);

        return Inertia::render('inspections/show', [
            'inspection' => $this->serializeInspection($inspection),
            'can' => [
                'update' => Auth::user()->can('update', $inspection),
                'complete' => Auth::user()->can('complete', $inspection),
            ],
        ]);
    }

    public function storeForProperty(
        StoreInspectionRequest $request,
        Property $property,
        CreateInspection $createInspection,
    ): RedirectResponse {
        $this->authorize('view', $property);
        $this->authorize('create', Inspection::class);

        $createInspection->execute($property, null, null, $request->validated(), $request->user());

        return $this->createdResponse();
    }

    public function storeForUnit(
        StoreInspectionRequest $request,
        Property $property,
        Unit $unit,
        CreateInspection $createInspection,
    ): RedirectResponse {
        $this->authorize('view', $unit);
        $this->authorize('create', Inspection::class);

        $createInspection->execute($property, $unit, null, $request->validated(), $request->user());

        return $this->createdResponse();
    }

    public function storeForLease(
        StoreInspectionRequest $request,
        Lease $lease,
        CreateInspection $createInspection,
    ): RedirectResponse {
        $this->authorize('view', $lease);
        $this->authorize('create', Inspection::class);

        $lease->loadMissing('unit.property');
        abort_unless($lease->unit !== null, 422, __('The lease has no unit.'));
        $createInspection->execute($lease->unit->property, $lease->unit, $lease, $request->validated(), $request->user());

        return $this->createdResponse();
    }

    public function update(
        UpdateInspectionRequest $request,
        Inspection $inspection,
        UpdateInspection $updateInspection,
    ): RedirectResponse {
        $this->authorize('update', $inspection);
        $updateInspection->execute($inspection, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inspection draft saved.')]);

        return back();
    }

    public function complete(
        Request $request,
        Inspection $inspection,
        CompleteInspection $completeInspection,
    ): RedirectResponse {
        $this->authorize('complete', $inspection);
        $completeInspection->execute($inspection, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inspection completed.')]);

        return back();
    }

    private function history(
        Request $request,
        Builder|Relation $query,
        string $page,
        array $context,
        bool $global = false,
    ): Response {
        $columns = $global
            ? [
                Column::make('property_name', 'Property')->searchable(
                    fn (Builder $query, string $search) => $query->orWhereHas(
                        'property',
                        fn (Builder $propertyQuery) => $propertyQuery->whereRaw(
                            'lower(name) like ?',
                            ['%'.mb_strtolower($search).'%'],
                        ),
                    ),
                ),
                Column::make('unit_name', 'Unit')->searchable(
                    fn (Builder $query, string $search) => $query->orWhereHas(
                        'unit',
                        fn (Builder $unitQuery) => $unitQuery->whereRaw(
                            'lower(name) like ?',
                            ['%'.mb_strtolower($search).'%'],
                        ),
                    ),
                ),
                Column::make('lease_context', 'Lease / Tenant')->searchable(
                    fn (Builder $query, string $search) => $query->orWhereHas(
                        'lease',
                        fn (Builder $leaseQuery) => $leaseQuery
                            ->whereRaw('lower(reference) like ?', ['%'.mb_strtolower($search).'%'])
                            ->orWhereHas(
                                'primaryTenant',
                                fn (Builder $tenantQuery) => $tenantQuery->whereRaw(
                                    'lower(name) like ?',
                                    ['%'.mb_strtolower($search).'%'],
                                ),
                            ),
                    ),
                ),
                Column::make('template_name', 'Template')->sortable()->searchable(),
                Column::make('inspection_type', 'Type')->sortable(),
                Column::make('inspection_date', 'Date')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('inspector_name', 'Inspector')->searchable(
                    fn (Builder $query, string $search) => $query->orWhereHas(
                        'inspector',
                        fn (Builder $inspectorQuery) => $inspectorQuery->whereRaw(
                            'lower(name) like ?',
                            ['%'.mb_strtolower($search).'%'],
                        ),
                    ),
                ),
                Column::make('_actions', 'Actions'),
            ]
            : [
                Column::make('template_name', 'Checklist')->sortable()->searchable(),
                Column::make('inspection_type', 'Type')->sortable(),
                Column::make('inspection_date', 'Date')->sortable(),
                Column::make('status', 'Status')->sortable(),
            ];

        $filters = [
            Filter::select('inspection_type', 'Type', InspectionType::values())
                ->query(fn (Builder $query, string $value) => $query->where('inspection_type', $value)),
            Filter::select('status', 'Status', InspectionStatus::values())
                ->query(fn (Builder $query, string $value) => $query->where('status', $value)),
        ];

        if ($global) {
            array_unshift(
                $filters,
                Filter::select('property_id', 'Property', fn (): array => $this->propertyOptions($request))
                    ->query(fn (Builder $query, string $value) => $query->where('property_id', $value)),
            );
        }

        $table = Table::make()
            ->columns($columns)
            ->filters($filters)
            ->defaultSort('-inspection_date');

        $result = $table->paginate(
            $query->with([
                'property:id,name,slug',
                'unit:id,name,slug,property_id',
                'lease:id,reference,unit_id,primary_tenant_id',
                'lease.primaryTenant:id,name',
                'inspector:id,name',
            ]),
            $request,
            'inspections',
        );

        return Inertia::render($page, [
            ...$result,
            ...$context,
            'templates' => $this->templateOptions(),
            'can' => [
                'create' => $request->user()->can('inspections.create'),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, inspection_type: string}>
     */
    private function templateOptions(): array
    {
        return InspectionTemplate::query()
            ->where('is_active', true)
            ->orderBy('inspection_type')
            ->orderBy('name')
            ->get(['id', 'name', 'inspection_type'])
            ->map(fn (InspectionTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'inspection_type' => $template->inspection_type->value,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function propertyOptions(Request $request): array
    {
        return Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Property $property): array => [
                'value' => (string) $property->id,
                'label' => $property->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInspection(Inspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'template_name' => $inspection->template_name,
            'inspection_type' => $inspection->inspection_type->value,
            'inspection_date' => $inspection->inspection_date->toDateString(),
            'status' => $inspection->status->value,
            'notes' => $inspection->notes,
            'damage_observations' => $inspection->damage_observations,
            'completed_at' => $inspection->completed_at?->toIso8601String(),
            'property' => $inspection->property,
            'unit' => $inspection->unit,
            'lease' => $inspection->lease,
            'inspector' => $inspection->inspector,
            'completed_by' => $inspection->completedBy,
            'items' => $inspection->items->map(fn ($item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'description' => $item->description,
                'position' => $item->position,
                'condition' => $item->condition?->value,
                'notes' => $item->notes,
                'photos' => $item->media->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'url' => route('inspections.items.photos.show', [
                        'inspection' => $inspection,
                        'item' => $item,
                        'media' => $media,
                    ]),
                    'original_name' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ])->all(),
            ])->all(),
        ];
    }

    private function createdResponse(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inspection draft created.')]);

        return back();
    }
}
