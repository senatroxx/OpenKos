<?php

use App\Enums\Installation\InstallationState;
use App\Services\InstallationService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $file = storage_path('installed');
    if (file_exists($file)) {
        unlink($file);
    }
});

test('installation service detects uninstalled state', function () {
    expect(app(InstallationService::class)->isInstalled())->toBeFalse();
});

test('welcome page is shown on first visit', function () {
    $this->get('/install')
        ->assertRedirect('/install/welcome');

    $this->get('/install/welcome')
        ->assertInertia(fn (Assert $page) => $page
            ->component('install/welcome')
            ->has('version')
            ->has('steps')
        );
});

test('starting installation advances to requirements', function () {
    $this->post('/install/welcome')
        ->assertRedirect('/install/requirements');

    expect(app(InstallationService::class)->state())->toBe(InstallationState::Requirements);
});

test('requirements page redirects to welcome when state is welcome', function () {
    $this->get('/install/requirements')
        ->assertRedirect('/install/welcome');
});

test('requirements page renders when state is requirements', function () {
    $this->session(['installation_state' => InstallationState::Requirements->value]);

    $this->get('/install/requirements')
        ->assertInertia(fn (Assert $page) => $page
            ->component('install/requirements')
            ->has('requirements')
            ->has('allMet')
        );
});

test('database page renders when state is database', function () {
    $this->session(['installation_state' => InstallationState::Database->value]);

    $this->get('/install/database')
        ->assertInertia(fn (Assert $page) => $page->component('install/database'));
});

test('admin page validates input', function () {
    $this->session(['installation_state' => InstallationState::Admin->value]);

    $this->get('/install/admin')
        ->assertInertia(fn (Assert $page) => $page->component('install/admin'));

    $this->post('/install/admin', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['name', 'email', 'password']);
});

test('organization setup saves settings', function () {
    $this->session(['installation_state' => InstallationState::Organization->value]);

    $this->get('/install/organization')
        ->assertInertia(fn (Assert $page) => $page->component('install/organization'));

    $this->post('/install/organization', [
        'property_name' => 'My Test Boarding',
        'country_code' => 'ID',
        'currency' => 'IDR',
    ])->assertRedirect('/install/notifications');

    expect(session()->get('org_data.property_name'))->toBe('My Test Boarding');
    expect(session()->get('org_data.country_code'))->toBe('ID');
});

test('finished page renders when state is completed', function () {
    $this->session(['installation_state' => InstallationState::Completed->value]);

    $this->get('/install/finished')
        ->assertInertia(fn (Assert $page) => $page->component('install/finished'));
});

test('completed steps only marks previous steps as done', function () {
    $service = app(InstallationService::class);
    $service->setState(InstallationState::Database);

    $steps = $service->completedSteps();

    expect($steps['welcome'])->toBeTrue();
    expect($steps['requirements'])->toBeTrue();
    expect($steps['database'])->toBeFalse();
    expect($steps['admin'])->toBeNull();
});
