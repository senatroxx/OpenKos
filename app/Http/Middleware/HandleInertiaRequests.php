<?php

namespace App\Http\Middleware;

use App\Installation\InstallationService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;
use OpenKOS\Platform\Facades\OpenKOS;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $installed = app(InstallationService::class)->isInstalled();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'setting' => $installed ? fn () => Setting::get() : fn () => collect(),
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->getRoleNames()->first(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'platform' => $installed ? fn () => [
                'navigation' => OpenKOS::navigation()->toArray(),
                'workspaces' => OpenKOS::workspaces()->toArray(),
                'settings' => OpenKOS::settings()->toArray(),
                'dashboard' => OpenKOS::dashboard()->toArray(),
            ] : [],
        ];
    }
}
