# Tenancy Setup Guide

## 安装步骤

### 1. 安装依赖
```bash
composer require stancl/tenancy:^3.8
```

### 2. 创建 Central 数据库
```sql
CREATE DATABASE jerseyholic_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. 配置 .env
确保 `.env` 中包含以下配置：
```
DB_CONNECTION=central
DB_DATABASE_CENTRAL=jerseyholic_central
CENTRAL_DOMAIN=admin.jerseyholic.com
```

### 4. 创建迁移目录
```bash
mkdir -p database/migrations/central
mkdir -p database/migrations/tenant
```

### 5. 运行 Central 数据库迁移
```bash
php artisan migrate --path=database/migrations/central
```

### 6. 验证安装
```bash
php artisan tenancy:list
```

## 架构说明

| 连接名 | 用途 | 数据库 |
|--------|------|--------|
| `central` | 平台管理（stores, domains, plans） | `jerseyholic_central` |
| `tenant` | 租户数据（由 tenancy 动态切换） | `store_{id}` |
| `mysql` | 原有连接（保持兼容） | `jerseyholic_new` |
| `mysql_oc` | Legacy OpenCart（数据迁移用） | `jerseyholic_oc` |
| `mysql_tp` | Legacy ThinkPHP（数据迁移用） | `jerseyholic_tp` |

## Bootstrappers

- **DatabaseTenancyBootstrapper** — 自动切换数据库连接
- **CacheTenancyBootstrapper** — 缓存 key 前缀隔离
- **QueueTenancyBootstrapper** — 队列任务自动携带租户上下文
- **FilesystemTenancyBootstrapper** — 文件系统路径隔离
- **RedisTenancyBootstrapper** — Redis key 前缀隔离

## 后续任务

- M1-002: Central DB Schema 设计与迁移文件
- M1-003: Store (Tenant) 模型与域名模型
- M1-004: 租户识别中间件 + 路由层重构
