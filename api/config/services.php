<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal Configuration
    |--------------------------------------------------------------------------
    |
    | PayPal 支付网关配置。base_uri 支持 sandbox/live 环境切换。
    | 系统级 client_id/client_secret 用于 Webhook 验签（可选，
    | 不配置时从数据库取关联账号凭证）。
    |
    */
    'paypal' => [
        'base_uri'      => env('PAYPAL_BASE_URI', 'https://api-m.sandbox.paypal.com'),
        'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe 配置
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'webhook_secret'    => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'success_url'       => env('STRIPE_SUCCESS_URL'),
        'cancel_url'        => env('STRIPE_CANCEL_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GeoIP 配置
    |--------------------------------------------------------------------------
    */
    'geoip' => [
        'database_path' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-Country.mmdb')),
    ],
];
