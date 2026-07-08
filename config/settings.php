<?php

return [

    'site_name' => ['default' => 'OpenKOS', 'cast' => 'string'],
    'country_code' => ['default' => 'ID', 'cast' => 'string'],
    'locale' => ['default' => 'id', 'cast' => 'string'],
    'currency' => ['default' => 'IDR', 'cast' => 'string'],
    'timezone' => ['default' => 'Asia/Jakarta', 'cast' => 'string'],
    'lease_id_prefix' => ['default' => 'LSX', 'cast' => 'string'],
    'reminder_enabled' => ['default' => true, 'cast' => 'boolean'],
    'reminder_days_before' => ['default' => 3, 'cast' => 'integer'],
    'reminder_overdue_intervals' => ['default' => [1, 3, 7], 'cast' => 'array'],
    'reminder_channels' => ['default' => ['log'], 'cast' => 'array'],
    'mail_config' => ['default' => [], 'cast' => 'encrypted:array'],
    'whatsapp_config' => ['default' => [], 'cast' => 'encrypted:array'],

];
