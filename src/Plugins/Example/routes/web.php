<?php

use Illuminate\Support\Facades\Route;

/*
 * Routes shipped by the example plugin. PlatformServiceProvider loads this
 * file automatically when the plugin is enabled (convention: <plugin>/routes/
 * web.php). A real plugin would add auth/permission middleware and point a
 * registered nav item or workspace tab at these routes.
 */

Route::get('/example', fn () => response()->json([
    'plugin' => 'openkos/example',
    'message' => 'Route provided by the example plugin.',
]))->name('example.index');
