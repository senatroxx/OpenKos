<?php

return [

    'store' => env('SETTINGS_CACHE_STORE'),
    'ttl' => (int) env('SETTINGS_CACHE_TTL', 3600),

];
