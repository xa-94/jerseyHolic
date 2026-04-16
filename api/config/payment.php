<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 默认支付网关
    |--------------------------------------------------------------------------
    */
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'paypal'),

    /*
    |--------------------------------------------------------------------------
    | 选号策略（ElectionService）
    |--------------------------------------------------------------------------
    */
    'election' => [
        'behavior_min_interval' => env('PAYMENT_BEHAVIOR_MIN_INTERVAL', 60),
        'fallback_min_health_score' => env('PAYMENT_FALLBACK_MIN_HEALTH', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | 交易模拟（TransactionSimulationService）
    |--------------------------------------------------------------------------
    */
    'simulation' => [
        'amount_fluctuation_min' => '0.01',
        'amount_fluctuation_max' => '0.99',
        'daily_frequency_limit'  => env('PAYMENT_DAILY_FREQ_LIMIT', 100),
        'geo_check_enabled'      => env('PAYMENT_GEO_CHECK', true),
        'refund_rate_warning'    => 0.01,
        'refund_rate_critical'   => 0.02,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook 配置
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'idempotency_ttl' => 259200, // 72 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | 账号生命周期阶段限额
    |--------------------------------------------------------------------------
    */
    'lifecycle' => [
        'stages' => [
            'NEW'     => ['max_daily' => '500.00',  'max_monthly' => '5000.00'],
            'GROWING' => ['max_daily' => '2000.00', 'max_monthly' => '30000.00'],
            'MATURE'  => ['max_daily' => '5000.00', 'max_monthly' => '100000.00'],
            'AGING'   => ['max_daily' => '1000.00', 'max_monthly' => '15000.00'],
        ],
    ],
];
