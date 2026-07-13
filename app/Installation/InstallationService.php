<?php

namespace App\Installation;

use App\Enums\Role;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InstallationService
{
    private const STATE_KEY = 'installation_state';

    private const INSTALLED_KEY = 'installed';

    public function isInstalled(): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        return (bool) Setting::get(self::INSTALLED_KEY);
    }

    public function state(): InstallationState
    {
        if (! Schema::hasTable('settings')) {
            return InstallationState::Welcome;
        }

        return InstallationState::tryFrom(Setting::get(self::STATE_KEY))
            ?? InstallationState::Welcome;
    }

    public function setState(InstallationState $state): void
    {
        Setting::set(self::STATE_KEY, $state->value);
    }

    public function advance(): InstallationState
    {
        $states = InstallationState::all();
        $current = $this->state();
        $index = array_search($current, $states);

        if ($index !== false && isset($states[$index + 1])) {
            $next = $states[$index + 1];
            $this->setState($next);

            return $next;
        }

        return $current;
    }

    public function completedSteps(): array
    {
        $states = InstallationState::all();
        $current = $this->state();
        $currentIndex = array_search($current, $states);

        $completed = [];
        foreach ($states as $i => $state) {
            $completed[$state->value] = $state === InstallationState::Completed
                ? $i === $currentIndex
                : $i < $currentIndex;
        }

        return $completed;
    }

    public function requirements(): array
    {
        return [
            'php_version' => [
                'label' => 'PHP Version (>= 8.3)',
                'satisfied' => PHP_VERSION_ID >= 80300,
                'value' => PHP_VERSION,
            ],
            'bcmath' => [
                'label' => 'BCMath Extension',
                'satisfied' => extension_loaded('bcmath'),
            ],
            'ctype' => [
                'label' => 'Ctype Extension',
                'satisfied' => extension_loaded('ctype'),
            ],
            'json' => [
                'label' => 'JSON Extension',
                'satisfied' => extension_loaded('json'),
            ],
            'mbstring' => [
                'label' => 'Mbstring Extension',
                'satisfied' => extension_loaded('mbstring'),
            ],
            'openssl' => [
                'label' => 'OpenSSL Extension',
                'satisfied' => extension_loaded('openssl'),
            ],
            'pdo' => [
                'label' => 'PDO Extension',
                'satisfied' => extension_loaded('pdo'),
            ],
            'tokenizer' => [
                'label' => 'Tokenizer Extension',
                'satisfied' => extension_loaded('tokenizer'),
            ],
            'xml' => [
                'label' => 'XML Extension',
                'satisfied' => extension_loaded('xml'),
            ],
            'storage_writable' => [
                'label' => 'Storage Directory Writable',
                'satisfied' => File::isWritable(storage_path()),
            ],
        ];
    }

    public function allRequirementsMet(): bool
    {
        return collect($this->requirements())->every(fn ($req) => $req['satisfied']);
    }

    public function testDatabaseConnection(?string $connection = null): array
    {
        $connection ??= config('database.default');

        try {
            DB::connection($connection)->getPdo();

            return ['success' => true, 'message' => 'Connection successful.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function runInstallation(): array
    {
        try {
            $exitCode = Artisan::call('key:generate', ['--force' => true]);

            if ($exitCode !== 0) {
                return ['success' => false, 'message' => 'Failed to generate application key.'];
            }

            $exitCode = Artisan::call('migrate', ['--force' => true]);

            if ($exitCode !== 0) {
                return ['success' => false, 'message' => 'Migration failed: '.Artisan::output()];
            }

            $exitCode = Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\RoleAndPermissionSeeder',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                return ['success' => false, 'message' => 'Seeding failed.'];
            }

            $this->createStorageLink();

            return ['success' => true, 'message' => 'Installation completed successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createOwner(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $role = \Spatie\Permission\Models\Role::findOrCreate(Role::Owner->value);
        $user->assignRole($role);

        return $user;
    }

    public function setupOrganization(array $data): void
    {
        if (isset($data['site_name'])) {
            Setting::set('site_name', $data['site_name']);
        }

        if (isset($data['country_code'])) {
            Setting::set('country_code', $data['country_code']);
        }

        if (isset($data['timezone'])) {
            Setting::set('timezone', $data['timezone']);
        }

        if (isset($data['currency'])) {
            Setting::set('currency', $data['currency']);
        }

        if (isset($data['locale'])) {
            Setting::set('locale', $data['locale']);
        }
    }

    public function markCompleted(): void
    {
        Setting::set(self::INSTALLED_KEY, true);
        $this->setState(InstallationState::Completed);
    }

    private function createStorageLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (! File::exists($link)) {
            File::link($target, $link);
        }
    }
}
