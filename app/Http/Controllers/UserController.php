<?php

namespace App\Http\Controllers;

use App\Business\Users\UserGuard;
use App\Enums\Role;
use App\Http\Requests\User\CompleteInvitationRequest;
use App\Http\Requests\User\StoreUserInvitationRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Property;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function show(User $user): Response
    {
        abort_if($user->tenant()->exists(), 404);

        $user->load(['roles:id,name,label', 'properties:id,name']);

        return Inertia::render('users/show', [
            'user' => $this->transformUser($user),
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
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
            'status' => $user->invited_at ? 'invited' : ($user->is_active ? 'active' : 'disabled'),
            'invited_at' => $user->invited_at?->toISOString(),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];
    }

    public function index(Request $request): Response
    {
        $assignableRoles = RoleModel::query()
            ->where('is_active', true)
            ->where('name', '!=', Role::Owner->value)
            ->orderBy('name')
            ->get(['name', 'label']);

        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(),
                Column::make('email', 'Email')->sortable()->searchable(),
                Column::make('last_login_at', 'Last Login')->sortable(),
            ])
            ->filters([
                Filter::select('role', 'Role', $assignableRoles->map(fn (RoleModel $r) => [
                    'value' => $r->name,
                    'label' => $r->label ?? ucfirst($r->name),
                ])->all())
                    ->query(fn (Builder $q, string $value) => $q->when(
                        RoleModel::whereName($value)->exists(),
                        fn (Builder $q) => $q->role($value),
                    )),
                Filter::select('status', 'Status', ['active', 'invited', 'disabled'])
                    ->query(fn (Builder $q, string $value) => match ($value) {
                        'active' => $q->where('is_active', true),
                        'invited' => $q->whereNotNull('invited_at'),
                        'disabled' => $q->where('is_active', false)->whereNull('invited_at'),
                        default => $q,
                    }),
            ])
            ->defaultSort('name');

        $query = User::query()
            ->with(['roles:id,name,label', 'properties:id,name'])
            ->whereDoesntHave('tenant');

        $result = $table->paginate($query, $request, 'users');

        $result['users'] = $result['users']->through(fn (User $user) => $this->transformUser($user));

        return Inertia::render('users/index', [
            ...$result,
            'properties' => Property::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => $assignableRoles->map(fn (RoleModel $role) => [
                'value' => $role->name,
                'label' => $role->label ?? ucfirst($role->name),
            ]),
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

            User::query()->whereKey($user->id)->update(['is_active' => false]);

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

    public function update(UpdateUserRequest $request, User $user, UserGuard $guard): RedirectResponse
    {
        $this->ensureNotTenant($user);

        $validated = $request->validated();

        if (! $validated['is_active'] && $guard->isLastActiveOwner($user)) {
            return back()->withErrors(['is_active' => __('At least one active owner must remain.')]);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            User::query()->whereKey($user->id)->update([
                'is_active' => $validated['is_active'],
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

    public function destroy(User $user, UserGuard $guard): RedirectResponse
    {
        $this->ensureNotTenant($user);

        if ($guard->isLastActiveOwner($user)) {
            return back()->withErrors(['user' => __('At least one active owner must remain.')]);
        }

        User::query()->whereKey($user->id)->update([
            'is_active' => false,
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

            User::query()->whereKey($user->id)->update(['is_active' => true]);

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

    private function createInvitationToken(User $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker();

        return $broker->createToken($user);
    }
}
