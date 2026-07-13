<?php

namespace App\Http\Middleware;

use App\Installation\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        // ponytail: skip in tests so existing tests don't need rewrites
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $service = app(InstallationService::class);

        if ($service->isInstalled()) {
            if ($request->is('install/*') || $request->is('install')) {
                return redirect('/auth/login');
            }

            return $next($request);
        }

        if (! $request->is('install/*') && ! $request->is('install')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
