<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use OpenKOS\Platform\Facades\OpenKOS;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    private const BRANDING_MIMES = [
        'logo' => ['image/jpeg', 'image/png', 'image/webp'],
        'favicon' => ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'],
    ];

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
        $settingsPages = array_values(array_filter(
            OpenKOS::settings()->toArray(),
            fn (array $page) => $this->canSeeSettingsPage($request, $page),
        ));

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'app' => [
                'timezone' => config('app.display_timezone', 'UTC'),
                'currency_scales' => app(MoneyConverter::class)->scales(),
            ],
            // Integration configs (mail_config, whatsapp_config, payment_gateway_config) hold secrets — SMTP
            // password, API tokens — so they never go into the app-wide share. Their
            // own settings pages load them (masked) separately.
            'setting' => fn () => [
                ...collect(Setting::get())
                    ->except([
                        'mail_config',
                        'whatsapp_config',
                        'payment_gateway_config',
                        'branding_logo_path',
                        'branding_favicon_path',
                    ])
                    ->all(),
                'currency' => app(InstallationCurrencySettings::class)->default(),
                'supported_currencies' => app(InstallationCurrencySettings::class)->supported(),
            ],
            'branding' => fn () => $this->branding(),
            'notificationChannels' => fn () => [
                'mail' => filled(data_get(Setting::effectiveMailConfig(), 'host')),
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
                'settings' => $settingsPages,
                'dashboard' => OpenKOS::dashboard()->toArray(),
            ],
        ];
    }

    /**
     * @param  array{permission?: string|null, ownerOnly?: bool}  $page
     */
    private function canSeeSettingsPage(Request $request, array $page): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if (($page['ownerOnly'] ?? true) && ! $user->isOwner()) {
            return false;
        }

        $permission = $page['permission'] ?? null;

        return $permission === null || $user->isOwner() || $user->can($permission);
    }

    /**
     * @return array{logoUrl: string, faviconUrl: string, hasCustomLogo: bool, hasCustomFavicon: bool, hasConfiguredLogo: bool, hasConfiguredFavicon: bool}
     */
    private function branding(): array
    {
        $logoPath = Setting::get('branding_logo_path');
        $faviconPath = Setting::get('branding_favicon_path');
        $hasConfiguredLogo = $this->isConfigured($logoPath);
        $hasConfiguredFavicon = $this->isConfigured($faviconPath);
        $disk = null;

        if ($hasConfiguredLogo || $hasConfiguredFavicon) {
            try {
                $disk = Storage::disk((string) config('filesystems.default', 'local'));
            } catch (\Throwable) {
                // Use bundled branding when the configured disk is unavailable.
            }
        }

        $logoVersion = $this->storedAssetVersion('logo', $logoPath, $disk);
        $faviconVersion = $this->storedAssetVersion('favicon', $faviconPath, $disk);

        return [
            'logoUrl' => $this->brandingUrl('logo', $logoVersion),
            'faviconUrl' => $this->brandingUrl('favicon', $faviconVersion),
            'hasCustomLogo' => $logoVersion !== null,
            'hasCustomFavicon' => $faviconVersion !== null,
            'hasConfiguredLogo' => $hasConfiguredLogo,
            'hasConfiguredFavicon' => $hasConfiguredFavicon,
        ];
    }

    private function brandingUrl(string $asset, ?string $version): string
    {
        return route('branding.asset', [
            'asset' => $asset,
            'v' => $version ?? 'default',
        ]);
    }

    private function storedAssetVersion(string $asset, mixed $path, ?FilesystemAdapter $disk): ?string
    {
        if ($disk === null || ! $this->isConfigured($path)) {
            return null;
        }

        try {
            if (! $disk->exists($path)) {
                return null;
            }

            $mimeType = $disk->mimeType($path);

            return is_string($mimeType) && in_array($mimeType, self::BRANDING_MIMES[$asset], true)
                ? sha1((string) $path)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isConfigured(mixed $path): bool
    {
        return is_string($path) && filled($path);
    }
}
