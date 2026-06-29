<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'budi@openkos.com'],
            [
                'name' => 'Budi',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        if (! $user->hasRole(Role::Owner->value)) {
            $user->assignRole(Role::Owner->value);
        }
    }
}
