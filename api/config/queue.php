<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'jh_default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
        // 租户专用队列连接（QueueTenancyBootstrapper 会自动携带租户上下文）
        'tenant_redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'jh_tenant'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'jh_jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],
    ],
    'batching' => [
        'database' => env('DB_CONNECTION', 'central'),
        'table' => 'jh_job_batches',
    ],
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'central'),
        'table' => 'jh_failed_jobs',
    ],
];
