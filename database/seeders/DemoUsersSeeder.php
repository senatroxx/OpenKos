<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RecommendedRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoUsersSeeder extends Seeder
{
    private const STAFF_EMAIL = 'demo.staff@openkos.com';

    private const TENANT_EMAIL = 'demo.tenant@openkos.com';

    private const PASSWORD = 'password';

    public function run(): void
    {
        DB::transaction(function (): void {
            $staffRole = $this->ensureStaffRole();
            $staff = $this->upsertUser('Demo Staff', self::STAFF_EMAIL);
            $staff->syncRoles([$staffRole->name]);
            $staff->properties()->syncWithoutDetaching(
                Property::query()
                    ->where('slug', 'like', 'ope-184-demo-%')
                    ->pluck('id')
                    ->all(),
            );

            $tenantUser = $this->upsertUser('Demo Tenant', self::TENANT_EMAIL);
            $tenantUser->syncRoles([]);

            $tenant = Tenant::query()
                ->where('id_card_number', '3273010203040004')
                ->firstOrFail();
            $linkedTenant = Tenant::query()
                ->where('user_id', $tenantUser->id)
                ->whereKeyNot($tenant->id)
                ->first();

            if ($tenant->user_id !== null && $tenant->user_id !== $tenantUser->id) {
                throw new RuntimeException('The demo tenant fixture is already linked to another user.');
            }

            $linkedTenant?->forceFill(['user_id' => null])->saveQuietly();
            $tenant->forceFill(['user_id' => $tenantUser->id])->saveQuietly();
        });
    }

    private function upsertUser(string $name, string $email): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'is_active' => true,
            'invited_at' => null,
            'last_login_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user->refresh();
    }

    private function ensureStaffRole(): RoleModel
    {
        $definition = collect(RecommendedRoles::all())->firstWhere('name', 'staff');

        if (! $definition) {
            throw new RuntimeException('Missing recommended role definition for [staff].');
        }

        $role = RoleModel::query()->firstOrNew([
            'name' => 'staff',
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

        return $role->refresh();
    }
}
