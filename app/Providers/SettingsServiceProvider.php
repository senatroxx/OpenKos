<?php

namespace App\Providers;

use App\Services\Settings\PlatformSettingsStore;
use App\Services\Settings\SettingManager;
use Illuminate\Support\ServiceProvider;
use OpenKOS\Core\Contracts\SettingsStore;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SettingManager::class);
        $this->app->singleton(SettingsStore::class, PlatformSettingsStore::class);
    }
}
