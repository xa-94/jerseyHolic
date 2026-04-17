<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 品类体系配置
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'cache_ttl' => 3600, // 品类缓存 1h
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全映射名称配置
    |--------------------------------------------------------------------------
    */
    'safe_names' => [
        'cache_ttl' => 3600, // 安全名称缓存 1h
        'fallback_name' => 'General Merchandise',
    ],

    /*
    |--------------------------------------------------------------------------
    | 特货识别配置
    |--------------------------------------------------------------------------
    */
    'sensitive' => [
        'enabled' => true,
        'cache_ttl' => 1800, // 品牌黑名单缓存 30min
        'sku_prefixes' => ['hic', 'WPZ', 'DIY', 'NBL', 'NFL', 'NBA', 'MLB', 'NHL'],
        'min_category_ratio' => 50, // 品类敏感度最低阈值 %
    ],

    /*
    |--------------------------------------------------------------------------
    | 商品同步配置
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'batch_size' => 50,
        'queue' => 'product-sync',
        'retry_times' => 3,
        'retry_backoff' => [60, 120, 300],
        'daily_verification_time' => '03:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | 斗篷系统配置
    |--------------------------------------------------------------------------
    */
    'cloak' => [
        'enabled' => env('CLOAK_ENABLED', true),
        'default_mode' => env('CLOAK_DEFAULT_MODE', 'real'),
        'header_name' => 'X-Cloak-Mode',
        'placeholder_images' => [
            'jerseys' => '/images/placeholders/sports-apparel.jpg',
            'footwear' => '/images/placeholders/footwear.jpg',
            'headwear' => '/images/placeholders/headwear.jpg',
            'accessories' => '/images/placeholders/accessories.jpg',
            'bags' => '/images/placeholders/bags.jpg',
            'equipment' => '/images/placeholders/equipment.jpg',
            'default' => '/images/placeholders/general.jpg',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 站点商品配置
    |--------------------------------------------------------------------------
    */
    'store_config' => [
        'cache_ttl' => 1800, // 站点商品配置缓存 30min
        'default_language' => 'en',
        'default_currency' => 'USD',
    ],
];
