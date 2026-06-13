<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\User\CompleteInvitationRequest;
use App\Http\Requests\User\StoreUserInvitationRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Property;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $search = $request->query('search', '');
        $role = $request->query('role', '');
        $status = $request->query('status', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'email', 'last_login_at'];
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

        $users = User::query()
            ->with(['roles:id,name,label', 'properties:id,name'])
            ->whereDoesntHave('tenant')
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(email)'), 'like', '%'.mb_strtolower($search).'%');
            }))
            ->when($role && RoleModel::whereName($role)->exists(), fn (Builder $q) => $q->role($role))
            ->when($status === 'active', fn (Builder $q) => $q->whereRaw('is_active is true'))
            ->when($status === 'invited', fn (Builder $q) => $q->whereNotNull('invited_at'))
            ->when($status === 'disabled', fn (Builder $q) => $q->whereRaw('is_active is false')->whereNull('invited_at'))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(fn ($role) => [
                    'name' => $role->name,
                    'label' => $role->label ?? ucfirst($role->name),
                ])->values(),
                'role' => $user->roles->first()?->name,
                'properties' => $user->properties->map(fn (Property $property) => [
                    'id' => $property->id,
                    'name' => $property->name,
                ])->values(),
                'is_active' => $user->is_active,
                'status' => $this->statusFor($user),
                'invited_at' => $user->invited_at?->toISOString(),
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString(),
            ]);

        $assignableRoles = RoleModel::query()
            ->whereRaw('is_active is true')
            ->where('name', '!=', Role::Owner->value)
            ->orderBy('name')
            ->get(['name', 'label']);

        return Inertia::render('users/index', [
            'users' => $users,
            'properties' => Property::query()->whereRaw('is_active is true')->orderBy('name')->get(['id', 'name']),
            'roles' => $assignableRoles->map(fn (RoleModel $role) => [
                'value' => $role->name,
                'label' => $role->label ?? ucfirst($role->name),
            ]),
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function store(StoreUserInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::password(64)),
                'invited_at' => now(),
            ]);

            User::query()->whereKey($user->id)->update(['is_active' => DB::raw('false')]);

            $roleNames = $validated['roles'];
            $user->syncRoles($roleNames);
            $user->properties()->sync($validated['property_ids'] ?? []);

            $token = $this->createInvitationToken($user);
            $user->notify(new UserInvitation(route('users.invitations.accept', [
                'token' => $token,
                'email' => $user->email,
            ])));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User invited.')]);

        return to_route('users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureNotTenant($user);

        $validated = $request->validated();

        if (! $validated['is_active'] && $this->isLastActiveOwner($user)) {
            return back()->withErrors(['is_active' => __('At least one active owner must remain.')]);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            User::query()->whereKey($user->id)->update([
                'is_active' => DB::raw($validated['is_active'] ? 'true' : 'false'),
            ]);

            if (! $user->isOwner()) {
                $roleNames = $validated['roles'] ?? [];
                $user->syncRoles($roleNames);
            }

            $user->properties()->sync($validated['property_ids'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureNotTenant($user);

        if ($this->isLastActiveOwner($user)) {
            return back()->withErrors(['user' => __('At least one active owner must remain.')]);
        }

        User::query()->whereKey($user->id)->update([
            'is_active' => DB::raw('false'),
            'invited_at' => null,
        ]);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User access disabled.')]);

        return to_route('users.index');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->ensureNotTenant($user);

        if (! $user->is_active) {
            return back()->withErrors(['user' => __('Disabled users cannot receive password reset links.')]);
        }

        Password::broker()->sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password reset link sent.')]);

        return to_route('users.index');
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        $this->ensureNotTenant($user);

        if (! $user->invited_at) {
            return back()->withErrors(['user' => __('Only invited users can receive invitation links.')]);
        }

        $token = $this->createInvitationToken($user);
        $user->notify(new UserInvitation(route('users.invitations.accept', [
            'token' => $token,
            'email' => $user->email,
        ])));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation link resent.')]);

        return to_route('users.index');
    }

    public function acceptInvitation(Request $request, string $token): Response
    {
        return Inertia::render('auth/accept-invitation', [
            'email' => $request->query('email', ''),
            'token' => $token,
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function completeInvitation(CompleteInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $invitedUser = User::where('email', $validated['email'])->first();

        if (! $invitedUser?->invited_at) {
            return back()->withErrors(['email' => __('This invitation is invalid or inactive.')]);
        }

        $status = Password::broker()->reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'email_verified_at' => now(),
                'invited_at' => null,
                'remember_token' => Str::random(60),
            ])->save();

            User::query()->whereKey($user->id)->update(['is_active' => DB::raw('true')]);

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return to_route('login')->with('status', __('Invitation accepted. You can now log in.'));
    }

    private function ensureNotTenant(User $user): void
    {
        abort_if($user->hasTenantProfile(), 404);
    }

    private function isLastActiveOwner(User $user): bool
    {
        return $user->isOwner()
            && User::role(Role::Owner->value)->whereRaw('is_active is true')->whereKeyNot($user->id)->doesntExist();
    }

    private function createInvitationToken(User $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker();

        return $broker->createToken($user);
    }

    private function statusFor(User $user): string
    {
        if ($user->invited_at) {
            return 'invited';
        }

        return $user->is_active ? 'active' : 'disabled';
    }
}
