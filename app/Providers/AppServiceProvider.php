<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsAppManager;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppManager::class);

        // Bind PHP booleans as 'true'/'false' on Postgres instead of 1/0 (see the class).
        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEvents();
        $this->configureMail();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
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
            /** @var User $event->user */
            $event->user->forceFill(['last_login_at' => now()])->save();
        });
    }

    protected function configureMail(): void
    {
        try {
            $setting = Setting::first();
        } catch (QueryException) {
            return;
        }

        if (! $setting?->mail_host) {
            return;
        }

        config()->set('mail.mailers.smtp.host', $setting->mail_host);
        config()->set('mail.mailers.smtp.port', $setting->mail_port);
        config()->set('mail.mailers.smtp.username', $setting->mail_username);
        config()->set('mail.mailers.smtp.password', $setting->mail_password);
        config()->set('mail.mailers.smtp.encryption', $setting->mail_encryption === 'null' ? null : $setting->mail_encryption);

        if ($setting->mail_from_address) {
            config()->set('mail.from.address', $setting->mail_from_address);
            config()->set('mail.from.name', $setting->mail_from_name);
        }

        config()->set('mail.default', 'smtp');
    }
}
