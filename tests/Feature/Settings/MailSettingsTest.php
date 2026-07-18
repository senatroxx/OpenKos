<?php

use App\Models\Setting;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('Mail settings page', function () {
    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->get(route('settings.mail.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/mail')
                ->has('settings.mail_config')
            );
    });

    it('updates mail settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_config' => [
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'username' => 'user@example.com',
                    'encryption' => 'tls',
                    'from_address' => 'noreply@example.com',
                    'from_name' => 'Test',
                ],
            ])
            ->assertRedirect(route('settings.mail.edit'));

        $config = Setting::get('mail_config');

        expect($config['host'])->toBe('smtp.example.com');
        expect($config['port'])->toBe(587);
        expect($config['username'])->toBe('user@example.com');
        expect($config['encryption'])->toBe('tls');
        expect($config['from_address'])->toBe('noreply@example.com');
        expect($config['from_name'])->toBe('Test');
    });

    it('encrypts the mail password', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_config' => [
                    'password' => 'secret123',
                ],
            ]);

        $config = Setting::get('mail_config');

        expect($config['password'])->toBe('secret123');
    });

    it('validates mail settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_config' => [
                    'port' => 'not-a-number',
                    'encryption' => 'invalid',
                    'from_address' => 'not-an-email',
                ],
            ])
            ->assertSessionHasErrors([
                'mail_config.port',
                'mail_config.encryption',
                'mail_config.from_address',
            ]);
    });

    it('requires authentication', function () {
        $this->get(route('settings.mail.edit'))->assertRedirect(route('login'));
        $this->patch(route('settings.mail.update'))->assertRedirect(route('login'));
    });
});
