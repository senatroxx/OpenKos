<?php

use App\Enums\Role;
use App\Models\Property;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

test('owner can view users page and tenant users are excluded', function () {
    $owner = User::factory()->owner()->create(['name' => 'Owner User']);
    $admin = User::factory()->admin()->create(['name' => 'Admin User']);
    $tenantUser = User::factory()->create(['name' => 'Tenant User']);
    Tenant::factory()->create(['user_id' => $tenantUser->id]);

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.name', $admin->name)
            ->where('users.data.0.status', 'active')
            ->where('users.data.1.name', $owner->name)
        );
});

test('non owners cannot view users page', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('invalid role filter falls back gracefully without error', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('users.index', ['role' => 'does-not-exist']))
        ->assertOk();
});

test('owner can invite admin and assign properties with custom notification', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();
    $properties = Property::factory()->count(2)->create();

    RoleModel::findOrCreate('admin')->update(['label' => 'Admin']);

    $this->actingAs($owner)
        ->post(route('users.store'), [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'roles' => ['admin'],
            'property_ids' => $properties->pluck('id')->all(),
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->properties()->pluck('properties.id')->all())->toEqualCanonicalizing($properties->pluck('id')->all())
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->invited_at)->not->toBeNull()
        ->and($user->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->get(route('users.index', ['status' => 'invited']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'admin@example.com')
            ->where('users.data.0.status', 'invited')
        );

    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user) {
        $url = $notification->toArray($user)['url'];

        return str_contains($url, route('users.invitations.accept', ['token' => 'placeholder'], false)) === false
            && str_contains($url, '/invitations/')
            && str_contains($url, 'email=admin%40example.com');
    });
});

test('owner can invite user with multiple roles', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();

    RoleModel::findOrCreate('admin')->update(['label' => 'Admin']);
    RoleModel::findOrCreate('staff')->update(['label' => 'Staff']);

    $this->actingAs($owner)
        ->post(route('users.store'), [
            'name' => 'Multi Role User',
            'email' => 'multi@example.com',
            'roles' => ['admin', 'staff'],
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'multi@example.com')->firstOrFail();

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasRole('staff'))->toBeTrue();
});

test('owner can invite user with custom role', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();

    $customRole = RoleModel::create([
        'name' => 'finance-staff',
        'label' => 'Finance Staff',
        'guard_name' => 'web',
        'is_system' => false,
        'is_active' => true,
    ]);
    $customRole->givePermissionTo('financials.view');

    $this->actingAs($owner)
        ->post(route('users.store'), [
            'name' => 'Finance User',
            'email' => 'finance@example.com',
            'roles' => ['finance-staff'],
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'finance@example.com')->firstOrFail();
    expect($user->hasRole('finance-staff'))->toBeTrue();
});

test('owner cannot invite owner through users page', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('users.store'), [
            'name' => 'Owner User',
            'email' => 'new-owner@example.com',
            'roles' => [Role::Owner->value],
        ])
        ->assertSessionHasErrors('roles.0');

    expect(User::where('email', 'new-owner@example.com')->exists())->toBeFalse();
});

test('invited user accepts invitation on dedicated invitation route and becomes verified', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();

    RoleModel::findOrCreate('staff')->update(['label' => 'Staff']);

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Staff User',
        'email' => 'staff@example.com',
        'roles' => ['staff'],
    ]);

    $user = User::where('email', 'staff@example.com')->firstOrFail();
    $inviteUrl = null;

    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user, &$inviteUrl) {
        $inviteUrl = $notification->toArray($user)['url'];

        return true;
    });

    $token = Str::between($inviteUrl, '/invitations/', '?');

    auth()->logout();

    $this->post(route('users.invitations.complete'), [
        'token' => $token,
        'email' => 'staff@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->invited_at)->toBeNull()
        ->and($user->is_active)->toBeTrue();
});

test('disabled users cannot log in', function () {
    $user = User::factory()->admin()->create(['email' => 'disabled@example.com', 'is_active' => false]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('owner can disable staff but cannot disable last owner', function () {
    $owner = User::factory()->owner()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($owner)
        ->delete(route('users.destroy', $staff))
        ->assertRedirect(route('users.index'));

    expect($staff->refresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('users.destroy', $owner))
        ->assertSessionHasErrors('user');

    expect($owner->refresh()->is_active)->toBeTrue();
});

test('reset password action sends default reset notification', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($owner)
        ->post(route('users.reset-password', $admin))
        ->assertRedirect(route('users.index'));

    Notification::assertSentTo($admin, ResetPassword::class);
    Notification::assertNotSentTo($admin, UserInvitation::class);
});

test('owner can resend invitation link for invited user', function () {
    Notification::fake();
    $owner = User::factory()->owner()->create();
    $invitedUser = User::factory()->admin()->create([
        'email' => 'pending@example.com',
        'is_active' => false,
        'invited_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('users.resend-invitation', $invitedUser))
        ->assertRedirect(route('users.index'));

    Notification::assertSentTo($invitedUser, UserInvitation::class);
});

test('assigned property scope limits non owner dashboard and property list', function () {
    $admin = User::factory()->admin()->create();
    $assignedProperty = Property::factory()->create(['name' => 'Assigned Kos']);
    $otherProperty = Property::factory()->create(['name' => 'Hidden Kos']);
    Unit::factory()->count(2)->create(['property_id' => $assignedProperty->id]);
    Unit::factory()->count(3)->create(['property_id' => $otherProperty->id]);

    $admin->properties()->sync([$assignedProperty->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 2)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.name', 'Assigned Kos')
        );

    $this->actingAs($admin)
        ->get(route('properties.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Assigned Kos')
        );
});

test('non owner cannot access unassigned property units', function () {
    $admin = User::factory()->admin()->create();
    $assignedProperty = Property::factory()->create();
    $otherProperty = Property::factory()->create();

    $admin->properties()->sync([$assignedProperty->id]);

    $this->actingAs($admin)
        ->get(route('properties.units.index', $otherProperty))
        ->assertForbidden();
});
