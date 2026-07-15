<?php

use App\Models\Setting;
use App\Models\User;

test('install command creates owner account and settings', function () {
    $this->artisan('app:install')
        ->expectsQuestion('What is the name of this installation?', 'Kos Pak Budi')
        ->expectsQuestion('What country is this installation for?', 'ID')
        ->expectsQuestion('What is the application URL?', 'http://localhost')
        ->expectsQuestion('What timezone should be used?', 'Asia/Jakarta')
        ->expectsQuestion('What is the owner\'s name?', 'Pak Budi')
        ->expectsQuestion('What is the owner\'s email address?', 'pakbudi@openkos.com')
        ->expectsQuestion('Choose a password for the owner account', 'password123')
        ->expectsQuestion('Confirm the password', 'password123')
        ->assertSuccessful();

    $user = User::first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Pak Budi');
    expect($user->email)->toBe('pakbudi@openkos.com');
    expect($user->hasRole('owner'))->toBeTrue();

    expect(Setting::get('site_name'))->toBe('Kos Pak Budi');
    expect(Setting::get('country_code'))->toBe('ID');
    expect(Setting::get('locale'))->toBe('en');
    expect(Setting::get('currency'))->toBe('IDR');
    expect(Setting::get('timezone'))->toBe('Asia/Jakarta');
});

test('install command stores other country defaults', function () {
    $this->artisan('app:install')
        ->expectsQuestion('What is the name of this installation?', 'My Boarding')
        ->expectsQuestion('What country is this installation for?', 'XX')
        ->expectsQuestion('What is the application URL?', 'http://localhost')
        ->expectsQuestion('What timezone should be used?', 'UTC')
        ->expectsQuestion('What is the owner\'s name?', 'John')
        ->expectsQuestion('What is the owner\'s email address?', 'john@example.com')
        ->expectsQuestion('Choose a password for the owner account', 'password123')
        ->expectsQuestion('Confirm the password', 'password123')
        ->assertSuccessful();

    expect(Setting::get('site_name'))->toBe('My Boarding');
    expect(Setting::get('country_code'))->toBe('XX');
    expect(Setting::get('currency'))->toBe('USD');
    expect(Setting::get('timezone'))->toBe('UTC');
});

test('install command fails when users already exist', function () {
    User::factory()->create();

    $this->artisan('app:install')
        ->expectsOutput('Users already exist. Installation has already been completed.')
        ->assertFailed();
});
