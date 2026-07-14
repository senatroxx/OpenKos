<?php

namespace App\Http\Controllers\Installation;

use App\Enums\Installation\InstallationState;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\InstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Platform\Facades\OpenKOS;

class InstallationController extends Controller
{
    public function __construct(
        private readonly InstallationService $installer,
    ) {}

    public function index(): RedirectResponse
    {
        $state = $this->installer->state();

        if ($state === InstallationState::Completed) {
            return redirect('/auth/login');
        }

        return redirect()->route('install.'.$state->value);
    }

    public function welcome(): Response|RedirectResponse
    {
        if ($this->installer->state() !== InstallationState::Welcome) {
            return redirect()->route('install.'.$this->installer->state()->value);
        }

        return Inertia::render('install/welcome', [
            'version' => config('platform.version', '0.1.0'),
            'steps' => $this->installer->completedSteps(),
        ]);
    }

    public function start(): RedirectResponse
    {
        $this->installer->setState(InstallationState::Requirements);

        return redirect()->route('install.requirements');
    }

    public function requirements(): Response|RedirectResponse
    {
        if ($this->installer->state() === InstallationState::Welcome) {
            return redirect()->route('install.welcome');
        }

        return Inertia::render('install/requirements', [
            'requirements' => $this->installer->requirements(),
            'allMet' => $this->installer->allRequirementsMet(),
            'steps' => $this->installer->completedSteps(),
        ]);
    }

    public function checkRequirements(): RedirectResponse
    {
        if (! $this->installer->allRequirementsMet()) {
            return back()->withErrors(['requirements' => 'All requirements must be met before proceeding.']);
        }

        $this->installer->advance();

        return redirect()->route('install.database');
    }

    public function database(): Response|RedirectResponse
    {
        if ($this->installer->state() !== InstallationState::Database) {
            return redirect()->route('install.'.$this->installer->state()->value);
        }

        return Inertia::render('install/database', [
            'connection' => config('database.default'),
            'steps' => $this->installer->completedSteps(),
        ]);
    }

    public function configureDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'connection' => ['required', 'string', 'in:mysql,pgsql,sqlite'],
            'host' => ['required_if:connection,mysql,pgsql', 'nullable', 'string'],
            'port' => ['required_if:connection,mysql,pgsql', 'nullable', 'string'],
            'database' => ['required', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $connection = $data['connection'];

        $this->updateDatabaseConfig($connection, $data);

        $result = $this->installer->testDatabaseConnection($connection);

        if (! $result['success']) {
            return back()->withErrors(['connection' => $result['message']]);
        }

        $this->installer->advance();

        return redirect()->route('install.admin');
    }

    public function admin(): Response|RedirectResponse
    {
        if ($this->installer->state() !== InstallationState::Admin) {
            return redirect()->route('install.'.$this->installer->state()->value);
        }

        return Inertia::render('install/admin', [
            'steps' => $this->installer->completedSteps(),
        ]);
    }

    public function createAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->installer->saveAdminData($data['name'], $data['email'], $data['password']);
        $this->installer->advance();

        return redirect()->route('install.organization');
    }

    public function organization(): Response|RedirectResponse
    {
        if ($this->installer->state() !== InstallationState::Organization) {
            return redirect()->route('install.'.$this->installer->state()->value);
        }

        $whatsappDrivers = collect(config('services.whatsapp.drivers'))
            ->map(fn ($d, $name) => ['name' => $name, 'label' => $d['label']])
            ->values();

        return Inertia::render('install/organization', [
            'steps' => $this->installer->completedSteps(),
            'pluginSteps' => OpenKOS::installationSteps()->toArray(),
            'whatsappDrivers' => $whatsappDrivers,
        ]);
    }

    public function setupOrganization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'string', 'size:2'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'string', 'max:5'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_driver' => ['nullable', 'string'],
        ]);

        $this->installer->saveOrgData($data);
        $this->installer->advance();

        return redirect()->route('install.installing');
    }

    public function installing(): Response|RedirectResponse
    {
        if ($this->installer->state() !== InstallationState::Installing) {
            return redirect()->route('install.'.$this->installer->state()->value);
        }

        return Inertia::render('install/installing', [
            'steps' => $this->installer->completedSteps(),
        ]);
    }

    public function runInstall(): RedirectResponse
    {
        $result = $this->installer->runInstallation();

        if (! $result['success']) {
            return back()->withErrors(['install' => $result['message']]);
        }

        $this->installer->markCompleted();

        return redirect()->route('install.finished');
    }

    public function finished(): Response
    {
        return Inertia::render('install/finished', [
            'steps' => $this->installer->completedSteps(),
            'setting' => ['site_name' => Setting::get('site_name') ?? config('app.name')],
        ]);
    }

    private function updateDatabaseConfig(string $connection, array $data): void
    {
        $config = [
            'host' => $data['host'] ?? '127.0.0.1',
            'port' => $data['port'] ?? ($connection === 'pgsql' ? '5432' : '3306'),
            'database' => $data['database'],
            'username' => $data['username'] ?? 'root',
            'password' => $data['password'] ?? '',
        ];

        foreach ($config as $key => $value) {
            config(["database.connections.{$connection}.{$key}" => $value]);
        }

        config(['database.default' => $connection]);
        DB::purge($connection);

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $env = File::get($envPath);
        $replacements = array_merge(
            ['DB_CONNECTION' => $connection],
            array_combine(
                array_map(fn ($k) => 'DB_'.strtoupper($k), array_keys($config)),
                $config,
            ),
        );

        foreach ($replacements as $key => $value) {
            $escaped = str_replace('"', '\"', $value);
            $env = preg_replace_callback(
                "/^{$key}=.*/m",
                fn () => "{$key}=\"{$escaped}\"",
                $env,
            );
        }

        File::put($envPath, $env);
    }
}
