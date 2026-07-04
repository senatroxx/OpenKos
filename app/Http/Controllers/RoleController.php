<?php

namespace App\Http\Controllers;

use App\Business\Roles\RoleGuard;
use App\Enums\Permission;
use App\Http\Requests\Role\CloneRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Support\RecommendedRoles;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function show(Role $role): Response
    {
        $role->loadCount('users')->load('permissions:id,name');

        return Inertia::render('roles/show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? ucfirst($role->name),
                'description' => $role->description,
                'color' => $role->color,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at?->toISOString(),
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $table = Table::make()
            ->columns([
                Column::make('label', 'Role')->sortable()->searchable(function (Builder $q, string $search): void {
                    $q->whereRaw('lower(label) like ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']);
                }),
                Column::make('users_count', 'Users')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'disabled'])
                    ->query(fn (Builder $q, string $value) => match ($value) {
                        'active' => $q->whereRaw('is_active is true'),
                        'disabled' => $q->whereRaw('is_active is false'),
                        default => $q,
                    }),
            ])
            ->defaultSort('label');

        $query = Role::query()
            ->withCount('users')
            ->with('permissions:id,name');

        $result = $table->paginate($query, $request, 'roles');

        $result['roles'] = $result['roles']->through(fn (Role $role) => [
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
            ...$result,
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

    public function update(UpdateRoleRequest $request, Role $role, RoleGuard $guard): RedirectResponse
    {
        $validated = $request->validated();

        if (! $guard->isSystem($role)) {
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

    public function destroy(Role $role, RoleGuard $guard): RedirectResponse
    {
        if ($guard->isSystem($role)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('System roles cannot be deleted.')]);

            return to_route('roles.index');
        }

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    public function clone(CloneRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

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
