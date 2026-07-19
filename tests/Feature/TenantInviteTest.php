<?php

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitation;
use App\Policies\TenantPolicy;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('invite tenant', function () {
    it('creates a user and links to tenant', function () {
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->from(route('tenants.show', $tenant))
            ->post(route('tenants.invite', $tenant), [
                'email' => 'tenant@example.com',
            ])
            ->assertRedirect(route('tenants.show', $tenant));

        $tenant->refresh();

        expect($tenant->user)->not->toBeNull();
        expect($tenant->user->email)->toBe('tenant@example.com');
        expect($tenant->user->is_active)->toBeFalse();
        expect($tenant->user->invited_at)->not->toBeNull();

        Notification::assertSentTo($tenant->user, TenantInvitation::class);
    });

    it('saves the email without sending when send_invite is false', function () {
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->from(route('tenants.show', $tenant))
            ->post(route('tenants.invite', $tenant), [
                'email' => 'tenant@example.com',
                'send_invite' => false,
            ])
            ->assertRedirect(route('tenants.show', $tenant));

        $tenant->refresh();

        expect($tenant->user)->not->toBeNull();
        expect($tenant->user->email)->toBe('tenant@example.com');
        expect($tenant->user->is_active)->toBeFalse();
        expect($tenant->user->invited_at)->toBeNull();

        Notification::assertNotSentTo($tenant->user, TenantInvitation::class);
    });

    it('returns 403 for user without tenants.invite permission', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->post(route('tenants.invite', $tenant), [
                'email' => 'tenant@example.com',
            ])
            ->assertForbidden();
    });

    it('validates email is required', function () {
        $owner = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->post(route('tenants.invite', $tenant), [])
            ->assertSessionHasErrors('email');
    });

    it('validates email is unique in users table', function () {
        $owner = User::factory()->owner()->create();
        User::factory()->create(['email' => 'existing@example.com']);
        $tenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->post(route('tenants.invite', $tenant), [
                'email' => 'existing@example.com',
            ])
            ->assertSessionHasErrors('email');
    });

    it('returns error when tenant already has a linked user', function () {
        $owner = User::factory()->owner()->create();
        $tenant = Tenant::factory()->withUser()->create();

        $this->actingAs($owner)
            ->from(route('tenants.show', $tenant))
            ->post(route('tenants.invite', $tenant), [
                'email' => 'another@example.com',
            ])
            ->assertRedirect(route('tenants.show', $tenant));
    });
});

describe('resend invitation', function () {
    it('resends to an invited tenant', function () {
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => now(),
            'is_active' => false,
        ]);
        $tenant = Tenant::factory()->create(['user_id' => $user->id]);

        $this->actingAs($owner)
            ->post(route('tenants.resend-invitation', $tenant))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, TenantInvitation::class);
    });

    it('sets invited_at if null before resending', function () {
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => null,
            'is_active' => false,
        ]);
        $tenant = Tenant::factory()->create(['user_id' => $user->id]);

        $this->actingAs($owner)
            ->post(route('tenants.resend-invitation', $tenant))
            ->assertSessionHasNoErrors();

        expect($user->fresh()->invited_at)->not->toBeNull();

        Notification::assertSentTo($user, TenantInvitation::class);
    });

    it('resends to an active tenant without re-flagging invited_at', function () {
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $tenant = Tenant::factory()->create(['user_id' => $user->id]);

        $this->actingAs($owner)
            ->post(route('tenants.resend-invitation', $tenant))
            ->assertSessionHasNoErrors();

        // Still emails a fresh link, but the active account stays "not pending".
        Notification::assertSentTo($user, TenantInvitation::class);
        expect($user->fresh()->invited_at)->toBeNull();
    });

    it('returns error if tenant has no user account', function () {
        $owner = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->post(route('tenants.resend-invitation', $tenant))
            ->assertSessionHasErrors('invite');
    });
});

describe('disable access', function () {
    it('deactivates the linked user', function () {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => now(),
            'is_active' => false,
        ]);
        $tenant = Tenant::factory()->create(['user_id' => $user->id]);

        $this->actingAs($owner)
            ->post(route('tenants.disable-access', $tenant))
            ->assertSessionHasNoErrors();

        $user->refresh();

        expect($user->is_active)->toBeFalse();
        expect($user->invited_at)->toBeNull();
    });

    // Regression: policy must still grant invite/disable/resend for scoped staff
    // once the tenant's user is active (post-activation lifecycle).
    it('lets scoped staff manage access after the tenant user is active', function () {
        $staff = User::factory()->staff()->create();
        $tenant = Tenant::factory()->withUser()->create();
        $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
        $lease->unit->property->users()->attach($staff);

        expect($tenant->user->is_active)->toBeTrue();
        expect(app(TenantPolicy::class)->invite($staff, $tenant))->toBeTrue();
    });
});

describe('invitation acceptance', function () {
    it('serves the accept page to a guest', function () {
        $this->get(route('tenants.invitations.accept', ['token' => 'some-token', 'email' => 'tenant@example.com']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/tenant-accept-invitation'));
    });

    it('accepts a valid invitation and sets password', function () {
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => now(),
            'is_active' => false,
        ]);
        Tenant::factory()->create(['user_id' => $user->id]);

        $token = Password::broker()->createToken($user);

        $this->post(route('tenants.invitations.complete'), [
                'email' => 'tenant@example.com',
                'token' => $token,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $user->refresh();

        expect($user->email_verified_at)->not->toBeNull();
        expect($user->is_active)->toBeTrue();
        expect($user->invited_at)->toBeNull();
    });

    it('rejects an invalid token', function () {
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => now(),
            'is_active' => false,
        ]);
        Tenant::factory()->create(['user_id' => $user->id]);

        $this->withSession(['_previous' => ['url' => route('tenants.invitations.accept', ['token' => 'invalid-token', 'email' => 'tenant@example.com'])]])
            ->post(route('tenants.invitations.complete'), [
                'email' => 'tenant@example.com',
                'token' => 'invalid-token',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors(['email']);
    });

    it('rejects completion for a user without a tenant profile', function () {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'invited_at' => now(),
            'is_active' => false,
        ]);

        $token = Password::broker()->createToken($user);

        $this->withSession(['_previous' => ['url' => route('tenants.invitations.accept', ['token' => $token, 'email' => 'staff@example.com'])]])
            ->post(route('tenants.invitations.complete'), [
                'email' => 'staff@example.com',
                'token' => $token,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors(['email']);

        expect($user->fresh()->is_active)->toBeFalse();
    });

    it('lets an active tenant reset password via a resend link', function () {
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        Tenant::factory()->create(['user_id' => $user->id]);

        $token = Password::broker()->createToken($user);

        $this->post(route('tenants.invitations.complete'), [
            'email' => 'tenant@example.com',
            'token' => $token,
            'password' => 'ResetPassword123!',
            'password_confirmation' => 'ResetPassword123!',
        ])->assertRedirect('/login');

        expect($user->fresh()->is_active)->toBeTrue();
    });

    it('rejects completion for a disabled tenant', function () {
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'invited_at' => null,
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        Tenant::factory()->create(['user_id' => $user->id]);

        $token = Password::broker()->createToken($user);

        $this->withSession(['_previous' => ['url' => route('tenants.invitations.accept', ['token' => $token, 'email' => 'tenant@example.com'])]])
            ->post(route('tenants.invitations.complete'), [
                'email' => 'tenant@example.com',
                'token' => $token,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors(['email']);

        expect($user->fresh()->is_active)->toBeFalse();
    });
});
