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
        $user = User::create([
            'name' => 'Budi',
            'email' => 'budi@openkos.com',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole(Role::Owner->value);
    }
}
