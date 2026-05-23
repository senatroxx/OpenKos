<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class InstallCommand extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Setup the initial owner account for OpenKOS';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        if (User::exists()) {
            $this->error('Users already exist. Installation has already been completed.');

            return self::FAILURE;
        }

        $name = $this->ask('What is the owner\'s name?');

        $email = $this->ask('What is the owner\'s email address?');

        $password = $this->secret('Choose a password for the owner account');

        $this->call('db:seed', [
            '--class' => 'Database\Seeders\RoleAndPermissionSeeder',
            '--force' => true,
        ]);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $role = SpatieRole::findOrCreate(Role::Owner->value);
        $user->assignRole($role);

        $this->info('OpenKOS has been installed successfully!');
        $this->warn("Owner account created: {$name} ({$email})");

        return self::SUCCESS;
    }
}
