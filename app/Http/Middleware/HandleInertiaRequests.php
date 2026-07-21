<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use OpenKOS\Platform\Facades\OpenKOS;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        // Use the configured site name (settings table) as the app-wide display
        // name for this request — drives the page <title> (blade + Inertia
        // suffix) and the shared `name` prop. Falls back to config/env.
        config(['app.name' => Setting::get('site_name') ?? config('app.name')]);

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Integration configs (mail_config, whatsapp_config) hold secrets — SMTP
            // password, API tokens — so they never go into the app-wide share. Their
            // own settings pages load them (masked) separately.
            'setting' => fn () => collect(Setting::get())
                ->except(['mail_config', 'whatsapp_config'])
                ->all(),
            'notificationChannels' => fn () => [
                'mail' => filled(data_get(Setting::get('mail_config'), 'host')),
                'whatsapp' => filled(Setting::get('whatsapp_driver')),
            ],
            'auth' => [
                'user' => $request->user(),
                'tenant' => fn () => $request->user()?->tenant()
                    ->select(['id', 'name'])
                    ->first(),
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
