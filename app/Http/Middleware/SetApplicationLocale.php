<?php

namespace App\Http\Middleware;

use App\Services\Localization\ApplicationLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    public function __construct(private ApplicationLocale $locale) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->locale->apply();

        return $next($request);
    }
}
