<?php

return [
    'fixtures' => [
        'enabled' => filter_var(env('LOAD_TEST_FIXTURES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'users' => [
            'owner' => [
                'name' => 'Load Test Owner',
                'email' => env('LOAD_TEST_OWNER_EMAIL'),
                'password' => env('LOAD_TEST_OWNER_PASSWORD'),
            ],
            'admin' => [
                'name' => 'Load Test Manager',
                'email' => env('LOAD_TEST_ADMIN_EMAIL'),
                'password' => env('LOAD_TEST_ADMIN_PASSWORD'),
            ],
            'staff' => [
                'name' => 'Load Test Staff',
                'email' => env('LOAD_TEST_STAFF_EMAIL'),
                'password' => env('LOAD_TEST_STAFF_PASSWORD'),
            ],
            'tenant' => [
                'name' => 'Load Test Tenant',
                'email' => env('LOAD_TEST_TENANT_EMAIL'),
                'password' => env('LOAD_TEST_TENANT_PASSWORD'),
            ],
        ],
    ],
];
