<?php

use App\Models\Setting;
use App\Models\User;

test('install command creates owner account and settings', function () {
    $this->artisan('app:install')
        ->expectsQuestion('What is the name of this installation?', 'Kos Pak Budi')
        ->expectsQuestion('What country is this installation for?', 'ID')
        ->expectsQuestion('What is the owner\'s name?', 'Pak Budi')
        ->expectsQuestion('What is the owner\'s email address?', 'pakbudi@openkos.com')
        ->expectsQuestion('Choose a password for the owner account', 'password123')
        ->assertSuccessful();

    $user = User::first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Pak Budi');
    expect($user->email)->toBe('pakbudi@openkos.com');
    expect($user->hasRole('owner'))->toBeTrue();

    $setting = Setting::first();

    expect($setting)->not->toBeNull();
    expect($setting->site_name)->toBe('Kos Pak Budi');
    expect($setting->country_code)->toBe('ID');
    expect($setting->currency)->toBe('IDR');
    expect($setting->timezone)->toBe('Asia/Jakarta');
});

test('install command stores other country defaults', function () {
    $this->artisan('app:install')
        ->expectsQuestion('What is the name of this installation?', 'My Boarding')
        ->expectsQuestion('What country is this installation for?', 'XX')
        ->expectsQuestion('What is the owner\'s name?', 'John')
        ->expectsQuestion('What is the owner\'s email address?', 'john@example.com')
        ->expectsQuestion('Choose a password for the owner account', 'password123')
        ->assertSuccessful();

    $setting = Setting::first();

    expect($setting->site_name)->toBe('My Boarding');
    expect($setting->country_code)->toBe('XX');
    expect($setting->currency)->toBe('USD');
});

test('install command fails when users already exist', function () {
    User::factory()->create();

    $this->artisan('app:install')
        ->expectsOutput('Users already exist. Installation has already been completed.')
        ->assertFailed();
});
