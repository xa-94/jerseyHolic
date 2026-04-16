<?php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'customers',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => null,
        ],
        'merchant' => [
            'driver' => 'sanctum',
            'provider' => 'merchant_users',
        ],
    ],
    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
        'merchants' => [
            'driver' => 'eloquent',
            'model' => App\Models\Merchant::class,
        ],
        'merchant_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Central\MerchantUser::class,
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Customer::class,
        ],
    ],
    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table' => 'jh_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'merchant_users' => [
            'provider' => 'merchant_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
    'password_timeout' => 10800,
];
