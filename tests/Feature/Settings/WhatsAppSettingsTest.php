<?php

use App\Models\Setting;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('WhatsApp settings page', function () {
    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/whatsapp')
                ->has('drivers')
                ->has('settings.whatsapp_driver')
                ->has('settings.whatsapp_config')
            );
    });

    it('contains all built-in drivers', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('drivers.0.name', 'log')
                ->where('drivers.1.name', 'baileys')
                ->where('drivers.2.name', 'fonnte')
                ->where('drivers.3.name', 'whatsapp_cloud')
            );
    });

    it('updates whatsapp driver selection', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'fonnte',
            ])
            ->assertRedirect();

        expect(Setting::get()->whatsapp_driver)->toBe('fonnte');
    });

    it('updates whatsapp config', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'fonnte',
                'whatsapp_config' => [
                    'fonnte' => [
                        'token' => 'test-token-123',
                    ],
                ],
            ])
            ->assertRedirect();

        $config = Setting::get()->whatsapp_config;

        expect($config['fonnte']['token'])->toBe('test-token-123');
    });

    it('does not overwrite existing config on update', function () {
        $owner = User::factory()->owner()->create();
        $setting = Setting::get();
        $setting->whatsapp_config = ['fonnte' => ['token' => 'existing-token']];
        $setting->save();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'baileys',
                'whatsapp_config' => [
                    'baileys' => ['url' => 'http://localhost:3000'],
                ],
            ])
            ->assertRedirect();

        $config = Setting::get()->whatsapp_config;
        expect($config['fonnte']['token'])->toBe('existing-token');
        expect($config['baileys']['url'])->toBe('http://localhost:3000');
    });

    it('tests connection with log driver', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('settings.whatsapp.test'))
            ->assertRedirect();
    });

    it('is owner-only', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.whatsapp.edit'))
            ->assertForbidden();
    });

    it('validates driver name', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'invalid_driver',
            ])
            ->assertSessionHasErrors(['whatsapp_driver']);
    });

    it('returns status json', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.status'))
            ->assertOk()
            ->assertJsonStructure(['healthy', 'message', 'phone', 'lastConnected']);
    });

    it('disconnects baileys driver', function () {
        $owner = User::factory()->owner()->create();
        $setting = Setting::get();
        $setting->whatsapp_driver = 'baileys';
        $setting->whatsapp_config = ['baileys' => ['url' => 'http://localhost:3000', 'api_key' => 'secret']];
        $setting->save();

        Http::fake([
            '*/api/sessions' => Http::response(['meta' => ['success' => true]]),
        ]);

        $this->actingAs($owner)
            ->delete(route('settings.whatsapp.disconnect'))
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    });

});
