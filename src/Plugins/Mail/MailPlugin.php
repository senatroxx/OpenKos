<?php

namespace OpenKOS\Plugins\Mail;

use App\Notifications\Drivers\LogMailDriver;
use App\Notifications\Drivers\SmtpMailDriver;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class MailPlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'openkos/mail',
            name: 'Mail Notifications',
            version: '1.0.0',
            description: 'Registers built-in SMTP and Log mail drivers.',
        );
    }

    public function register(OpenKOSManager $platform): void
    {
        $platform->notifications()->registerDriver(new NotificationDriverRegistration(
            name: 'openkos/log',
            channel: 'mail',
            driverClass: LogMailDriver::class,
            label: 'Log (Local / Testing)',
            laravelMailer: 'log',
        ));

        $platform->notifications()->registerDriver(new NotificationDriverRegistration(
            name: 'openkos/smtp',
            channel: 'mail',
            driverClass: SmtpMailDriver::class,
            label: 'SMTP Server',
            laravelMailer: 'smtp',
        ));
    }
}
