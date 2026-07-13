<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceSafeEnvDuringInstall
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if (! file_exists(storage_path('installed'))) {
            config(['session.driver' => 'file']);
            config(['cache.default' => 'file']);
            config(['queue.default' => 'sync']);
        }

        return $next($request);
    }
}
