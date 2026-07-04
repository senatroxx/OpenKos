<?php

use App\Notifications\Drivers\BaileysDriver;
use App\Notifications\Drivers\FonnteDriver;
use App\Notifications\Drivers\WhatsAppCloudApiDriver;
use App\Notifications\Drivers\WhatsappLogDriver;

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
            'baileys' => [
                'class' => BaileysDriver::class,
                'label' => 'Baileys (Unofficial, Unstable)',
                'url' => env('BAILEYS_URL'),
                'api_key' => env('BAILEYS_API_KEY'),
            ],
            'fonnte' => [
                'class' => FonnteDriver::class,
                'label' => 'Fonnte (Unofficial)',
                'token' => env('FONNTE_TOKEN'),
            ],
            'whatsapp_cloud' => [
                'class' => WhatsAppCloudApiDriver::class,
                'label' => 'WhatsApp Cloud API (Official, Untested)',
                'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),
                'access_token' => env('WHATSAPP_CLOUD_ACCESS_TOKEN'),
            ],
        ],
    ],

];
