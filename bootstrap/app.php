<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceSafeEnvDuringInstall;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfNotInstalled;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [
            ForceSafeEnvDuringInstall::class,
            RedirectIfNotInstalled::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $e) {
            if ($e->getCode() === '23503') {
                if (request()->isMethod('delete')) {
                    Inertia::flash('toast', ['type' => 'error', 'message' => __('This record cannot be deleted because other records depend on it.')]);
                }

                return redirect()->back();
            }
        });
    })->create();

// ponytail: run without .env on fresh install — set essential
// env vars here so bootstrappers (LoadConfiguration, etc.)
// find them before any middleware runs.
$envPath = dirname(__DIR__).'/.env';
if (! file_exists($envPath)) {
    $key = 'base64:'.base64_encode(random_bytes(32));
    file_put_contents($envPath, "APP_KEY={$key}\n");
    putenv("APP_KEY={$key}");
    putenv('SESSION_DRIVER=file');
    putenv('CACHE_STORE=file');
    putenv('QUEUE_CONNECTION=sync');
    putenv('DB_CONNECTION=sqlite');
}

return $app;
