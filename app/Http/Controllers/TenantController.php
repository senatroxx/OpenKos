<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'phone', 'email'];
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

        $tenants = Tenant::query()
            ->with(['leases' => fn ($q) => $q->whereNull('end_date'), 'leases.room.property'])
            ->withCount(['leases as active_leases_count' => fn (Builder $q) => $q->whereNull('end_date')])
            ->when($status === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($status === 'archived', fn (Builder $q) => $q->onlyTrashed())
            ->when(! $status || $status === 'active' || $status === 'inactive', fn (Builder $q) => $q->whereNull('deleted_at'))
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(phone)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(email)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(id_card_number)'), 'like', '%'.mb_strtolower($search).'%');
            }))
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return Inertia::render('tenants/index', [
            'tenants' => $tenants,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant created.')]);

        return to_route('tenants.index');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant updated.')]);

        return to_route('tenants.index');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant archived.')]);

        return to_route('tenants.index');
    }
}
