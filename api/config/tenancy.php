<?php

declare(strict_types=1);

use App\Models\Central\Domain;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | 自定义租户模型，对应 Central DB 的 stores 表。
    |
    */
    'tenant_model' => App\Models\Central\Store::class,

    /*
    |--------------------------------------------------------------------------
    | ID Generator
    |--------------------------------------------------------------------------
    |
    | 使用自增 ID（AutoIncrementingIntIdGenerator），不用 UUID。
    |
    */
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class, // 初始安装用 UUID，后续可替换为自增

    /*
    |--------------------------------------------------------------------------
    | Domain Model
    |--------------------------------------------------------------------------
    */
    'domain_model' => \App\Models\Central\Domain::class,

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | 中央（平台管理）域名列表。这些域名的请求不会触发租户识别。
    |
    */
    'central_domains' => [
        env('CENTRAL_DOMAIN', 'admin.jerseyholic.com'),
        'localhost',       // 本地开发
        '127.0.0.1',      // 本地开发
    ],

    /*
    |--------------------------------------------------------------------------
    | Bootstrappers
    |--------------------------------------------------------------------------
    |
    | 租户初始化引导器列表。当租户被识别后，这些引导器会依次执行：
    | - DatabaseTenancyBootstrapper: 切换数据库连接
    | - CacheTenancyBootstrapper:   缓存 key 前缀隔离
    | - QueueTenancyBootstrapper:   队列任务自动携带租户上下文
    | - FilesystemTenancyBootstrapper: 文件系统路径隔离
    | - RedisTenancyBootstrapper:   Redis key 前缀隔离
    |
    */
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | 租户数据库命名规则：prefix + tenant_id + suffix
    | 例如：store_1, store_2
    |
    */
    'database' => [
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),

        'template_tenant_connection' => 'tenant',

        'prefix' => 'store_',
        'suffix' => '',

        'managers' => [
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | CacheTenancyBootstrapper 使用的 tag。
    |
    */
    'cache' => [
        'tag_base' => 'tenant_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem
    |--------------------------------------------------------------------------
    |
    | FilesystemTenancyBootstrapper 配置。
    |
    */
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    |
    | RedisTenancyBootstrapper 会给这些连接的 key 加上租户前缀。
    |
    */
    'redis' => [
        'prefix_base' => 'tenant_',
        'prefixed_connections' => [
            'default',
            'cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | 可选功能模块列表。按需启用。
    |
    */
    'features' => [
        // Stancl\Tenancy\Features\TenantConfig::class,
        // Stancl\Tenancy\Features\CrossDomainRedirect::class,
        // Stancl\Tenancy\Features\UserImpersonation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Parameters
    |--------------------------------------------------------------------------
    |
    | 租户迁移文件路径和参数。
    |
    */
    'migration_parameters' => [
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder Parameters
    |--------------------------------------------------------------------------
    |
    | 租户数据库 Seeder 配置。
    |
    */
    'seeder_parameters' => [
        '--class' => 'Database\Seeders\TenantDatabaseSeeder',
    ],

];
