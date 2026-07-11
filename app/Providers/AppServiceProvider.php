<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Setting;
use App\Services\Settings\SettingManager;
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
use OpenKOS\Notification\Listeners\InvoiceReminderListener;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingManager::class);
        $this->app->singleton(WhatsAppManager::class);

        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEvents();
        $this->configureMail();
        $this->configureReminderEvents();
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
            $config = Setting::get('mail_config');
        } catch (QueryException) {
            return;
        }

        if (! ($config['host'] ?? null)) {
            return;
        }

        config()->set('mail.mailers.smtp.host', $config['host'] ?? '');
        config()->set('mail.mailers.smtp.port', $config['port'] ?? 587);
        config()->set('mail.mailers.smtp.username', $config['username'] ?? '');
        config()->set('mail.mailers.smtp.password', $config['password'] ?? '');
        $encryption = $config['encryption'] ?? null;
        if ($encryption === 'null') {
            $encryption = null;
        }
        config()->set('mail.mailers.smtp.encryption', $encryption);

        if ($fromAddress = $config['from_address'] ?? null) {
            config()->set('mail.from.address', $fromAddress);
            config()->set('mail.from.name', $config['from_name'] ?? '');
        }

        config()->set('mail.default', 'smtp');
    }

    protected function configureReminderEvents(): void
    {
        Event::listen(
            InvoiceReminderDispatched::class,
            InvoiceReminderListener::class,
        );
    }
}
