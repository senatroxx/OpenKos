<?php

use App\Models\Tenant;
use App\Models\User;

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('registration creates a user without a tenant', function () {
    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->tenant()->exists())->toBeFalse()
        ->and(Tenant::query()->count())->toBe(0);
});
