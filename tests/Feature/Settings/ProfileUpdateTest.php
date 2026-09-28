<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('tenant profile page only shares account settings pages', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.tenant.id', $tenant->id)
            ->where('auth.tenant.name', $tenant->name)
            ->has('platform.settings', 3)
            ->where('platform.settings.0.key', 'about')
            ->where('platform.settings.0.ownerOnly', false)
            ->where('platform.settings.1.key', 'profile')
            ->where('platform.settings.1.ownerOnly', false)
            ->where('platform.settings.2.key', 'security')
            ->where('platform.settings.2.ownerOnly', false)
            ->missing('platform.settings.3'));
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('portal profile email address cannot be changed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('portal.profile.edit'))
        ->patch(route('portal.profile.update'), [
            'name' => $user->name,
            'email' => 'changed@example.com',
            'phone' => '+628123456789',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('portal.profile.edit'));

    expect($user->refresh()->email)->not->toBe('changed@example.com');
});

test('portal profile stores renter details', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('portal.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+628123456789',
            'id_card_number' => '3273010203040005',
            'emergency_contact_name' => 'Siti Nurhaliza',
            'emergency_contact_phone' => '+628987654321',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->only([
        'phone',
        'id_card_number',
        'emergency_contact_name',
        'emergency_contact_phone',
    ]))->toBe([
        'phone' => '+628123456789',
        'id_card_number' => '3273010203040005',
        'emergency_contact_name' => 'Siti Nurhaliza',
        'emergency_contact_phone' => '+628987654321',
    ]);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
