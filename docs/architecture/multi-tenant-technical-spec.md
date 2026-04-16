# JerseyHolic 多租户技术规范

> **版本**: v1.0  
> **日期**: 2026-04-17  
> **阶段**: Phase M1  
> **包**: stancl/tenancy v3  
> **框架**: Laravel 10  

---

## 目录

1. [Model 层 Central / Tenant 分离设计](#1-model-层-central--tenant-分离设计)
2. [Redis / Cache / Queue 租户隔离](#2-redis--cache--queue-租户隔离)
3. [路由层 Central / Tenant 分组配置](#3-路由层-central--tenant-分组配置)
4. [Nginx 配置与 SSL 管理](#4-nginx-配置与-ssl-管理)

---

## 1. Model 层 Central / Tenant 分离设计

### 1.1 架构概述

系统采用 **双库分离** 策略——Central DB（平台级/商户级数据）与 Tenant DB（店铺级业务数据，每店一库）。Model 层通过两个抽象基类实现连接隔离：

```
App\Models\Central\CentralModel   → 强制 $connection = 'central'
App\Models\Tenant\TenantModel     → 不设 $connection，由 stancl/tenancy 自动切换
```

### 1.2 CentralModel 基类

**文件**: `api/app/Models/Central/CentralModel.php`

```php
abstract class CentralModel extends Model
{
    protected $connection = 'central';
}
```

- 所有平台级 / 商户级数据模型继承此类
- 显式固定 `$connection = 'central'`，不受租户上下文切换影响
- 在任何时刻（Central 路由、Tenant 路由、队列任务）查询都走 Central DB

### 1.3 TenantModel 基类

**文件**: `api/app/Models/Tenant/TenantModel.php`

```php
abstract class TenantModel extends Model
{
    // 不显式设置 $connection，由 stancl/tenancy 的 DatabaseTenancyBootstrapper 自动处理。
}
```

- 所有店铺级业务数据模型继承此类
- **不设置** `$connection`，依赖 `DatabaseTenancyBootstrapper` 在租户上下文激活后自动切换默认连接
- 如需在 Central 上下文中查询 Tenant 数据（如数据迁移脚本），使用 `->setConnection('tenant')` 指定

### 1.4 Store Model — Tenant 契约实现

**文件**: `api/app/Models/Central/Store.php`

Store 模型是连接 stancl/tenancy 框架的核心，它继承了 `Stancl\Tenancy\Database\Models\Tenant` 并存储在 Central DB 的 `stores` 表中：

```php
class Store extends Tenant
{
    use SoftDeletes;

    protected $table = 'stores';

    public function getTenantKeyName(): string { return 'id'; }
    public function getTenantKey(): mixed { return $this->getAttribute('id'); }
}
```

**关键设计点**:
- 继承 stancl `Tenant` 基类，自动具备 `CentralConnection`、`HasDatabase`、`HasDomains`、`GeneratesIds` 等 Trait
- 使用自增整数 ID 作为 Tenant Key
- `database_password` 字段使用 `encrypted` cast 加密存储
- 关联关系：`merchant()` (BelongsTo)、`domains()` (HasMany)、`paymentAccounts()` (BelongsToMany)

### 1.5 Central Models 清单（20 个）

| # | Model | 说明 |
|---|-------|------|
| 1 | `CentralModel` | 抽象基类 |
| 2 | `Admin` | 平台管理员 |
| 3 | `Blacklist` | IP/邮箱黑名单 |
| 4 | `Domain` | 域名绑定 |
| 5 | `FundFlowLog` | 资金流水日志 |
| 6 | `Menu` | 后台菜单 |
| 7 | `Merchant` | 商户 |
| 8 | `MerchantApiKey` | 商户 API 密钥 |
| 9 | `MerchantRiskScore` | 商户风险评分 |
| 10 | `MerchantUser` | 商户用户 |
| 11 | `PaymentAccount` | 支付账户 |
| 12 | `PaymentAccountGroup` | 支付账户分组 |
| 13 | `PaymentAccountLog` | 支付账户日志 |
| 14 | `Permission` | 权限 |
| 15 | `ProductSyncLog` | 商品同步日志 |
| 16 | `Role` | 角色 |
| 17 | `SettlementDetail` | 结算明细 |
| 18 | `SettlementRecord` | 结算记录 |
| 19 | `Store` | 店铺/租户（Tenant Model） |
| 20 | `StorePaymentAccount` | 店铺-支付账户关联 |

### 1.6 Tenant Models 清单（50 个）

| # | Model | # | Model |
|---|-------|---|-------|
| 1 | `TenantModel` (基类) | 26 | `Payment` |
| 2 | `Banner` | 27 | `PaymentCard` |
| 3 | `Category` | 28 | `PaymentTransaction` |
| 4 | `CategoryDescription` | 29 | `Product` |
| 5 | `Country` | 30 | `ProductAttribute` |
| 6 | `Coupon` | 31 | `ProductAttributeValue` |
| 7 | `CouponUsage` | 32 | `ProductDescription` |
| 8 | `Currency` | 33 | `ProductImage` |
| 9 | `Customer` | 34 | `ProductSafeMapping` |
| 10 | `CustomerAddress` | 35 | `ProductSku` |
| 11 | `CustomerGroup` | 36 | `Promotion` |
| 12 | `Dispute` | 37 | `Refund` |
| 13 | `FbEventName` | 38 | `RewardPoint` |
| 14 | `FbPixelConfig` | 39 | `RiskOrder` |
| 15 | `GeoZone` | 40 | `SafeProduct` |
| 16 | `GeoZoneRule` | 41 | `Setting` |
| 17 | `Language` | 42 | `Shipment` |
| 18 | `OperationLog` | 43 | `ShipmentTrack` |
| 19 | `Order` | 44 | `ShippingProvider` |
| 20 | `OrderAddress` | 45 | `ShippingProviderMapping` |
| 21 | `OrderExt` | 46 | `ShippingRule` |
| 22 | `OrderHistory` | 47 | `ShippingZone` |
| 23 | `OrderItem` | 48 | `SkuPrefixConfig` |
| 24 | `OrderTotal` | 49 | `Zone` |
| 25 | `Page` / `PageDescription` | 50 | — |

### 1.7 向后兼容 Alias 策略

**目录**: `api/app/Models/` 根目录（约 60 个文件）

为了兼容旧代码中的 `App\Models\Xxx` 引用，根目录下保留了 alias 文件。每个 alias 文件的结构如下：

```php
// api/app/Models/Admin.php
namespace App\Models;

use App\Models\Central\Admin as CentralAdmin;

/**
 * @deprecated 请使用 App\Models\Central\Admin 代替。
 */
class Admin extends CentralAdmin
{
    // 继承自 Central\Admin，不做任何修改。
}
```

**工作原理**:
- 旧代码中 `use App\Models\Admin` 仍然可用，通过继承自动路由到 `Central\Admin`
- 所有 alias 文件标记为 `@deprecated`，新代码应直接引用 `Central\` 或 `Tenant\` 子命名空间
- Central alias 继承 Central Model → 自动使用 `central` 连接
- Tenant alias 继承 Tenant Model → 自动跟随租户上下文连接

### 1.8 开发规范

#### 新增 Model 规范

| 数据归属 | 放置目录 | 继承基类 | 示例 |
|---------|---------|---------|------|
| 平台/商户级（所有店铺共享） | `app/Models/Central/` | `CentralModel` | Merchant, Admin, Role |
| 店铺级（每店独立） | `app/Models/Tenant/` | `TenantModel` | Product, Order, Customer |

#### 注意事项

1. **新代码禁止直接引用** `App\Models\Xxx`（根目录 alias），应使用完整路径 `App\Models\Central\Xxx` 或 `App\Models\Tenant\Xxx`
2. **跨库关联查询**：Central Model 不能直接 `hasMany(TenantModel)`, 需要先初始化租户上下文后再查询
3. **队列任务中**：如需在 Job 中访问 Tenant 数据，确保 Job 实现了 `TenantAware` 或在 dispatch 时已处于租户上下文
4. **数据迁移脚本**：如需在非租户上下文查询 Tenant 数据，显式使用 `->setConnection('tenant')`
5. **新增 alias 文件**（可选）：如果新 Model 需要兼容旧代码引用，在 `app/Models/` 根目录创建 alias 文件

---

## 2. Redis / Cache / Queue 租户隔离

### 2.1 Bootstrappers 列表

**文件**: `api/config/tenancy.php`

当租户被识别后，以下 Bootstrapper 按顺序执行：

```php
'bootstrappers' => [
    DatabaseTenancyBootstrapper::class,    // 切换数据库连接
    CacheTenancyBootstrapper::class,       // 缓存 key 前缀隔离
    QueueTenancyBootstrapper::class,       // 队列任务自动携带租户上下文
    FilesystemTenancyBootstrapper::class,  // 文件系统路径隔离
    RedisTenancyBootstrapper::class,       // Redis key 前缀隔离
],
```

### 2.2 DatabaseTenancyBootstrapper — 数据库连接切换

- 租户初始化时，将 Laravel 默认数据库连接切换为当前租户的数据库（如 `store_1`, `store_2`）
- 租户数据库命名规则：`prefix + tenant_id + suffix`，即 `store_{id}`
- 模板连接 `tenant` 的 `database` 字段为 `null`，由 stancl 动态设置

**数据库连接配置**（`api/config/database.php`）:

```php
'central' => [
    'driver'   => 'mysql',
    'database' => env('DB_DATABASE_CENTRAL', 'jerseyholic_central'),
    'prefix'   => 'jh_',
    // ...
],
'tenant' => [
    'driver'   => 'mysql',
    'database' => null, // 由 tenancy 动态设置
    'prefix'   => 'jh_',
    // ...
],
```

### 2.3 CacheTenancyBootstrapper — 缓存前缀隔离

**配置** (`api/config/tenancy.php`):
```php
'cache' => [
    'tag_base' => 'tenant_',
],
```

**工作原理**:
- 租户上下文激活后，缓存 key 自动添加 `tenant_{store_id}` 前缀
- 例如：Store #3 的缓存 key `products:list` 实际存储为 `tenant_3:products:list`
- 租户上下文结束后，前缀自动移除，恢复为全局缓存

**缓存 Store 配置** (`api/config/cache.php`):

```php
'stores' => [
    'redis' => [                // 默认缓存 store（全局 / Central 使用）
        'driver' => 'redis',
        'connection' => 'cache',
    ],
    'tenant' => [               // 租户专用缓存 store
        'driver' => 'redis',
        'connection' => 'cache',
    ],
],
'prefix' => env('CACHE_PREFIX', 'jh_cache_'),
```

### 2.4 QueueTenancyBootstrapper — 队列隔离

**工作原理**:
- 在租户上下文中 dispatch 的队列任务会自动序列化当前 `tenant_id`
- Worker 处理任务时，先恢复租户上下文，再执行 Job `handle()` 方法
- 任务完成后，自动恢复到 Central 上下文

**队列配置** (`api/config/queue.php`):

```php
'connections' => [
    'redis' => [                    // 全局/Central 队列
        'driver' => 'redis',
        'queue'  => env('REDIS_QUEUE', 'jh_default'),
    ],
    'tenant_redis' => [             // 租户专用队列
        'driver' => 'redis',
        'queue'  => env('REDIS_QUEUE', 'jh_tenant'),
    ],
],
'batching' => ['database' => 'central'],  // Job batching 记录在 Central DB
'failed'   => ['database' => 'central'],  // 失败任务记录在 Central DB
```

### 2.5 RedisTenancyBootstrapper — Redis Key 前缀隔离

**配置** (`api/config/tenancy.php`):
```php
'redis' => [
    'prefix_base' => 'tenant_',
    'prefixed_connections' => [
        'default',
        'cache',
    ],
],
```

**工作原理**:
- 租户上下文激活后，`default` 和 `cache` 两个 Redis 连接的 key 自动添加 `tenant_{store_id}:` 前缀
- 例如：Store #5 的 Redis key `session:abc` 变为 `tenant_5:session:abc`
- 仅影响 `prefixed_connections` 中列出的连接

**Redis 连接配置** (`api/config/database.php`):
```php
'redis' => [
    'client'  => 'predis',
    'default' => ['host' => ..., 'database' => 0],  // 受租户前缀影响
    'cache'   => ['host' => ..., 'database' => 1],  // 受租户前缀影响
],
```

### 2.6 FilesystemTenancyBootstrapper — 文件存储路径隔离

**配置** (`api/config/tenancy.php`):
```php
'filesystem' => [
    'suffix_base' => 'tenant',
    'disks' => ['local', 'public'],
    'root_override' => [
        'local'  => '%storage_path%/app/',
        'public' => '%storage_path%/app/public/',
    ],
    'suffix_storage_path' => true,
    'asset_helper_tenancy' => true,
],
```

**工作原理**:
- 租户上下文激活后，`local` 和 `public` 磁盘的 root 路径自动切换到租户专属目录
- 例如：Store #2 的 `local` 磁盘 root 变为 `storage/tenant2/app/`
- 确保不同店铺的上传文件、导出文件互不干扰

### 2.7 开发规范

#### 缓存使用场景

| 场景 | 使用方式 | 说明 |
|------|---------|------|
| 租户业务数据缓存 | `Cache::store('tenant')->get(...)` | 自动隔离，推荐使用 |
| 全局/平台配置缓存 | `Cache::store('redis')->get(...)` | Central 数据，不加租户前缀 |
| 默认缓存（租户路由内） | `Cache::get(...)` | CacheTenancyBootstrapper 自动隔离 |

#### 注意事项

1. **在 Central 路由中**不要使用 `Cache::store('tenant')`，因为没有租户上下文，前缀为空
2. **队列任务**中如需访问 Central 数据的缓存，使用 `Cache::store('redis')` 显式指定
3. **Redis 连接**：如果需要一个不受租户前缀影响的 Redis 连接，可在 `database.php` 中新增一个连接，并不要将其加入 `prefixed_connections`
4. **文件上传**：在租户路由中使用 `Storage::disk('public')` 时，文件会自动存储到租户目录
5. **跨租户操作**（如管理后台批量导出）：需要手动遍历租户并使用 `tenancy()->initialize($store)` 切换上下文

---

## 3. 路由层 Central / Tenant 分组配置

### 3.1 架构概述

路由按业务域分为两个文件：

| 文件 | 域名 | 用途 | 中间件 |
|------|------|------|--------|
| `routes/central.php` | Central 域名 (admin.jerseyholic.com, localhost) | 平台管理、商户后台、Webhook | `api` |
| `routes/tenant.php` | 租户域名 (store1.jerseyholic.com) | 买家前台 | `api` + `tenant` |

路由由 `TenancyServiceProvider` 统一注册。

### 3.2 路由注册方式

**文件**: `api/app/Providers/TenancyServiceProvider.php`

```php
// Central 路由：绑定到 Central 域名
protected function mapCentralRoutes(): void
{
    foreach ($this->centralDomains() as $domain) {
        Route::middleware(['api'])
            ->domain($domain)
            ->group(base_path('routes/central.php'));
    }
}

// Tenant 路由：不绑定域名，通过中间件动态识别
protected function mapTenantRoutes(): void
{
    Route::middleware(['api', 'tenant'])
        ->group(base_path('routes/tenant.php'));
}
```

**Central 域名列表** (`api/config/tenancy.php`):
```php
'central_domains' => [
    env('CENTRAL_DOMAIN', 'admin.jerseyholic.com'),
    'localhost',
    '127.0.0.1',
],
```

### 3.3 Tenant 中间件组

**注册位置**: `TenancyServiceProvider::configureMiddleware()`

```php
Route::middlewareGroup('tenant', [
    ResolveTenantByDomain::class,        // 1. 根据域名查询 Central DB 的 domains 表，识别租户
    EnsureTenantContext::class,           // 2. 确保租户上下文已初始化
    PreventAccessFromCentralDomains::class, // 3. 阻止 Central 域名访问 Tenant 路由
]);
```

#### ResolveTenantByDomain 中间件流程

**文件**: `api/app/Http/Middleware/ResolveTenantByDomain.php`

```
请求进入 → 提取 Host（去 port, 去 www）
  → 是 Central 域名？→ 是 → 跳过，直接放行
  → 否 → 查询 jh_domains 表
    → 未找到 → 404 STORE_NOT_FOUND
    → 找到 → 获取 Store 实例
      → 检查状态：maintenance → 503 / suspended → 403 / 其他 → 404
      → 正常 → tenancy()->initialize($store) 初始化租户上下文
      → 注入 store、tenant_id、merchant、merchant_id 到 request attributes
```

#### EnsureTenantContext 中间件

**文件**: `api/app/Http/Middleware/EnsureTenantContext.php`

- 检查 `tenancy()->initialized`，如未初始化返回 403 `TENANT_CONTEXT_REQUIRED`

### 3.4 中间件别名注册

**文件**: `api/app/Http/Kernel.php`

```php
protected $middlewareAliases = [
    'auth'             => Authenticate::class,
    'force.json'       => ForceJsonResponse::class,
    'set.locale'       => SetLocaleMiddleware::class,
    'log.request'      => RequestLogMiddleware::class,
    'check.permission' => CheckPermission::class,
    'check.role'       => CheckRole::class,
    'tenant'           => ResolveTenantByDomain::class,
    'ensure.tenant'    => EnsureTenantContext::class,
    'central.only'     => PreventAccessFromTenantDomains::class,
];
```

### 3.5 Central 路由结构

**文件**: `routes/central.php`

```
/api/v1/admin/auth/login        [POST]    — 公开，管理员登录
/api/v1/admin/auth/logout       [POST]    — 需认证
/api/v1/admin/auth/me           [GET]     — 需认证
/api/v1/admin/dashboard         [GET]     — 需认证
/api/v1/admin/products/*        [CRUD]    — 需认证
/api/v1/admin/categories/*      [CRUD]    — 需认证
/api/v1/admin/orders/*          [CRUD]    — 需认证
/api/v1/admin/rbac/*            [CRUD]    — 需认证 + check.permission:rbac.manage

/api/v1/merchant/shop/*         [TODO]    — 需认证
/api/v1/merchant/products/*     [TODO]    — 需认证
/api/v1/merchant/orders/*       [TODO]    — 需认证
/api/v1/merchant/settlements/*  [TODO]    — 需认证

/api/v1/webhook/paypal/*        [POST]    — 公开（无需认证）
/api/v1/webhook/stripe/*        [POST]    — 公开
/api/v1/webhook/logistics/*     [POST]    — 公开
/api/v1/webhook/antom/*         [POST]    — 公开
```

Central 受保护路由使用中间件：`auth:sanctum`, `force.json`, `central.only`

### 3.6 Tenant 路由结构

**文件**: `routes/tenant.php`

```
公开路由（无需登录）:
/api/v1/products/*              [GET]     — 商品列表/搜索/详情
/api/v1/categories/*            [GET]     — 分类列表/详情
/api/v1/auth/login|register     [POST]    — 买家登录/注册
/api/v1/countries               [GET]     — 国家列表
/api/v1/cart/*                  [CRUD]    — 购物车
/api/v1/checkout/*              [POST]    — 结算
/api/v1/store/info              [GET]     — 店铺信息
/api/v1/shipping/rates          [POST]    — 运费查询

受保护路由（需要 auth:sanctum）:
/api/v1/orders/*                [CRUD]    — 订单
/api/v1/account/profile         [GET/PUT] — 个人信息
/api/v1/account/password        [PUT]     — 修改密码
/api/v1/account/addresses/*     [CRUD]    — 收货地址
```

Tenant 路由基础中间件：`force.json`, `set.locale`

### 3.7 RouteServiceProvider

**文件**: `api/app/Providers/RouteServiceProvider.php`

- 加载 `routes/api.php` 作为兼容入口（原有路由）
- 加载 `routes/web.php`
- 配置限流策略：`api` 限流 300/min，`login` 限流 30/min
- 多租户路由 (`central.php`, `tenant.php`) 由 `TenancyServiceProvider` 独立加载

### 3.8 开发规范

#### 新增路由规范

| 路由类型 | 文件 | 中间件 | 场景 |
|---------|------|--------|------|
| 平台管理接口 | `routes/central.php` — Admin 段 | `auth:sanctum`, `force.json`, `central.only` | 管理后台功能 |
| 商户后台接口 | `routes/central.php` — Merchant 段 | `auth:sanctum`, `force.json`, `central.only` | 商户自助管理 |
| 第三方回调 | `routes/central.php` — Webhook 段 | `force.json`（无 auth） | 支付/物流回调 |
| 买家前台接口 | `routes/tenant.php` | `force.json`, `set.locale`（公开）/ + `auth:sanctum`（受保护） | 店铺前台功能 |

#### 注意事项

1. **不要在 `routes/api.php` 中新增多租户路由**，该文件仅作兼容保留
2. **Central 路由中访问 Tenant 数据**：需要手动初始化租户上下文 `tenancy()->initialize($store)`
3. **Tenant 路由中访问 Central 数据**：直接使用 Central Model（CentralModel 已固定连接）
4. **Webhook 路由**放在 Central 路由中，因为回调通常携带订单 ID 等标识，需在内部逻辑中识别对应租户
5. **路由前缀**统一使用 `/api/v1/`

---

## 4. Nginx 配置与 SSL 管理

### 4.1 架构概述

系统提供 **全自动域名配置** 能力：当新店铺创建或绑定域名时，自动生成 Nginx 配置文件并签发 SSL 证书。

核心组件：

| 组件 | 文件 | 职责 |
|------|------|------|
| NginxConfigService | `app/Services/NginxConfigService.php` | 渲染模板、写入配置、测试/重载 Nginx |
| GenerateNginxConfigJob | `app/Jobs/GenerateNginxConfigJob.php` | 异步生成 Nginx 配置（队列任务） |
| ProvisionSSLCertificateJob | `app/Jobs/ProvisionSSLCertificateJob.php` | 异步签发 SSL 证书（队列任务） |
| nginx.php 配置 | `config/nginx.php` | 路径、端口、SSL、certbot 等配置 |
| store.conf.template | `resources/templates/nginx/store.conf.template` | 独立域名配置模板 |
| wildcard.conf.template | `resources/templates/nginx/wildcard.conf.template` | 通配符域名配置模板 |

### 4.2 配置参数

**文件**: `api/config/nginx.php`

```php
'nginx' => [
    'config_path'             => '/etc/nginx/sites-available',
    'enabled_path'            => '/etc/nginx/sites-enabled',
    'template_path'           => resource_path('templates/nginx/store.conf.template'),
    'wildcard_template_path'  => resource_path('templates/nginx/wildcard.conf.template'),
    'nuxt_port'               => env('NUXT_PORT', 3000),
    'laravel_port'            => env('LARAVEL_PORT', 8000),
    'ssl_cert_base_path'      => '/etc/letsencrypt/live',
    'ssl_wildcard_cert'       => '/etc/letsencrypt/live/jerseyholic.com/fullchain.pem',
    'ssl_wildcard_key'        => '/etc/letsencrypt/live/jerseyholic.com/privkey.pem',
    'auto_reload'             => env('NGINX_AUTO_RELOAD', false),
    'dry_run'                 => env('NGINX_DRY_RUN', true),
    'nginx_bin'               => '/usr/sbin/nginx',
    'certbot_bin'             => '/usr/bin/certbot',
    'certbot_webroot'         => '/var/www/certbot',
    'certbot_email'           => 'admin@jerseyholic.com',
],
```

### 4.3 NginxConfigService 核心方法

**文件**: `api/app/Services/NginxConfigService.php`

| 方法 | 说明 |
|------|------|
| `generateConfig(Store)` | 读取模板，替换占位符，返回渲染后的 Nginx 配置字符串 |
| `writeConfig(Store, enableSite)` | 生成配置 → 写入 sites-available → 创建 symlink 到 sites-enabled → 自动 reload |
| `removeConfig(Store)` | 删除配置文件和 symlink |
| `testConfig()` | 执行 `nginx -t` 测试语法 |
| `reloadNginx()` | 先 test 再 `nginx -s reload` |
| `configExists(Store)` | 检查配置文件是否存在 |

**模板占位符**:

| 占位符 | 替换值 |
|--------|--------|
| `{{STORE_NAME}}` | 店铺名称 |
| `{{DOMAIN}}` | 主域名 |
| `{{STORE_ID}}` | 店铺 ID |
| `{{GENERATED_AT}}` | 生成时间 |
| `{{SSL_CERT_PATH}}` | SSL 证书路径 |
| `{{SSL_KEY_PATH}}` | SSL 密钥路径 |
| `{{NUXT_PORT}}` | Nuxt SSR 端口 |
| `{{LARAVEL_PORT}}` | Laravel API 端口 |

**域名优先级**: domains 关系中的第一个域名 > store->domain 字段 > `{store_code}.jerseyholic.com` 回退

**Dry-run 模式**: 开发环境默认启用（`NGINX_DRY_RUN=true`），仅记录日志不写入文件系统

### 4.4 配置模板

#### store.conf.template（独立域名）

```nginx
# 80 → 301 → HTTPS
server {
    listen 80;
    server_name {{DOMAIN}};
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {{DOMAIN}};

    ssl_certificate {{SSL_CERT_PATH}};
    ssl_certificate_key {{SSL_KEY_PATH}};

    # Nuxt 3 SSR
    location / {
        proxy_pass http://127.0.0.1:{{NUXT_PORT}};
    }

    # Laravel API
    location /api/ {
        proxy_pass http://127.0.0.1:{{LARAVEL_PORT}};
    }

    # 静态资源 30 天缓存
    location ~* \.(js|css|png|jpg|...)$ {
        expires 30d;
    }
}
```

#### wildcard.conf.template（通配符域名）

- 作为 `default_server`，捕获所有未匹配的 `*.jerseyholic.com` 子域名
- 使用通配符 SSL 证书（Let's Encrypt DNS-01 验证）
- 适用于开发、测试及新店铺尚未获取独立证书时

### 4.5 SSL 证书签发

**文件**: `api/app/Jobs/ProvisionSSLCertificateJob.php`

```
Job dispatch → 更新 Domain.certificate_status = 'provisioning'
  → dry_run? → 是 → 设为 'dry_run'，结束
  → 否 → 执行 certbot certonly --webroot -w /var/www/certbot -d {domain}
    → 成功 → certificate_status = 'active'
    → 失败 → certificate_status = 'failed'，重试（最多 3 次，间隔 30s）
```

**Job 配置**: 队列 `nginx`，超时 300s，最多重试 3 次

### 4.6 域名配置自动化完整流程

```
1. 管理后台创建/绑定域名
   ↓
2. Domain 记录写入 Central DB (jh_domains 表)
   ↓
3. dispatch GenerateNginxConfigJob
   → NginxConfigService::writeConfig()
   → 渲染 store.conf.template
   → 写入 /etc/nginx/sites-available/store_{id}.conf
   → symlink 到 sites-enabled
   → nginx -t && nginx -s reload
   ↓
4. dispatch ProvisionSSLCertificateJob
   → certbot certonly --webroot
   → 签发 Let's Encrypt SSL 证书
   → 更新 Domain.certificate_status
   ↓
5. 域名生效，HTTPS 访问就绪
```

### 4.7 开发规范

#### 注意事项

1. **开发环境**默认 `NGINX_DRY_RUN=true`，不会实际写入文件系统，仅记录日志
2. **生产部署**前需设置 `NGINX_DRY_RUN=false`、`NGINX_AUTO_RELOAD=true`
3. **配置文件命名**: `store_{id}.conf`，由 `getConfigFilename()` 统一管理
4. **SSL 证书**: 子域名使用通配符证书，独立域名使用 certbot webroot 验证
5. **队列 Worker**: 需启动 `nginx` 队列 worker 处理配置生成和 SSL 签发任务
6. **模板修改**: 修改模板后，需要对所有已生成的配置执行批量重新生成
7. **Nginx 权限**: Laravel 进程需要有权限写入 `/etc/nginx/sites-available/` 和执行 `nginx -s reload`

---

## 附录：文件索引

| 类别 | 文件路径 |
|------|---------|
| Central Model 基类 | `api/app/Models/Central/CentralModel.php` |
| Tenant Model 基类 | `api/app/Models/Tenant/TenantModel.php` |
| Store (Tenant) Model | `api/app/Models/Central/Store.php` |
| Alias 文件（示例） | `api/app/Models/Admin.php` |
| 多租户配置 | `api/config/tenancy.php` |
| 数据库配置 | `api/config/database.php` |
| 缓存配置 | `api/config/cache.php` |
| 队列配置 | `api/config/queue.php` |
| Central 路由 | `api/routes/central.php` |
| Tenant 路由 | `api/routes/tenant.php` |
| TenancyServiceProvider | `api/app/Providers/TenancyServiceProvider.php` |
| RouteServiceProvider | `api/app/Providers/RouteServiceProvider.php` |
| HTTP Kernel | `api/app/Http/Kernel.php` |
| ResolveTenantByDomain | `api/app/Http/Middleware/ResolveTenantByDomain.php` |
| EnsureTenantContext | `api/app/Http/Middleware/EnsureTenantContext.php` |
| Nginx 配置 | `api/config/nginx.php` |
| NginxConfigService | `api/app/Services/NginxConfigService.php` |
| GenerateNginxConfigJob | `api/app/Jobs/GenerateNginxConfigJob.php` |
| ProvisionSSLCertificateJob | `api/app/Jobs/ProvisionSSLCertificateJob.php` |
| 店铺 Nginx 模板 | `api/resources/templates/nginx/store.conf.template` |
| 通配符 Nginx 模板 | `api/resources/templates/nginx/wildcard.conf.template` |
