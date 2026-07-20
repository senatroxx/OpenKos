<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('does not leak mail or whatsapp secrets in shared props', function () {
    Setting::set('mail_config', ['host' => 'smtp.example.com', 'password' => 'super-secret'], 'array');
    Setting::set('whatsapp_config', ['baileys' => ['token' => 'wa-token-123']], 'array');

    $props = $this->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->viewData('page')['props'];

    expect($props['setting'])->not->toHaveKey('mail_config');
    expect($props['setting'])->not->toHaveKey('whatsapp_config');
    expect(json_encode($props))->not->toContain('super-secret');
    expect(json_encode($props))->not->toContain('wa-token-123');
});

it('exposes notification channel readiness as booleans only', function () {
    $owner = User::factory()->owner()->create();

    $props = $this->actingAs($owner)->get(route('dashboard'))->viewData('page')['props'];
    expect($props['notificationChannels'])->toBe(['mail' => false, 'whatsapp' => false]);

    Setting::set('mail_config', ['host' => 'smtp.example.com'], 'array');
    Setting::set('whatsapp_driver', 'baileys', 'string');

    $props = $this->actingAs($owner)->get(route('dashboard'))->viewData('page')['props'];
    expect($props['notificationChannels'])->toBe(['mail' => true, 'whatsapp' => true]);
});
