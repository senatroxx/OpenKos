<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class InstallCommand extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Setup the initial owner account for OpenKOS';

    public function handle(): int
    {
        $this->setupEnvironment();

        $this->call('migrate', ['--force' => true]);

        if (User::exists()) {
            $this->error('Users already exist. Installation has already been completed.');

            return self::FAILURE;
        }

        $siteName = $this->ask('What is the name of this installation?', 'OpenKOS');

        $country = $this->choice(
            'What country is this installation for?',
            ['ID' => 'Indonesia', 'XX' => 'Other'],
            'ID',
        );

        $name = $this->ask('What is the owner\'s name?');

        $email = $this->ask('What is the owner\'s email address?');

        $password = $this->secret('Choose a password for the owner account');

        $this->call('db:seed', [
            '--class' => 'Database\Seeders\RoleAndPermissionSeeder',
            '--force' => true,
        ]);

        Setting::create([
            'site_name' => $siteName,
            'country_code' => $country,
            'locale' => $country === 'ID' ? 'id' : config('app.locale'),
            'currency' => $country === 'ID' ? 'IDR' : 'USD',
            'timezone' => $country === 'ID' ? 'Asia/Jakarta' : config('app.timezone'),
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

    protected function setupEnvironment(): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (! File::exists($envPath) && File::exists($examplePath)) {
            File::copy($examplePath, $envPath);
            $this->info('Created .env file from .env.example');

            $this->call('key:generate', ['--force' => true]);

            $this->setupDatabase($envPath);
        }
    }

    protected function setupDatabase(string $envPath): void
    {
        $connection = $this->choice(
            'Which database driver?',
            ['pgsql' => 'PostgreSQL', 'mysql' => 'MySQL', 'sqlite' => 'SQLite'],
            'pgsql',
        );

        $env = File::get($envPath);

        if ($connection === 'sqlite') {
            $env = preg_replace('/^DB_CONNECTION=.*/m', 'DB_CONNECTION=sqlite', $env);
            $env = preg_replace('/^DB_HOST=.*/m', '# DB_HOST=127.0.0.1', $env);
            $env = preg_replace('/^DB_PORT=.*/m', '# DB_PORT=5432', $env);
            $env = preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE='.base_path('database/database.sqlite'), $env);
            $env = preg_replace('/^DB_USERNAME=.*/m', '# DB_USERNAME=', $env);
            $env = preg_replace('/^DB_PASSWORD=.*/m', '# DB_PASSWORD=', $env);

            File::put($envPath, $env);
            $this->info('Database configured for SQLite.');

            return;
        }

        $host = $this->ask('Database host?', '127.0.0.1');
        $port = $this->ask('Database port?', $connection === 'pgsql' ? '5432' : '3306');
        $database = $this->ask('Database name?', 'openkos');
        $username = $this->ask('Database username?', 'root');
        $password = $this->secret('Database password?') ?? '';

        $env = preg_replace('/^DB_CONNECTION=.*/m', "DB_CONNECTION={$connection}", $env);
        $env = preg_replace('/^DB_HOST=.*/m', "DB_HOST={$host}", $env);
        $env = preg_replace('/^DB_PORT=.*/m', "DB_PORT={$port}", $env);
        $env = preg_replace('/^DB_DATABASE=.*/m', "DB_DATABASE={$database}", $env);
        $env = preg_replace('/^DB_USERNAME=.*/m', "DB_USERNAME={$username}", $env);
        $env = preg_replace('/^DB_PASSWORD=.*/m', "DB_PASSWORD={$password}", $env);

        File::put($envPath, $env);
        $this->info("Database configured for {$connection}.");
    }
}
