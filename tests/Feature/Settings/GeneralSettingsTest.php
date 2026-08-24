<?php

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['inertia.ssr.enabled' => false]);
});

describe('General settings page', function () {
    it('forbids tenant-linked users from viewing the page', function () {
        $tenantUser = User::factory()->create();
        Tenant::factory()->withUser($tenantUser)->create();

        $this->actingAs($tenantUser)
            ->get(route('settings.general.edit'))
            ->assertForbidden();
    });

    it('forbids tenant-linked users from updating settings', function () {
        $tenantUser = User::factory()->create();
        Tenant::factory()->withUser($tenantUser)->create();

        $this->actingAs($tenantUser)
            ->patch(route('settings.general.update'), [
                'site_name' => 'Tenant Attempt',
                'country_code' => 'ID',
                'locale' => 'id',
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'lease_id_prefix' => 'TEN',
                'invoice_id_prefix' => 'INV',
            ])
            ->assertForbidden();
    });

    it('renders the form with all settings', function () {
        $owner = User::factory()->owner()->create();

        Setting::set('site_name', 'OpenKOS');
        Setting::set('country_code', 'ID');
        Setting::set('locale', 'id');
        Setting::set('currency', 'IDR');
        Setting::set('timezone', 'Asia/Jakarta');
        Setting::set('lease_id_prefix', 'LSX');
        Setting::set('invoice_id_prefix', 'INV');

        $this->from(route('settings.general.edit'))->actingAs($owner)
            ->get(route('settings.general.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/general')
                ->has('settings.site_name')
                ->has('settings.country_code')
                ->has('settings.locale')
                ->has('settings.currency')
                ->where('settings.supported_currencies', ['IDR'])
                ->has('settings.timezone')
                ->has('settings.lease_id_prefix')
                ->has('settings.invoice_id_prefix')
                ->has('settings.invoice_pdf_enabled')
                ->has('timezone_list')
            );
    });

    it('updates all general settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.general.edit'))->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'site_name' => 'My Property Mgmt',
                'country_code' => 'US',
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'lease_id_prefix' => 'LSE',
                'invoice_id_prefix' => 'INV',
                'invoice_pdf_enabled' => true,
            ])
            ->assertRedirect(route('settings.general.edit'));

        expect(Setting::get('site_name'))->toBe('My Property Mgmt');
        expect(Setting::get('country_code'))->toBe('US');
        expect(Setting::get('locale'))->toBe('en');
        expect(Setting::get('currency'))->toBe('USD');
        expect(Setting::get('timezone'))->toBe('America/New_York');
        expect(Setting::get('lease_id_prefix'))->toBe('LSE');
        expect(Setting::get('invoice_id_prefix'))->toBe('INV');
        expect(Setting::get('invoice_pdf_enabled'))->toBeTrue();
    });

    it('normalizes and stores supported currencies', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'currency' => ' usd ',
                'supported_currencies' => [' idr ', 'usd'],
            ])
            ->assertRedirect();

        expect(Setting::get('currency'))->toBe('USD')
            ->and(Setting::get('supported_currencies'))->toBe(['IDR', 'USD']);
    });

    it('rejects case-insensitive duplicate supported currencies', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'currency' => 'IDR',
                'supported_currencies' => ['idr', 'IDR'],
            ])
            ->assertSessionHasErrors('supported_currencies.1');
    });

    it('rejects empty, unknown, and default-excluding supported currencies', function (array $supported, string $error): void {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'currency' => 'USD',
                'supported_currencies' => $supported,
            ])
            ->assertSessionHasErrors($error);
    })->with([
        'empty' => [[], 'supported_currencies'],
        'unknown' => [['USD', 'ZZZ'], 'supported_currencies.1'],
        'default excluded' => [['IDR'], 'supported_currencies'],
    ]);

    it('changes the legacy fallback with the new default currency', function () {
        $owner = User::factory()->owner()->create();
        Setting::set('currency', 'IDR');

        $this->actingAs($owner)
            ->patch(route('settings.general.update'), ['currency' => 'USD'])
            ->assertRedirect();

        expect(Setting::get('currency'))->toBe('USD')
            ->and(Setting::get('supported_currencies'))->toBeNull();
    });

    it('warns when removing a currency used by active rates', function () {
        $owner = User::factory()->owner()->create();
        $unit = Unit::factory()->create();
        Setting::set('currency', 'IDR');
        Setting::set('supported_currencies', ['IDR', 'USD']);

        $this->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'currency' => 'USD',
                'supported_currencies' => ['USD'],
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'warning');

        expect($unit->rates()->where('currency', 'IDR')->where('is_active', true)->exists())->toBeTrue();
    });

    it('validates country_code is 2 uppercase letters', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.general.edit'))->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'site_name' => 'OpenKOS',
                'country_code' => 'USA',
                'locale' => 'id',
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'lease_id_prefix' => 'LSX',
                'invoice_id_prefix' => 'INV',
            ])
            ->assertSessionHasErrors(['country_code']);
    });

    it('validates currency is 3 uppercase letters', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.general.edit'))->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'site_name' => 'OpenKOS',
                'country_code' => 'ID',
                'locale' => 'id',
                'currency' => 'INR',
                'timezone' => 'Asia/Jakarta',
                'lease_id_prefix' => 'LSX',
                'invoice_id_prefix' => 'INV',
            ])
            ->assertSessionDoesntHaveErrors(['currency']);
    });

    it('validates timezone is valid', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.general.edit'))->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'site_name' => 'OpenKOS',
                'country_code' => 'ID',
                'locale' => 'id',
                'currency' => 'IDR',
                'timezone' => 'Invalid/Timezone',
                'lease_id_prefix' => 'LSX',
                'invoice_id_prefix' => 'INV',
            ])
            ->assertSessionHasErrors(['timezone']);
    });

    it('validates invoice_id_prefix is uppercase', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.general.edit'))->actingAs($owner)
            ->patch(route('settings.general.update'), [
                'site_name' => 'OpenKOS',
                'country_code' => 'ID',
                'locale' => 'id',
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'lease_id_prefix' => 'LSX',
                'invoice_id_prefix' => 'inv',
            ])
            ->assertSessionHasErrors(['invoice_id_prefix']);
    });
});
