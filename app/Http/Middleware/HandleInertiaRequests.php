<?php

namespace App\Http\Middleware;

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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'setting' => fn () => Setting::get(),
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->getRoleNames()->first(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'platform' => fn () => [
                'navigation' => OpenKOS::navigation()->toArray(),
                'workspaces' => OpenKOS::workspaces()->toArray(),
                'settings' => OpenKOS::settings()->toArray(),
                'dashboard' => OpenKOS::dashboard()->toArray(),
            ],
        ];
    }
}
