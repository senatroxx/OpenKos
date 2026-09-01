<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RecommendedRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{enabled: bool, users: array<string, array{name: string, email: string|null, password: string|null}>} $fixtures */
        $fixtures = config('load-test.fixtures');

        $this->ensureAllowed($fixtures['enabled']);
        $this->validateUsers($fixtures['users']);

        DB::transaction(function () use ($fixtures): void {
            $this->call(RoleAndPermissionSeeder::class);

            $this->upsertUser($fixtures['users']['owner'], Role::Owner->value);
            $this->ensureRecommendedRole('admin');
            $this->ensureRecommendedRole('staff');
            $this->upsertUser($fixtures['users']['admin'], 'admin');
            $this->upsertUser($fixtures['users']['staff'], 'staff');

            $tenantUser = $this->upsertUser($fixtures['users']['tenant'], null);
            $this->upsertTenant($tenantUser);
        });
    }

    private function ensureAllowed(bool $enabled): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Load-test fixtures cannot be seeded in production.');
        }

        if (! $enabled) {
            throw new RuntimeException('Set LOAD_TEST_FIXTURES_ENABLED=true before seeding load-test fixtures.');
        }
    }

    /**
     * @param  array<string, array{name: string, email: string|null, password: string|null}>  $users
     */
    private function validateUsers(array $users): void
    {
        $emails = [];

        foreach (['owner', 'admin', 'staff', 'tenant'] as $persona) {
            $user = $users[$persona] ?? null;

            if (! is_array($user)) {
                throw new InvalidArgumentException("Missing load-test configuration for [{$persona}].");
            }

            $email = $user['email'] ?? null;
            $password = $user['password'] ?? null;

            if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("LOAD_TEST_{$this->envPersona($persona)}_EMAIL must be a valid email address.");
            }

            if (! is_string($password) || trim($password) === '') {
                throw new InvalidArgumentException("LOAD_TEST_{$this->envPersona($persona)}_PASSWORD is required.");
            }

            $normalizedEmail = strtolower($email);

            if (isset($emails[$normalizedEmail])) {
                throw new InvalidArgumentException("Load-test email [{$email}] is configured for multiple personas.");
            }

            $emails[$normalizedEmail] = $persona;
        }
    }

    private function envPersona(string $persona): string
    {
        return match ($persona) {
            'admin' => 'ADMIN',
            default => strtoupper($persona),
        };
    }

    /**
     * @param  array{name: string, email: string|null, password: string|null}  $attributes
     */
    private function upsertUser(array $attributes, ?string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $attributes['email']]);
        $user->forceFill([
            'name' => $attributes['name'],
            'password' => Hash::make($attributes['password']),
            'email_verified_at' => now(),
            'is_active' => true,
            'invited_at' => null,
            'last_login_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->syncRoles($role ? [$role] : []);

        return $user;
    }

    private function ensureRecommendedRole(string $name): RoleModel
    {
        $definition = collect(RecommendedRoles::all())->firstWhere('name', $name);

        if (! $definition) {
            throw new RuntimeException("Missing recommended role definition for [{$name}].");
        }

        $role = RoleModel::query()->firstOrNew([
            'name' => $name,
            'guard_name' => 'web',
        ]);
        $role->forceFill([
            'label' => $definition['label'],
            'description' => $definition['description'],
            'color' => $definition['color'],
            'is_system' => false,
            'is_active' => true,
        ])->save();
        $role->syncPermissions($definition['permissions']);

        return $role;
    }

    private function upsertTenant(User $user): Tenant
    {
        $tenant = Tenant::withTrashed()->firstOrNew(['user_id' => $user->id]);

        if ($tenant->trashed()) {
            $tenant->restore();
        }

        $tenant->forceFill([
            'name' => $user->name,
            'phone' => null,
            'id_card_number' => 'load-test-tenant',
            'is_active' => true,
        ])->save();

        return $tenant;
    }
}
