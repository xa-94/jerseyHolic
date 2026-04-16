<?php

return [
    'default' => env('DB_CONNECTION', 'central'),

    'migrations' => 'migrations',

    'connections' => [
        // Central database — 平台管理库（stores, domains, plans 等）
        'central' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE_CENTRAL', 'jerseyholic_central'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => 'jh_',
            'strict'    => true,
            'engine'    => 'InnoDB',
        ],

        // Tenant database template — 由 stancl/tenancy 动态切换
        'tenant' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => null, // 由 tenancy 动态设置
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => 'jh_',
            'strict'    => true,
            'engine'    => 'InnoDB',
        ],

        // 原有默认连接（保持兼容）
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'jerseyholic_new'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => 'InnoDB',
        ],

        // Legacy OpenCart 数据库（数据迁移用）
        'mysql_oc' => [
            'driver'    => 'mysql',
            'host'      => env('DB_OC_HOST', '127.0.0.1'),
            'port'      => env('DB_OC_PORT', '3306'),
            'database'  => env('DB_OC_DATABASE', 'jerseyholic_oc'),
            'username'  => env('DB_OC_USERNAME', 'root'),
            'password'  => env('DB_OC_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => 'oc_',
        ],

        // Legacy ThinkPHP 数据库（数据迁移用）
        'mysql_tp' => [
            'driver'    => 'mysql',
            'host'      => env('DB_TP_HOST', '127.0.0.1'),
            'port'      => env('DB_TP_PORT', '3306'),
            'database'  => env('DB_TP_DATABASE', 'jerseyholic_tp'),
            'username'  => env('DB_TP_USERNAME', 'root'),
            'password'  => env('DB_TP_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ],
    ],

    'redis' => [
        'client'  => 'predis',
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],
        'cache' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 1),
        ],
    ],
];
