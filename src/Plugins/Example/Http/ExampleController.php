<?php

namespace OpenKOS\Plugins\Example\Http;

use Illuminate\Http\JsonResponse;

/**
 * Invokable controller for the example plugin's route. Using a controller
 * (rather than a route closure) keeps the route compatible with
 * `php artisan route:cache`.
 */
class ExampleController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'plugin' => 'openkos/example',
            'message' => 'Route provided by the example plugin.',
        ]);
    }
}
