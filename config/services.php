<?php

use App\Notifications\Drivers\WhatsappLogDriver;

return [

    'marketplace' => [
        'url' => env('OPENKOS_MARKETPLACE_URL', 'https://marketplace.openkos.id'),
        'connect_timeout' => (int) env('OPENKOS_MARKETPLACE_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('OPENKOS_MARKETPLACE_TIMEOUT', 15),
        'max_response_bytes' => (int) env('OPENKOS_MARKETPLACE_MAX_RESPONSE_BYTES', 1024 * 1024),
        'max_artifact_bytes' => (int) env('OPENKOS_MARKETPLACE_MAX_ARTIFACT_BYTES', 64 * 1024 * 1024),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'default' => env('WHATSAPP_DRIVER', 'log'),

        // Seed data for the WhatsAppPlugin, which registers these into the
        // platform NotificationRegistry (the runtime source of truth).
        'drivers' => [
            'log' => [
                'class' => WhatsappLogDriver::class,
                'label' => 'Log',
            ],
        ],
    ],

];
