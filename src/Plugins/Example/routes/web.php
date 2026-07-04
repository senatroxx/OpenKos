<?php

use Illuminate\Support\Facades\Route;
use OpenKOS\Plugins\Example\Http\ExampleController;

/*
 * Routes shipped by the example plugin. PlatformServiceProvider loads this
 * file automatically when the plugin is enabled (convention: <plugin>/routes/
 * web.php). The route is gated by the plugin's own permission — the same one
 * declared in ExamplePlugin::register() and persisted by
 * `php artisan platform:permissions:sync`.
 */

Route::get('/example', ExampleController::class)
    ->middleware(['auth', 'permission:example.view'])
    ->name('example.index');
