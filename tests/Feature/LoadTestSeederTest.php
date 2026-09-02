<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LoadTestSeeder;
use Illuminate\Support\Facades\Hash;

function loadTestFixtureConfig(bool $enabled = true): array
{
    return [
        'enabled' => $enabled,
        'users' => [
            'owner' => ['name' => 'Load Test Owner', 'email' => 'owner.load-test@example.com', 'password' => 'owner-secret'],
            'admin' => ['name' => 'Load Test Manager', 'email' => 'manager.load-test@example.com', 'password' => 'manager-secret'],
            'staff' => ['name' => 'Load Test Staff', 'email' => 'staff.load-test@example.com', 'password' => 'staff-secret'],
            'tenant' => ['name' => 'Load Test Tenant', 'email' => 'tenant.load-test@example.com', 'password' => 'tenant-secret'],
        ],
    ];
}

test('requires explicit opt-in before seeding fixtures', function () {
    config(['load-test.fixtures' => loadTestFixtureConfig(false)]);

    expect(fn () => $this->seed(LoadTestSeeder::class))
        ->toThrow(RuntimeException::class, 'LOAD_TEST_FIXTURES_ENABLED=true');

    expect(User::query()->count())->toBe(0);
    expect(Role::query()->count())->toBe(0);
});

test('validates all credentials before writing fixtures', function () {
    $config = loadTestFixtureConfig();
    $config['users']['staff']['password'] = '';
    config(['load-test.fixtures' => $config]);

    expect(fn () => $this->seed(LoadTestSeeder::class))
        ->toThrow(InvalidArgumentException::class, 'LOAD_TEST_STAFF_PASSWORD is required');

    expect(User::query()->count())->toBe(0);
    expect(Role::query()->count())->toBe(0);
});

test('seeds idempotent owner manager staff and tenant fixtures', function () {
    config(['load-test.fixtures' => loadTestFixtureConfig()]);

    $this->seed(LoadTestSeeder::class);
    $this->seed(LoadTestSeeder::class);

    expect(User::query()->whereIn('email', [
        'owner.load-test@example.com',
        'manager.load-test@example.com',
        'staff.load-test@example.com',
        'tenant.load-test@example.com',
    ])->count())->toBe(4);

    $owner = User::whereEmail('owner.load-test@example.com')->firstOrFail();
    $manager = User::whereEmail('manager.load-test@example.com')->firstOrFail();
    $staff = User::whereEmail('staff.load-test@example.com')->firstOrFail();
    $tenantUser = User::whereEmail('tenant.load-test@example.com')->firstOrFail();

    expect($owner->hasRole('owner'))->toBeTrue()
        ->and($manager->hasRole('admin'))->toBeTrue()
        ->and($staff->hasRole('staff'))->toBeTrue()
        ->and($tenantUser->roles)->toBeEmpty()
        ->and(Hash::check('owner-secret', $owner->password))->toBeTrue()
        ->and($tenantUser->tenant)->not->toBeNull();

    expect(Tenant::query()->where('user_id', $tenantUser->id)->count())->toBe(1);
});

test('tenant fixture reaches the tenant portal without a lease', function () {
    config(['load-test.fixtures' => loadTestFixtureConfig()]);
    $this->seed(LoadTestSeeder::class);

    $tenantUser = User::whereEmail('tenant.load-test@example.com')->firstOrFail();

    $this->actingAs($tenantUser)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/dashboard')
            ->where('tenant.name', 'Load Test Tenant')
            ->where('lease', null));
});

test('rejects production fixture seeding', function () {
    $this->app->instance('env', 'production');
    config(['load-test.fixtures' => loadTestFixtureConfig()]);

    expect(fn () => app(LoadTestSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'cannot be seeded in production');

    expect(User::query()->count())->toBe(0);
});
