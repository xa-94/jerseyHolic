<?php

namespace App\Providers;

use App\Events\StoreDeprovisioned;
use App\Events\StoreProvisioned;
use App\Events\StoreProvisionFailed;
use App\Listeners\LogStoreProvisionFailure;
use App\Listeners\SendStoreProvisionedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * 应用事件-监听器注册
 *
 * 注册站点创建/销毁相关事件的监听器。
 * stancl/tenancy 自身的事件监听在 TenancyServiceProvider 中配置。
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * 事件 → 监听器映射
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        StoreProvisioned::class => [
            SendStoreProvisionedNotification::class,
        ],

        StoreProvisionFailed::class => [
            LogStoreProvisionFailure::class,
        ],

        StoreDeprovisioned::class => [
            // 后续可添加：清理缓存、发送通知等
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
