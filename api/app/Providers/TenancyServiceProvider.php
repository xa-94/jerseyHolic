<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;
use Stancl\JobPipeline\JobPipeline;
use App\Http\Middleware\ResolveTenantByDomain;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\PreventAccessFromTenantDomains;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * 租户生命周期事件 → 任务监听映射。
     *
     * @var array<class-string, array<int, class-string|JobPipeline>>
     */
    public static function events(): array
    {
        return [
            // ----- 租户创建 -----
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class  => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    // Jobs\SeedDatabase::class,   // 按需启用
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            // ----- 租户更新 -----
            Events\SavingTenant::class   => [],
            Events\TenantSaved::class    => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class  => [],

            // ----- 租户删除 -----
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class  => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            // ----- 域名事件 -----
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class  => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class  => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class  => [],

            // ----- 租户初始化 / 结束 -----
            Events\InitializingTenancy::class  => [],
            Events\TenancyInitialized::class   => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\EndingTenancy::class        => [],
            Events\TenancyEnded::class         => [
                Listeners\RevertToCentralContext::class,
            ],

            // ----- 数据库事件 -----
            Events\DatabaseCreated::class    => [],
            Events\DatabaseMigrated::class   => [],
            Events\DatabaseSeeded::class     => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class    => [],
        ];
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureEvents();
        $this->configureMiddleware();
        $this->configureRoutes();
    }

    /**
     * 注册所有多租户事件监听器。
     */
    protected function configureEvents(): void
    {
        foreach (static::events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * 注册多租户相关中间件组。
     *
     * - 'tenant': 域名租户识别 + 确保租户上下文 + 防止 Central 域名访问租户路由
     * - stancl/tenancy 原始中间件组保持可用
     */
    protected function configureMiddleware(): void
    {
        // 注册 'tenant' 中间件组：用于 tenant 路由
        Route::middlewareGroup('tenant', [
            ResolveTenantByDomain::class,
            EnsureTenantContext::class,
            Middleware\PreventAccessFromCentralDomains::class,
        ]);
    }

    /**
     * 配置多租户路由（中央路由 & 租户路由）。
     *
     * 中央路由：仅在中央域名生效（admin 后台等）
     * 租户路由：带有租户识别中间件的路由
     */
    protected function configureRoutes(): void
    {
        $this->mapCentralRoutes();
        $this->mapTenantRoutes();
    }

    /**
     * 中央路由——不做租户初始化，仅在 Central 域名生效。
     *
     * 加载 routes/central.php，包含平台管理 + 商户后台 + Webhook 路由。
     */
    protected function mapCentralRoutes(): void
    {
        if (!file_exists(base_path('routes/central.php'))) {
            return;
        }

        foreach ($this->centralDomains() as $domain) {
            Route::middleware(['api'])
                ->domain($domain)
                ->group(base_path('routes/central.php'));
        }
    }

    /**
     * 租户路由——自动识别租户后再处理请求。
     *
     * 加载 routes/tenant.php，带有 'tenant' 中间件组。
     * 这些路由不绑定特定域名，而是通过 ResolveTenantByDomain 中间件
     * 在运行时根据请求域名动态识别租户。
     */
    protected function mapTenantRoutes(): void
    {
        if (!file_exists(base_path('routes/tenant.php'))) {
            return;
        }

        Route::middleware(['api', 'tenant'])
            ->group(base_path('routes/tenant.php'));
    }

    /**
     * 读取配置中的中央域名列表。
     *
     * @return array<int, string>
     */
    protected function centralDomains(): array
    {
        return config('tenancy.central_domains', []);
    }
}

