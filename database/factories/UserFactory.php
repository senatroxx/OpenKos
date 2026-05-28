<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'invited_at' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function owner(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole(SpatieRole::findOrCreate(Role::Owner->value));
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole(SpatieRole::findOrCreate(Role::Admin->value));
        });
    }

    public function staff(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole(SpatieRole::findOrCreate(Role::Staff->value));
        });
    }
}
