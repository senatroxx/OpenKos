<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Models\Setting;
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
    public function register(): void
    {
        $this->app->singleton(WhatsAppManager::class);

        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEvents();
        $this->configureMail();
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

    protected function configureMail(): void
    {
        try {
            $host = Setting::get('mail_host');
        } catch (QueryException) {
            return;
        }

        if (! $host) {
            return;
        }

        config()->set('mail.mailers.smtp.host', $host);
        config()->set('mail.mailers.smtp.port', Setting::get('mail_port'));
        config()->set('mail.mailers.smtp.username', Setting::get('mail_username'));
        config()->set('mail.mailers.smtp.password', Setting::get('mail_password'));
        config()->set('mail.mailers.smtp.encryption', Setting::get('mail_encryption'));

        if ($fromAddress = Setting::get('mail_from_address')) {
            config()->set('mail.from.address', $fromAddress);
            config()->set('mail.from.name', Setting::get('mail_from_name'));
        }

        config()->set('mail.default', 'smtp');
    }
}
