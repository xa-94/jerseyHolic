---
title: JerseyHolic 多租户架构设计调研报告
date: 2026-04-16
version: v2.0
status: 完稿
---

# JerseyHolic 多租户架构设计调研报告

> 版本：v2.0 | 更新日期：2026-04-16
> 项目：JerseyHolic 跨境电商统一系统

---

## 目录

1. [执行摘要](#1-执行摘要)
2. [调研范围与目标](#2-调研范围与目标)
3. [多租户方案对比](#3-多租户方案对比)
4. [现有架构分析](#4-现有架构分析)
5. [推荐架构方案](#5-推荐架构方案)
6. [数据库设计](#6-数据库设计)
7. [商户多站点管理架构](#7-商户多站点管理架构)
8. [商品同步与市场配置架构](#8-商品同步与市场配置架构)
9. [实施路线图](#9-实施路线图)

---

## 1. 执行摘要

**推荐方案**：**stancl/tenancy v3** + **database-per-tenant** + **自定义中央库管理**

### 核心商业模式

平台出租独立站给商户，所有支付账号归平台所有。商户运营独立站，收入进平台账户，扣除佣金后线下结算给商户。每个商户拥有独立后台（查看订单/商品/销售/结算，不管理支付账号），每个站点拥有独立域名和独立数据库（完全数据隔离）。

**关键创新**：一个商户可以同时管理多个独立站，商品更新可同步到名下所有站点。

### 关键决策

| 维度 | 决策 | 理由 |
|------|------|------|
| 多租户模式 | Database-per-tenant | 最高级别数据隔离与安全性 |
| 运营模式 | 平台托管 | 平台管支付账号，商户负责运营 |
| 包选择 | stancl/tenancy v3 | 功能完整、社区活跃、生产稳定 |
| Laravel 版本 | 10+（兼容 11+） | 完全支持 |
| 域名识别 | 中间件 + DB 查询 | 灵活高效 |
| 商户-站点关系 | 1:N（一商户多站点） | 支持多市场多品类运营 |
| 商品同步 | 主商品库 + 异步队列 | 高效可靠的跨站点同步 |

---

## 2. 调研范围与目标

### 调研维度

1. **多租户包选型**：对比主流 Laravel 多租户解决方案
2. **数据隔离策略**：database-per-tenant vs shared-database 对比
3. **现有架构适配**：评估当前 jerseyholic-new 项目的改造成本
4. **商户多站点**：设计一商户多站点的管理架构
5. **商品同步机制**：跨站点商品同步的技术方案
6. **多市场配置**：多品类、多语言、多货币的差异化配置
7. **前端适配**：Nuxt 3 SSR + Vue 3 管理后台的多租户支持
8. **安全性**：数据隔离、凭证加密、权限控制
9. **实施路线**：分阶段实施计划与工期预估

### 调研目标

- 确定最优的多租户技术方案
- 设计支持"一商户多站点"的架构
- 规划商品跨站点同步机制
- 输出可执行的分阶段实施路线图

---

## 3. 多租户方案对比

### 方案一：stancl/tenancy v3 ⭐ 推荐

**GitHub**：4000+ Stars，450+ Fork

**优点**：
- 社区最活跃的 Laravel 多租户包
- 自动化程度最高：数据库/缓存/队列/文件系统自动切换
- 用户同步、权限管理等商业特性支持
- 生产稳定，大量企业级验证
- 丰富的 Bootstrappers 和事件钩子

**缺点**：
- 学习成本中等
- 深度定制需要较多配置

**Laravel 支持**：8+ 至 11+
**PHP 要求**：8.0+

**关键配置示例**：

```php
// config/tenancy.php
return [
    'tenant_model' => \App\Models\Tenant::class,

    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => \Stancl\Tenancy\Database\Models\Domain::class,

    'central_domains' => [
        'admin.jerseyholic.com',  // 平台管理后台
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    ],

    'database' => [
        'prefix' => 'jerseyholic_store_',
        'suffix' => '',
    ],
];
```

**中间件配置**：

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'tenant' => [
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ],
];
```

---

### 方案二：spatie/laravel-multitenancy

**优点**：
- 设计简洁，学习成本低
- 代码侵入性小

**缺点**：
- 功能有限，许多功能需手动实现
- 社区规模较小
- 不适合复杂项目

**结论**：不适合 JerseyHolic，项目需要支持商品同步映射等复杂功能。

---

### 方案三：手动实现

**优点**：
- 完全可控，自定义程度高

**缺点**：
- 开发工作量巨大（预估 4-6 周额外开发）
- 维护成本高
- 安全漏洞风险大
- 测试覆盖困难

**结论**：不推荐

---

### 对比总表

| 维度 | stancl/tenancy v3 | spatie/multitenancy | 手动实现 |
|------|:--:|:--:|:--:|
| 功能完整度 | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ |
| 自动化程度 | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐ |
| 学习成本 | 中等 | 低 | 高 |
| 开发工期 | 短（1-2 周集成） | 中（需补功能） | 长（4-6 周） |
| 社区支持 | 活跃 | 一般 | 无 |
| 生产验证 | 大量 | 少量 | 无 |
| 深度定制 | 支持（事件+钩子） | 有限 | 完全自由 |
| 数据库隔离 | 原生支持 | 需扩展 | 需自建 |
| 缓存/队列隔离 | 原生支持 | 不支持 | 需自建 |
| 维护成本 | 低 | 中 | 高 |
| **综合评分** | **⭐⭐⭐⭐⭐ 推荐** | **⭐⭐ 不推荐** | **❌ 不推荐** |

---

## 4. 现有架构分析

### 项目结构现状

当前 `jerseyholic-new` 项目采用前后端分离架构：

```
jerseyholic-new/
├── api/            # Laravel 10 后端 API
├── admin-ui/       # Vue 3 + Element Plus 管理后台
├── storefront/     # Nuxt 3 SSR 买家前台
└── docs/           # 项目文档
```

### 技术栈

- **后端**：Laravel 10（PHP 8.1+）、Sanctum 认证、Eloquent ORM
- **管理后台**：Vue 3 + TypeScript + Element Plus + Vite
- **买家前台**：Nuxt 3（SSR）+ TailwindCSS + @nuxtjs/i18n
- **数据库**：MySQL 8.0+（utf8mb4）
- **缓存/队列**：Redis（predis 客户端）

### 数据库连接现状

当前 `config/database.php` 配置了三个数据库连接：

| 连接名 | 数据库 | 表前缀 | 用途 |
|--------|--------|--------|------|
| `mysql` | `jerseyholic_new` | 无 | 新系统主库 |
| `mysql_oc` | `jerseyholic_oc` | `oc_` | 旧 OpenCart 系统 |
| `mysql_tp` | `jerseyholic_tp` | 无 | ThinkPHP 旧系统 |

### 依赖现状（composer.json）

当前尚未安装多租户相关包。核心依赖：

| 包 | 版本 | 说明 |
|---|---|---|
| laravel/framework | ^10.0 | 框架核心 |
| laravel/sanctum | ^3.3 | API 认证 |
| predis/predis | ^3.4 | Redis 客户端 |
| guzzlehttp/guzzle | ^7.8 | HTTP 客户端 |

### 改造评估

| 方面 | 现状 | 改造难度 | 说明 |
|------|------|----------|------|
| 数据库配置 | 单库 + 两个旧系统连接 | 低 | 新增 central + tenant 连接即可 |
| 认证体系 | Sanctum | 低 | 天然支持多租户 Token 隔离 |
| Redis 配置 | 单实例 | 低 | 通过 stancl 自动添加租户前缀 |
| Model 层 | 标准 Eloquent | 中 | 需指定 connection 属性 |
| 路由层 | RESTful API | 低 | 添加 tenant 中间件组即可 |
| 前端适配 | Nuxt 3 SSR | 中 | 需根据域名动态获取配置 |

---

## 5. 推荐架构方案

### 方案选型结论

**stancl/tenancy v3 + 自定义扩展**

选型理由：
1. **功能完整度最高**：原生支持 database/cache/queue/filesystem 隔离
2. **开发效率最高**：自动化切换减少 70% 手动配置工作
3. **社区最活跃**：4000+ Stars，问题响应快，文档完善
4. **扩展性强**：通过事件和 Bootstrapper 机制轻松定制
5. **与 Laravel 10 完全兼容**：无需升级框架

### 整体架构图

```
┌─────────────────────────────────────────────────────────────┐
│                        用户请求层                            │
│  store-a.jerseyholic.com  store-b.jerseyholic.com  ...      │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                      Nginx 反向代理                          │
│              通配符 SSL + 域名路由分发                         │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                   Laravel 应用层                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │        ResolveTenant 中间件（域名识别）                │    │
│  │  请求 → 提取域名 → 查 Central DB → 获取 store 信息    │    │
│  │       → 切换 Tenant DB → 执行业务逻辑                 │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │  平台管理 API │  │  商户后台 API │  │  买家前台 API │       │
│  │  (Central)   │  │  (Central +  │  │  (Tenant)    │       │
│  │              │  │   Tenant)    │  │              │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
└──────────────────────────┬──────────────────────────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ Central DB   │  │ Merchant DB  │  │  Store DBs   │
│ jerseyholic  │  │ jerseyholic  │  │ jerseyholic  │
│ _central     │  │ _merchant_1  │  │ _store_1     │
│              │  │ _merchant_2  │  │ _store_2     │
│ - merchants  │  │              │  │ _store_3     │
│ - stores     │  │ - master_    │  │ ...          │
│ - payments   │  │   products   │  │              │
│ - settlement │  │ - sync_rules │  │ - products   │
│ - admins     │  │              │  │ - orders     │
│              │  │              │  │ - customers  │
└──────────────┘  └──────────────┘  └──────────────┘
         │                 │                 │
         └─────────────────┼─────────────────┘
                           ▼
                  ┌──────────────┐
                  │    Redis     │
                  │ 按租户前缀   │
                  │ 隔离缓存     │
                  └──────────────┘
```

### Central DB vs Tenant DB 职责划分

| 数据类型 | 存储位置 | 隔离方式 | 说明 |
|---------|---------|---------|------|
| 商户信息 | Central DB | 行级隔离 | 平台统一管理商户 |
| 站点配置 | Central DB | merchant_id 关联 | 一商户多站点 |
| 支付账号 | Central DB | 平台所有 | 平台管理支付账号 |
| 结算记录 | Central DB | merchant_id 维度 | 按商户聚合结算 |
| 平台管理员 | Central DB | — | 平台级别 |
| 商户用户 | Central DB | merchant_id 关联 | 商户后台登录 |
| 主商品数据 | Merchant DB | 按商户隔离 | 商户级商品主库 |
| 同步规则 | Merchant DB | 按商户隔离 | 商品同步配置 |
| 商品/SKU | Store DB | 数据库隔离 | 站点独立商品 |
| 订单 | Store DB | 数据库隔离 | 站点独立订单 |
| 客户 | Store DB | 数据库隔离 | 站点独立客户 |
| 购物车 | Store DB | 数据库隔离 | 站点独立购物车 |

### 中间件识别流程

```
用户请求 (store-a.jerseyholic.com/api/products)
    │
    ▼
[1] Nginx 接收请求，匹配通配符域名
    │
    ▼
[2] Laravel ResolveTenant 中间件
    │
    ├── 提取 Host: store-a.jerseyholic.com
    │
    ▼
[3] 查询 Central DB
    │
    ├── SELECT * FROM jh_stores WHERE domain = 'store-a.jerseyholic.com'
    ├── 获取: store_id, merchant_id, database_name, db_host, db_user, db_password
    │
    ▼
[4] 初始化租户上下文
    │
    ├── 切换数据库连接到 jerseyholic_store_{id}
    ├── 设置 Redis 前缀为 store_{id}:
    ├── 设置文件系统路径为 storage/stores/{id}/
    │
    ▼
[5] 执行业务逻辑（所有查询自动路由到 Tenant DB）
    │
    ▼
[6] 返回响应
```

### Nuxt 3 前端适配方案

```typescript
// composables/useStore.ts
export const useStore = () => {
  const config = useRuntimeConfig()
  const host = process.server
    ? useRequestHeaders()['host']
    : window.location.host

  // 根据域名从 API 获取站点配置
  const { data: storeConfig } = useFetch('/api/store/config', {
    baseURL: config.public.apiBase,
    headers: { 'X-Store-Domain': host || '' },
  })

  return {
    storeConfig,
    storeDomain: host,
  }
}
```

```typescript
// nuxt.config.ts - 多租户适配
export default defineNuxtConfig({
  ssr: true,

  runtimeConfig: {
    public: {
      apiBase: process.env.API_BASE_URL || 'https://api.jerseyholic.com',
    },
  },

  // i18n 根据站点配置动态加载
  modules: [
    '@nuxtjs/i18n',
  ],

  i18n: {
    locales: [
      { code: 'en', iso: 'en-US', file: 'en.json' },
      { code: 'zh', iso: 'zh-CN', file: 'zh.json' },
      { code: 'es', iso: 'es-ES', file: 'es.json' },
      { code: 'fr', iso: 'fr-FR', file: 'fr.json' },
      { code: 'de', iso: 'de-DE', file: 'de.json' },
      { code: 'ja', iso: 'ja-JP', file: 'ja.json' },
    ],
    defaultLocale: 'en',
    strategy: 'prefix',
    langDir: 'locales',
  },
})
```

---

## 6. 数据库设计

### 6.1 Central Database（jerseyholic_central）

#### jh_merchants — 商户主表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 商户 ID |
| name | VARCHAR(100) | 商户名称 |
| slug | VARCHAR(50) UNIQUE | 商户标识（用于生成数据库名等） |
| contact_name | VARCHAR(50) | 联系人姓名 |
| contact_email | VARCHAR(100) | 联系人邮箱 |
| contact_phone | VARCHAR(30) | 联系人电话 |
| commission_rate | DECIMAL(5,2) | 佣金比例（如 15.00 表示 15%） |
| status | ENUM('active','suspended','pending') | 商户状态 |
| settings | JSON | 商户扩展设置 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_stores — 站点表（关联 merchant_id，1:N 关系）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 站点 ID |
| merchant_id | BIGINT UNSIGNED FK | 所属商户 ID |
| name | VARCHAR(100) | 站点名称 |
| domain | VARCHAR(255) UNIQUE | 站点域名 |
| database_name | VARCHAR(100) | 租户数据库名 |
| db_host | VARCHAR(100) | 数据库主机 |
| db_port | INT | 数据库端口，默认 3306 |
| db_user | VARCHAR(100) | 数据库用户名（加密存储） |
| db_password | TEXT | 数据库密码（加密存储） |
| target_markets | JSON | 目标市场，如 `["US","UK","DE"]` |
| supported_languages | JSON | 支持语言，如 `["en","es","fr"]` |
| supported_currencies | JSON | 支持货币，如 `["USD","EUR","GBP"]` |
| primary_currency | VARCHAR(3) | 主货币 |
| product_categories | JSON | 站点品类，如 `["jerseys","shoes"]` |
| timezone | VARCHAR(50) | 站点时区 |
| status | ENUM('active','inactive','maintenance') | 站点状态 |
| settings | JSON | 站点扩展设置（主题、Logo、SEO 等） |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_payment_accounts — 支付账号表（归平台所有）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 支付账号 ID |
| provider | VARCHAR(30) | 支付渠道（paypal/stripe/antom） |
| account_name | VARCHAR(100) | 账号名称/标识 |
| credentials | TEXT | 加密存储的凭证信息 |
| is_default | TINYINT(1) | 是否默认账号 |
| status | ENUM('active','disabled') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_store_payment_accounts — 站点支付账号关联表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | ID |
| store_id | BIGINT UNSIGNED FK | 站点 ID |
| payment_account_id | BIGINT UNSIGNED FK | 支付账号 ID |
| is_active | TINYINT(1) | 是否启用 |
| priority | INT | 优先级 |

#### jh_settlement_records — 结算记录表（商户维度聚合）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 结算 ID |
| merchant_id | BIGINT UNSIGNED FK | 商户 ID |
| period_start | DATE | 结算周期开始 |
| period_end | DATE | 结算周期结束 |
| total_revenue | DECIMAL(12,2) | 总收入 |
| commission_amount | DECIMAL(12,2) | 佣金金额 |
| settlement_amount | DECIMAL(12,2) | 应结算给商户的金额 |
| currency | VARCHAR(3) | 结算币种 |
| status | ENUM('pending','confirmed','paid') | 结算状态 |
| details | JSON | 各站点明细 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_product_sync_logs — 商品同步日志表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 日志 ID |
| merchant_id | BIGINT UNSIGNED FK | 商户 ID |
| source_store_id | BIGINT UNSIGNED NULL | 来源站点（NULL 表示来自主商品库） |
| target_store_id | BIGINT UNSIGNED FK | 目标站点 ID |
| sync_type | ENUM('full','incremental') | 同步类型 |
| trigger_type | ENUM('manual','auto','scheduled') | 触发方式 |
| total_products | INT | 同步商品总数 |
| success_count | INT | 成功数 |
| fail_count | INT | 失败数 |
| status | ENUM('pending','running','completed','failed') | 同步状态 |
| error_log | JSON | 错误详情 |
| started_at | TIMESTAMP | 开始时间 |
| completed_at | TIMESTAMP NULL | 完成时间 |

#### jh_admins — 平台管理员表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 管理员 ID |
| username | VARCHAR(50) UNIQUE | 用户名 |
| email | VARCHAR(100) UNIQUE | 邮箱 |
| password | VARCHAR(255) | 密码（bcrypt） |
| name | VARCHAR(50) | 姓名 |
| role | VARCHAR(30) | 角色 |
| status | ENUM('active','disabled') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_merchant_users — 商户用户表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 用户 ID |
| merchant_id | BIGINT UNSIGNED FK | 所属商户 ID |
| username | VARCHAR(50) | 用户名 |
| email | VARCHAR(100) | 邮箱 |
| password | VARCHAR(255) | 密码（bcrypt） |
| name | VARCHAR(50) | 姓名 |
| role | ENUM('owner','manager','operator') | 角色 |
| allowed_store_ids | JSON | 可访问的站点 ID 列表（NULL 表示所有） |
| status | ENUM('active','disabled') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 6.2 Tenant Database（jerseyholic_store_{id}）

每个站点独立数据库，包含以下核心业务表：

#### 商品模块

| 表名 | 说明 |
|------|------|
| products | 商品主表（name, slug, description, price, status, sync_source_id） |
| product_skus | SKU 表（product_id, sku_code, price, stock, attributes JSON） |
| product_categories | 商品分类表 |
| product_images | 商品图片表 |
| product_attributes | 商品属性表（颜色、尺码等） |
| product_variants | 商品变体表 |
| product_reviews | 商品评价表 |

#### 订单模块

| 表名 | 说明 |
|------|------|
| orders | 订单主表（order_no, customer_id, total, status, payment_status） |
| order_items | 订单明细表 |
| order_addresses | 订单地址表（收货/账单地址） |
| order_shipments | 订单发货表 |
| order_payments | 订单支付记录表 |
| order_refunds | 退款表 |
| order_history | 订单状态变更历史 |

#### 客户模块

| 表名 | 说明 |
|------|------|
| customers | 客户表（email, name, password, phone） |
| customer_addresses | 客户地址簿 |
| customer_groups | 客户分组 |

#### 购物与营销

| 表名 | 说明 |
|------|------|
| carts | 购物车表 |
| cart_items | 购物车明细 |
| coupons | 优惠券表 |
| promotional_rules | 促销规则表 |

#### 物流模块

| 表名 | 说明 |
|------|------|
| shipping_rules | 运费规则表 |
| shipping_providers | 物流商配置表 |
| logistics_orders | 物流单表 |
| logistics_tracks | 物流轨迹表 |

### 6.3 Merchant Master Database（jerseyholic_merchant_{id}）

每个商户一个主商品库，用于存储商户级别的商品主数据，同步到其名下各站点。

#### master_products — 主商品表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 主商品 ID |
| name | VARCHAR(255) | 商品名称 |
| slug | VARCHAR(255) | URL 友好标识 |
| description | TEXT | 商品描述 |
| short_description | VARCHAR(500) | 简短描述 |
| base_price | DECIMAL(10,2) | 基础价格 |
| cost_price | DECIMAL(10,2) | 成本价 |
| category | VARCHAR(100) | 品类 |
| brand | VARCHAR(100) | 品牌 |
| images | JSON | 图片列表 |
| attributes | JSON | 属性（颜色、尺码等） |
| variants | JSON | 变体定义 |
| skus | JSON | SKU 列表（含各变体价格和库存） |
| seo_title | VARCHAR(255) | SEO 标题 |
| seo_description | VARCHAR(500) | SEO 描述 |
| status | ENUM('active','draft','archived') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### master_product_translations — 主商品多语言翻译表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | ID |
| master_product_id | BIGINT UNSIGNED FK | 主商品 ID |
| locale | VARCHAR(5) | 语言代码（en/zh/es/fr） |
| name | VARCHAR(255) | 翻译后名称 |
| description | TEXT | 翻译后描述 |
| short_description | VARCHAR(500) | 翻译后简短描述 |

#### sync_rules — 同步规则表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 规则 ID |
| master_product_id | BIGINT UNSIGNED FK NULL | 关联主商品（NULL 表示全局规则） |
| target_store_ids | JSON | 目标站点 ID 列表 |
| excluded_store_ids | JSON | 排除站点 ID 列表 |
| sync_fields | JSON | 需要同步的字段列表 |
| price_strategy | ENUM('fixed','multiplier','market_based') | 价格策略 |
| price_multiplier | DECIMAL(5,2) NULL | 价格倍率（如 1.10 = 加价 10%） |
| auto_sync | TINYINT(1) | 是否自动同步 |
| status | ENUM('active','paused') | 规则状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 6.4 数据归属总览

| 数据类型 | 存储位置 | 隔离方式 | 说明 |
|---------|---------|---------|------|
| merchants | Central DB | 行级 | 平台统一管理 |
| stores | Central DB | merchant_id FK | 一商户多站点 |
| payment_accounts | Central DB | 平台所有 | 平台管理支付 |
| settlement_records | Central DB | merchant_id 维度 | 按商户聚合 |
| master_products | Merchant DB | 按商户独立库 | 商品主数据 |
| sync_rules | Merchant DB | 按商户独立库 | 同步配置 |
| products | Store DB | 按站点独立库 | 站点商品 |
| orders | Store DB | 按站点独立库 | 完全数据隔离 |
| customers | Store DB | 按站点独立库 | 完全数据隔离 |

---

## 7. 商户多站点管理架构

### 7.1 Merchant → Store 1:N 关系设计

```
┌─────────────┐       ┌─────────────┐
│  Merchant   │       │   Store     │
│  (商户)     │ 1───N │  (站点)     │
│             │       │             │
│ id          │       │ id          │
│ name        │       │ merchant_id │◄── FK
│ slug        │       │ domain      │
│ commission  │       │ database    │
│ status      │       │ markets     │
│             │       │ languages   │
│             │       │ currencies  │
│             │       │ categories  │
└─────────────┘       └─────────────┘
       │
       │ 1───N
       ▼
┌─────────────┐
│ MerchantUser│
│ (商户员工)   │
│             │
│ merchant_id │
│ role        │
│ allowed_    │
│ store_ids   │
└─────────────┘
```

**核心关系**：
- 一个 Merchant 拥有多个 Store
- 一个 Merchant 拥有多个 MerchantUser
- MerchantUser 通过 `allowed_store_ids` 控制可访问的站点范围
- 每个 Store 对应一个独立数据库 `jerseyholic_store_{id}`

### 7.2 商户后台功能架构

```php
// 商户后台路由结构
Route::prefix('merchant')->middleware(['auth:merchant'])->group(function () {
    // 总览仪表盘（汇总所有站点数据）
    Route::get('/dashboard', [MerchantDashboardController::class, 'index']);

    // 站点管理
    Route::get('/stores', [MerchantStoreController::class, 'index']);
    Route::get('/stores/{store}', [MerchantStoreController::class, 'show']);

    // 商品管理（主商品库）
    Route::apiResource('/products', MerchantProductController::class);
    Route::post('/products/{product}/sync', [ProductSyncController::class, 'sync']);
    Route::post('/products/batch-sync', [ProductSyncController::class, 'batchSync']);

    // 订单查看（只读，聚合所有站点）
    Route::get('/orders', [MerchantOrderController::class, 'index']);
    Route::get('/orders/{store}/{order}', [MerchantOrderController::class, 'show']);

    // 结算报表
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::get('/settlements/{settlement}', [SettlementController::class, 'show']);

    // 销售统计（聚合所有站点）
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/by-store', [AnalyticsController::class, 'byStore']);
});
```

**商户后台核心功能**：

| 功能模块 | 说明 | 数据来源 |
|---------|------|---------|
| 总览仪表盘 | 所有站点的订单/收入/客户数汇总 | 遍历各 Store DB 聚合 |
| 站点管理 | 查看名下所有站点，切换站点上下文 | Central DB |
| 商品管理 | 管理主商品库，同步到各站点 | Merchant DB |
| 订单查看 | 查看所有站点的订单（只读） | 各 Store DB |
| 结算报表 | 查看佣金结算记录 | Central DB |
| 销售统计 | 按站点/品类/时间维度的销售分析 | 各 Store DB 聚合 |

### 7.3 站点创建流程

```php
// app/Services/StoreProvisioningService.php
class StoreProvisioningService
{
    public function createStore(Merchant $merchant, array $data): Store
    {
        return DB::connection('central')->transaction(function () use ($merchant, $data) {
            // 1. 在 Central DB 创建站点记录
            $store = Store::create([
                'merchant_id'          => $merchant->id,
                'name'                 => $data['name'],
                'domain'               => $data['domain'],
                'database_name'        => 'jerseyholic_store_' . Str::slug($data['name']),
                'db_host'              => config('tenancy.database.host'),
                'db_port'              => 3306,
                'db_user'              => config('tenancy.database.username'),
                'db_password'          => Crypt::encryptString(config('tenancy.database.password')),
                'target_markets'       => $data['target_markets'] ?? [],
                'supported_languages'  => $data['supported_languages'] ?? ['en'],
                'supported_currencies' => $data['supported_currencies'] ?? ['USD'],
                'primary_currency'     => $data['primary_currency'] ?? 'USD',
                'product_categories'   => $data['product_categories'] ?? [],
                'timezone'             => $data['timezone'] ?? 'UTC',
                'status'               => 'active',
            ]);

            // 2. 创建租户数据库
            $this->createDatabase($store);

            // 3. 运行租户数据库迁移（初始化表结构）
            $this->runMigrations($store);

            // 4. 生成 Nginx 配置（异步）
            GenerateNginxConfig::dispatch($store);

            // 5. 配置 SSL 证书（异步）
            ProvisionSSLCertificate::dispatch($store);

            return $store;
        });
    }

    private function createDatabase(Store $store): void
    {
        DB::connection('central')->statement(
            "CREATE DATABASE `{$store->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    private function runMigrations(Store $store): void
    {
        // 临时切换到新租户数据库连接
        config(["database.connections.tenant.database" => $store->database_name]);
        DB::purge('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);
    }
}
```

### 7.4 站点配置管理

每个站点可独立配置以下维度：

| 配置维度 | 字段 | 示例值 |
|---------|------|--------|
| 域名 | `domain` | `us-jerseys.jerseyholic.com` |
| 品类 | `product_categories` | `["jerseys","accessories"]` |
| 目标市场 | `target_markets` | `["US","CA","MX"]` |
| 语言 | `supported_languages` | `["en","es"]` |
| 主货币 | `primary_currency` | `USD` |
| 支持货币 | `supported_currencies` | `["USD","CAD","MXN"]` |
| 时区 | `timezone` | `America/New_York` |
| 支付偏好 | `settings.payment_methods` | `["paypal","stripe"]` |
| 物流渠道 | `settings.shipping_providers` | `["fedex","usps"]` |
| 主题配置 | `settings.theme` | `{"primary_color":"#1a1a2e"}` |

### 7.5 权限隔离

```php
// app/Http/Middleware/MerchantStoreAccess.php
class MerchantStoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('merchant');
        $storeId = $request->route('store')?->id ?? $request->input('store_id');

        if ($storeId) {
            // 验证站点是否属于当前商户
            $store = Store::where('id', $storeId)
                ->where('merchant_id', $user->merchant_id)
                ->firstOrFail();

            // 验证用户是否有权访问该站点
            if ($user->allowed_store_ids !== null
                && !in_array($storeId, $user->allowed_store_ids)) {
                abort(403, '无权访问该站点');
            }

            // 注入当前站点上下文
            app()->instance('current_store', $store);
        }

        return $next($request);
    }
}
```

---

## 8. 商品同步与市场配置架构

### 8.1 商品同步机制

#### 同步流程总览

```
┌──────────────────┐
│  商户主商品库      │
│  (Merchant DB)   │
│                  │
│  master_products │
│  sync_rules      │
└────────┬─────────┘
         │
         │  触发同步（手动/自动/定时）
         ▼
┌──────────────────┐
│  同步引擎         │
│  ProductSync     │
│  Service         │
│                  │
│  1. 读取同步规则  │
│  2. 筛选目标站点  │
│  3. 转换数据格式  │
│  4. 分发同步任务  │
└────────┬─────────┘
         │
         │  Laravel Job Queue（异步）
         │
    ┌────┼────┬────────┐
    ▼    ▼    ▼        ▼
┌──────┐┌──────┐┌──────┐
│Store ││Store ││Store │ ...
│ DB 1 ││ DB 2 ││ DB 3 │
│      ││      ││      │
│写入   ││写入   ││写入   │
│products││products││products│
└──────┘└──────┘└──────┘
         │
         ▼
┌──────────────────┐
│  Central DB      │
│  jh_product_     │
│  sync_logs       │
│  （记录同步结果） │
└──────────────────┘
```

#### 同步触发方式

| 触发方式 | 场景 | 实现 |
|---------|------|------|
| 手动触发 | 商户在后台点击"同步到站点" | API 调用 → dispatch Job |
| 保存时自动同步 | 商品保存后自动推送到关联站点 | Model Observer → dispatch Job |
| 定时批量同步 | 每日凌晨全量校验同步 | Laravel Scheduler → dispatch Job |

#### 同步策略

```php
// app/Services/ProductSyncService.php
class ProductSyncService
{
    /**
     * 同步商品到目标站点
     */
    public function syncProduct(
        int $merchantId,
        int $masterProductId,
        array $targetStoreIds = [],
        string $syncType = 'incremental'
    ): void {
        $merchantDb = "jerseyholic_merchant_{$merchantId}";

        // 1. 从主商品库读取商品数据
        $masterProduct = DB::connection($merchantDb)
            ->table('master_products')
            ->find($masterProductId);

        // 2. 读取同步规则
        $syncRule = DB::connection($merchantDb)
            ->table('sync_rules')
            ->where('master_product_id', $masterProductId)
            ->where('status', 'active')
            ->first();

        // 3. 确定目标站点
        $stores = $this->resolveTargetStores($merchantId, $targetStoreIds, $syncRule);

        // 4. 为每个目标站点分发异步同步任务
        foreach ($stores as $store) {
            SyncProductToStoreJob::dispatch(
                $store,
                $masterProduct,
                $syncRule,
                $syncType
            )->onQueue('product-sync');
        }

        // 5. 记录同步日志
        $this->createSyncLog($merchantId, $masterProductId, $stores, $syncType);
    }

    /**
     * 批量同步所有商品到指定站点
     */
    public function batchSync(int $merchantId, array $targetStoreIds = []): void
    {
        $merchantDb = "jerseyholic_merchant_{$merchantId}";

        $products = DB::connection($merchantDb)
            ->table('master_products')
            ->where('status', 'active')
            ->get();

        foreach ($products as $product) {
            $this->syncProduct($merchantId, $product->id, $targetStoreIds, 'full');
        }
    }

    /**
     * 解析目标站点（排除被排除的站点）
     */
    private function resolveTargetStores(
        int $merchantId,
        array $targetStoreIds,
        ?object $syncRule
    ): Collection {
        $query = Store::where('merchant_id', $merchantId)
            ->where('status', 'active');

        if (!empty($targetStoreIds)) {
            $query->whereIn('id', $targetStoreIds);
        }

        if ($syncRule && !empty($syncRule->excluded_store_ids)) {
            $excludedIds = json_decode($syncRule->excluded_store_ids, true);
            $query->whereNotIn('id', $excludedIds);
        }

        return $query->get();
    }
}
```

#### 异步队列实现

```php
// app/Jobs/SyncProductToStoreJob.php
class SyncProductToStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private Store $store,
        private object $masterProduct,
        private ?object $syncRule,
        private string $syncType
    ) {}

    public function handle(): void
    {
        // 切换到目标站点数据库
        $this->switchToStoreDb($this->store);

        try {
            if ($this->syncType === 'full') {
                $this->fullSync();
            } else {
                $this->incrementalSync();
            }
        } catch (\Exception $e) {
            // 更新同步日志状态为失败
            $this->updateSyncLog('failed', $e->getMessage());
            throw $e;
        }
    }

    private function fullSync(): void
    {
        // 全量同步：覆盖站点中对应商品的所有字段
        $productData = $this->transformProductData();

        DB::connection('tenant')->table('products')
            ->updateOrInsert(
                ['sync_source_id' => $this->masterProduct->id],
                $productData
            );

        // 同步 SKU、图片、属性等关联数据
        $this->syncSkus();
        $this->syncImages();
        $this->syncAttributes();
    }

    private function incrementalSync(): void
    {
        // 增量同步：只更新 sync_fields 中指定的字段
        $syncFields = $this->syncRule
            ? json_decode($this->syncRule->sync_fields, true)
            : ['name', 'description', 'base_price', 'images', 'skus'];

        $productData = $this->transformProductData($syncFields);

        DB::connection('tenant')->table('products')
            ->where('sync_source_id', $this->masterProduct->id)
            ->update($productData);
    }

    private function transformProductData(?array $fields = null): array
    {
        $allData = [
            'name'              => $this->masterProduct->name,
            'slug'              => $this->masterProduct->slug,
            'description'       => $this->masterProduct->description,
            'short_description' => $this->masterProduct->short_description,
            'price'             => $this->applyPriceStrategy($this->masterProduct->base_price),
            'category'          => $this->masterProduct->category,
            'brand'             => $this->masterProduct->brand,
            'images'            => $this->masterProduct->images,
            'status'            => $this->masterProduct->status,
            'sync_source_id'    => $this->masterProduct->id,
            'synced_at'         => now(),
        ];

        if ($fields) {
            return array_intersect_key($allData, array_flip($fields));
        }

        return $allData;
    }

    private function applyPriceStrategy(float $basePrice): float
    {
        if (!$this->syncRule) return $basePrice;

        return match ($this->syncRule->price_strategy) {
            'fixed'        => $basePrice,
            'multiplier'   => $basePrice * ($this->syncRule->price_multiplier ?? 1.0),
            'market_based' => $this->calculateMarketPrice($basePrice),
            default        => $basePrice,
        };
    }
}
```

#### 选择性同步

商户可以精确控制同步范围：

```php
// 示例：同步单个商品到指定站点
$syncService->syncProduct(
    merchantId: 1,
    masterProductId: 42,
    targetStoreIds: [1, 3],   // 只同步到站点 1 和 3
    syncType: 'incremental'
);

// 示例：同步所有商品到所有站点
$syncService->batchSync(merchantId: 1);

// 示例：同步规则配置
// sync_rules 表中可设置：
// - target_store_ids: [1,2,3]     → 只同步到这些站点
// - excluded_store_ids: [4]       → 排除站点 4
// - sync_fields: ["name","price"] → 只同步名称和价格
// - auto_sync: true               → 商品保存时自动同步
```

#### 同步冲突处理

| 冲突场景 | 处理策略 | 说明 |
|---------|---------|------|
| 站点本地修改 vs 主库更新 | 主库优先（可配置） | 默认以主商品库为准，覆盖站点本地修改 |
| 站点独有商品 | 不受影响 | 没有 sync_source_id 的商品不参与同步 |
| 同步字段冲突 | 按 sync_fields 决定 | 只有在 sync_fields 列表中的字段才会被覆盖 |
| 并发同步 | 数据库锁 + 幂等设计 | 使用 sync_source_id 做 updateOrInsert，天然幂等 |

#### 同步日志与监控

```php
// app/Services/SyncMonitorService.php
class SyncMonitorService
{
    /**
     * 获取商户的同步概览
     */
    public function getSyncOverview(int $merchantId): array
    {
        return [
            'total_syncs_today'  => ProductSyncLog::where('merchant_id', $merchantId)
                ->whereDate('started_at', today())->count(),
            'success_rate'       => $this->calculateSuccessRate($merchantId),
            'pending_syncs'      => ProductSyncLog::where('merchant_id', $merchantId)
                ->where('status', 'pending')->count(),
            'failed_syncs'       => ProductSyncLog::where('merchant_id', $merchantId)
                ->where('status', 'failed')
                ->whereDate('started_at', today())->count(),
            'last_sync_at'       => ProductSyncLog::where('merchant_id', $merchantId)
                ->latest('started_at')->value('started_at'),
        ];
    }
}
```

### 8.2 市场配置架构

#### 站点级别市场差异化配置

每个站点面向不同国家/市场，可独立配置：

```php
// 站点配置示例
Store::create([
    'merchant_id'          => 1,
    'name'                 => 'US Jerseys Store',
    'domain'               => 'us.jerseyholic.com',
    'target_markets'       => ['US', 'CA'],
    'supported_languages'  => ['en', 'es'],
    'supported_currencies' => ['USD', 'CAD'],
    'primary_currency'     => 'USD',
    'product_categories'   => ['jerseys', 'accessories'],
    'settings'             => [
        'payment_methods'    => ['paypal', 'stripe'],
        'shipping_providers' => ['fedex', 'usps', 'ups'],
        'tax_calculation'    => 'inclusive',
        'theme' => [
            'primary_color' => '#003366',
            'logo_url'      => '/assets/us-logo.png',
        ],
    ],
]);
```

#### 多语言内容管理

```php
// app/Services/TranslationSyncService.php
class TranslationSyncService
{
    /**
     * 同步商品时自动填充对应语言的翻译
     */
    public function syncTranslations(
        int $merchantId,
        int $masterProductId,
        Store $targetStore
    ): void {
        $merchantDb = "jerseyholic_merchant_{$merchantId}";

        foreach ($targetStore->supported_languages as $locale) {
            $translation = DB::connection($merchantDb)
                ->table('master_product_translations')
                ->where('master_product_id', $masterProductId)
                ->where('locale', $locale)
                ->first();

            if ($translation) {
                DB::connection('tenant')->table('product_translations')
                    ->updateOrInsert(
                        [
                            'product_id' => $masterProductId,
                            'locale'     => $locale,
                        ],
                        [
                            'name'        => $translation->name,
                            'description' => $translation->description,
                        ]
                    );
            }
        }
    }
}
```

#### 多货币价格策略

| 策略 | 说明 | 适用场景 |
|------|------|---------|
| fixed | 固定价格，各站点独立定价 | 价格差异大的市场 |
| multiplier | 基于基础价格乘以倍率 | 简单汇率换算 |
| market_based | 基于目标市场的定价规则 | 复杂的市场定价策略 |

```php
// 价格策略应用示例
// sync_rules.price_strategy = 'multiplier'
// sync_rules.price_multiplier = 1.15  （加价15%）
//
// 基础价格 $29.99 → 目标站点价格 $34.49
```

#### 地区限制物流规则

```php
// 站点级别物流配置示例
// jh_stores.settings.shipping_rules
{
    "shipping_providers": ["fedex", "usps"],
    "free_shipping_threshold": 99.00,
    "restricted_regions": ["AK", "HI", "PR"],
    "weight_rules": {
        "max_weight_kg": 30,
        "overweight_surcharge": 15.00
    },
    "delivery_estimates": {
        "domestic": "3-5 business days",
        "international": "7-14 business days"
    }
}
```

---

## 9. 实施路线图

### Phase 1：基础多租户架构（预估 2-3 周）

**目标**：完成 stancl/tenancy 集成，建立 Central DB，实现基础站点管理。

| 任务 | 优先级 | 预估工期 | 说明 |
|------|--------|---------|------|
| 安装 stancl/tenancy v3 | P0 | 1 天 | `composer require stancl/tenancy:^3.0` |
| 配置 tenancy.php | P0 | 1 天 | Bootstrappers、central_domains 等 |
| 创建 Central DB schema | P0 | 2 天 | jh_merchants、jh_stores 等核心表迁移 |
| 实现 ResolveTenant 中间件 | P0 | 2 天 | 域名识别 → 查询 Central DB → 切换 Tenant DB |
| 配置 Nginx 通配符域名 | P0 | 1 天 | 通配符 SSL + 反向代理 |
| 实现 StoreProvisioningService | P0 | 2 天 | 自动创建数据库、运行迁移 |
| 创建 Tenant DB 迁移文件 | P0 | 2 天 | products、orders、customers 等业务表 |
| 基础集成测试 | P0 | 2 天 | 验证租户隔离、数据库切换 |

**里程碑**：能够通过域名自动识别站点并切换到对应数据库。

---

### Phase 2：商户体系（预估 2-3 周）

**目标**：完成商户管理、多站点管理、商户后台。

| 任务 | 优先级 | 预估工期 | 说明 |
|------|--------|---------|------|
| 商户 CRUD API | P0 | 2 天 | 商户注册、编辑、状态管理 |
| 商户用户认证体系 | P0 | 2 天 | Sanctum guard + merchant_users 表 |
| 商户后台 API（仪表盘） | P1 | 2 天 | 聚合多站点数据的概览接口 |
| 站点创建/管理 API | P0 | 2 天 | 创建站点、配置管理 |
| 支付账号关联管理 | P1 | 1 天 | 平台分配支付账号到站点 |
| 结算记录模块 | P1 | 2 天 | 按商户维度的佣金计算和结算记录 |
| 商户后台前端（Vue 3） | P1 | 3 天 | 站点列表、切换、数据汇总 |
| 权限隔离中间件 | P0 | 1 天 | MerchantStoreAccess 中间件 |

**里程碑**：商户可以登录后台查看名下所有站点数据。

---

### Phase 3：商品同步（预估 2-3 周）

**目标**：实现主商品库和跨站点同步引擎。

| 任务 | 优先级 | 预估工期 | 说明 |
|------|--------|---------|------|
| 创建 Merchant DB schema | P0 | 1 天 | master_products、sync_rules 表 |
| 主商品 CRUD API | P0 | 2 天 | 商户管理主商品库 |
| ProductSyncService 核心实现 | P0 | 3 天 | 全量/增量同步逻辑 |
| SyncProductToStoreJob 异步任务 | P0 | 2 天 | 队列消费、重试、错误处理 |
| 选择性同步规则管理 | P1 | 2 天 | 目标站点选择、排除、字段过滤 |
| 同步日志与监控 | P1 | 1 天 | jh_product_sync_logs 记录与查询 |
| 同步冲突处理策略 | P1 | 1 天 | 主库优先、字段级覆盖 |
| 商品同步集成测试 | P0 | 2 天 | 跨库同步正确性验证 |

**里程碑**：商户可以在主商品库管理商品，并同步到名下所有站点。

---

### Phase 4：市场配置（预估 2 周）

**目标**：实现多品类、多语言、多货币、物流差异化。

| 任务 | 优先级 | 预估工期 | 说明 |
|------|--------|---------|------|
| 多语言翻译同步 | P1 | 2 天 | 主商品翻译 → 站点翻译同步 |
| 多货币价格策略 | P1 | 2 天 | fixed/multiplier/market_based 策略 |
| 站点级别物流配置 | P1 | 2 天 | 运费规则、地区限制 |
| 品类筛选与管理 | P2 | 1 天 | 站点品类过滤逻辑 |
| Nuxt 3 前端多租户适配 | P1 | 3 天 | 域名识别、动态配置加载、i18n |
| 性能优化与缓存 | P2 | 1 天 | 站点配置缓存、查询优化 |
| 安全审计 | P1 | 1 天 | 数据隔离验证、凭证加密检查 |

**里程碑**：站点可以面向不同国家/市场，提供差异化的语言、货币、物流服务。

---

### 总体时间表

```
Week 1-3:  Phase 1 - 基础多租户架构
Week 4-6:  Phase 2 - 商户体系
Week 7-9:  Phase 3 - 商品同步
Week 10-11: Phase 4 - 市场配置
Week 12:   整体集成测试 + 上线准备
```

**总预估工期**：10-12 周

---

## 附录：参考资料

- **stancl/tenancy 官方文档**：https://tenancyforlaravel.com/docs/v3
- **Laravel 官方文档**：https://laravel.com/docs
- **社区讨论**：GitHub Discussions（stancl/tenancy）

---

**文档完稿** | 2026-04-16 | v2.0
