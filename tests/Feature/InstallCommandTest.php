<?php

use App\Models\User;

test('install command creates owner account', function () {
    $this->artisan('app:install')
        ->expectsQuestion('What is the owner\'s name?', 'Pak Budi')
        ->expectsQuestion('What is the owner\'s email address?', 'pakbudi@openkos.com')
        ->expectsQuestion('Choose a password for the owner account', 'password123')
        ->assertSuccessful();

    $user = User::first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Pak Budi');
    expect($user->email)->toBe('pakbudi@openkos.com');
    expect($user->hasRole('owner'))->toBeTrue();
});

test('install command fails when users already exist', function () {
    User::factory()->create();

    $this->artisan('app:install')
        ->expectsOutput('Users already exist. Installation has already been completed.')
        ->assertFailed();
});
