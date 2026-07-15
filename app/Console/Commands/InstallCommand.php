<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class InstallCommand extends Command
{
    protected $signature = 'app:install {--fresh : Wipe all tables before migrating}';

    protected $description = 'Setup the initial owner account for OpenKOS';

    public function handle(): int
    {
        $this->setupEnvironment();

        $migrateArgs = ['--force' => true, '--quiet' => true];

        $exitCode = $this->option('fresh')
            ? $this->call('migrate:fresh', $migrateArgs)
            : $this->call('migrate', $migrateArgs);

        if ($exitCode !== 0) {
            return $exitCode;
        }

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

        $appUrl = $this->ask('What is the application URL?', 'http://localhost');

        $timezone = $this->ask(
            'What timezone should be used?',
            $country === 'ID' ? 'Asia/Jakarta' : 'UTC',
        );

        $name = $this->ask('What is the owner\'s name?');

        $email = $this->ask('What is the owner\'s email address?');

        do {
            $password = $this->secret('Choose a password for the owner account');
            $confirm = $this->secret('Confirm the password');

            if ($password !== $confirm) {
                $this->error('Passwords do not match. Please try again.');
            }
        } while ($password !== $confirm);

        $this->call('db:seed', [
            '--class' => 'Database\Seeders\RoleAndPermissionSeeder',
            '--force' => true,
        ]);

        Setting::set('site_name', $siteName);
        Setting::set('country_code', $country);
        Setting::set('locale', 'en');
        Setting::set('currency', $country === 'ID' ? 'IDR' : 'USD');
        Setting::set('timezone', $timezone);

        $this->info('Locale set to en. Internationalization (i18n) is planned for a future release.');

        $this->updateEnv([
            'APP_NAME' => $siteName,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
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

    protected function updateEnv(array $values): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $env = File::get($envPath);

        foreach ($values as $key => $value) {
            $escaped = str_replace('"', '\"', $value);
            $quoted = str_contains($value, ' ') ? "\"{$escaped}\"" : $escaped;
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            $replacement = $key.'='.$quoted;

            if (preg_match($pattern, $env)) {
                $env = preg_replace($pattern, addcslashes($replacement, '\\$'), $env);
            } else {
                $env .= PHP_EOL.$replacement;
            }
        }

        File::put($envPath, $env);
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

            $this->reloadDatabaseConfig($envPath);
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

    protected function reloadDatabaseConfig(string $envPath): void
    {
        $values = [];

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value);
            }
        }

        $connection = $values['DB_CONNECTION'] ?? config('database.default');

        config(['database.default' => $connection]);
        config(["database.connections.{$connection}.host" => $values['DB_HOST'] ?? '']);
        config(["database.connections.{$connection}.port" => $values['DB_PORT'] ?? '']);
        config(["database.connections.{$connection}.database" => $values['DB_DATABASE'] ?? '']);
        config(["database.connections.{$connection}.username" => $values['DB_USERNAME'] ?? '']);
        config(["database.connections.{$connection}.password" => $values['DB_PASSWORD'] ?? '']);

        DB::purge($connection);
    }
}
