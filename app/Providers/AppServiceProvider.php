<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDisplayTimezone();
        $this->configureAuthEvents();
    }

    protected function configureDisplayTimezone(): void
    {
        try {
            $timezone = Setting::get('timezone');
        } catch (QueryException) {
            $timezone = null;
        }

        if (! is_string($timezone) || $timezone === '') {
            $timezone = 'UTC';
        }

        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            $timezone = 'UTC';
        }

        config(['app.display_timezone' => $timezone]);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthEvents(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->save();
        });
    }
}
