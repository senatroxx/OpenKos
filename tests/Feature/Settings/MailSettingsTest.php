<?php

use App\Models\Setting;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('Mail settings page', function () {
    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.mail.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/mail')
                ->has('settings.mail_host')
                ->has('settings.mail_port')
                ->has('settings.mail_username')
            );
    });

    it('updates mail settings', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_host' => 'smtp.example.com',
                'mail_port' => 587,
                'mail_username' => 'user@example.com',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'Test',
            ])
            ->assertRedirect(route('settings.mail.edit'));

        expect(Setting::get('mail_host'))->toBe('smtp.example.com');
        expect(Setting::get('mail_port'))->toBe(587);
        expect(Setting::get('mail_username'))->toBe('user@example.com');
        expect(Setting::get('mail_encryption'))->toBe('tls');
        expect(Setting::get('mail_from_address'))->toBe('noreply@example.com');
        expect(Setting::get('mail_from_name'))->toBe('Test');
    });

    it('encrypts the mail password', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_password' => 'secret123',
            ]);

        expect(Setting::get('mail_password'))->toBe('secret123');
    });

    it('validates mail settings', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_port' => 'not-a-number',
                'mail_encryption' => 'invalid',
                'mail_from_address' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['mail_port', 'mail_encryption', 'mail_from_address']);
    });

    it('requires authentication', function () {
        $this->get(route('settings.mail.edit'))->assertRedirect(route('login'));
        $this->patch(route('settings.mail.update'))->assertRedirect(route('login'));
    });
});
