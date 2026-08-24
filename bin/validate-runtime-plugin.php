<?php

use App\Services\Platform\RuntimePluginArtifactValidator;
use Illuminate\Container\Container;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->bootstrapWith([
    LoadEnvironmentVariables::class,
    LoadConfiguration::class,
    HandleExceptions::class,
    RegisterFacades::class,
    SetRequestForConsole::class,
    RegisterProviders::class,
]);

if (! $app instanceof Container) {
    fwrite(STDERR, "Could not bootstrap the validation process.\n");
    exit(1);
}

$runtimeConfig = json_decode($argv[3] ?? '', true);
$app->make('config')->set('platform.runtime', is_array($runtimeConfig) ? $runtimeConfig : []);
$app->make('config')->set('platform.version', $argv[4] ?? '0.2.0');

try {
    $metadata = app(RuntimePluginArtifactValidator::class)->validate(
        $argv[1] ?? '',
        ($argv[2] ?? '') !== '' ? $argv[2] : null,
    );
    fwrite(STDOUT, json_encode($metadata, JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
