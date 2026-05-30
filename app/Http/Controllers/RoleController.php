<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Support\RecommendedRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'label', 'users_count'];
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

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw('lower(label) like ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']);
            }))
            ->when($status === 'active', fn ($q) => $q->whereRaw('is_active is true'))
            ->when($status === 'disabled', fn ($q) => $q->whereRaw('is_active is false'))
            ->orderByDesc('is_system')
            ->orderBy($sort === 'users_count' ? 'users_count' : $sort, $direction)
            ->paginate($perPage)
            ->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? ucfirst($role->name),
                'description' => $role->description,
                'color' => $role->color,
                'guard_name' => $role->guard_name,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at?->toISOString(),
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function create(Request $request): Response
    {
        $template = $request->query('template');
        $recommendation = null;

        if ($template) {
            $recommendation = collect(RecommendedRoles::all())->firstWhere('name', $template);
        }

        return Inertia::render('roles/create', [
            'permissionGroups' => Permission::grouped(),
            'recommendations' => RecommendedRoles::all(),
            'selectedRecommendation' => $recommendation,
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions:id,name');

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? ucfirst($role->name),
                'description' => $role->description,
                'color' => $role->color,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => Permission::grouped(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        DB::statement('UPDATE roles SET is_system = false, is_active = true WHERE id = ?', [$role->id]);
        $role->refresh();

        $role->syncPermissions($validated['permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        if (! $role->is_system) {
            $role->update([
                'label' => $validated['label'] ?? $role->label,
                'description' => $validated['description'] ?? $role->description,
                'color' => $validated['color'] ?? $role->color,
                'is_active' => $validated['is_active'] ?? $role->is_active,
            ]);

            $role->syncPermissions($validated['permissions'] ?? []);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('System roles cannot be deleted.')]);

            return to_route('roles.index');
        }

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    public function clone(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $newRole = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'label' => $validated['label'] ?? $role->label.' (Clone)',
            'description' => $role->description,
            'color' => $role->color,
        ]);

        DB::statement('UPDATE roles SET is_system = false, is_active = true WHERE id = ?', [$newRole->id]);
        $newRole->refresh();

        $newRole->syncPermissions($role->permissions->pluck('name')->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role cloned.')]);

        return to_route('roles.index');
    }
}
