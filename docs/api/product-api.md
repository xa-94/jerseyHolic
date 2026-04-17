# JerseyHolic 商品模块 API 文档

> ✅ Phase M4 已实现。覆盖品类管理、安全映射名称管理、敏感品牌管理、同步监控（Admin）；主商品管理、同步规则、同步触发、同步日志、站点配置（Merchant）。

---

## 目录

1. [Admin 商品管理接口](#1-admin-商品管理接口)
2. [Merchant 商品管理接口](#2-merchant-商品管理接口)
3. [错误码说明](#3-错误码说明)
4. [中间件说明](#4-中间件说明)
5. [实际路由注册摘要](#5-实际路由注册摘要)

---

## 1. Admin 商品管理接口 ✅ Phase M4 已实现

> 前缀：`/api/admin`（Central 路由）  
> 中间件：`api`, `auth:sanctum`, `admin`, `force.json`  
> 认证方式：Sanctum Token（Admin 角色）

---

### 1.1 品类管理

> 管理一级品类（L1）、二级品类（L2），支持 16 种语言名称、特货标识、排序等。

---

#### 1.1.1 获取一级品类列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/product-categories/l1` |
| **描述** | 获取所有一级品类列表，支持分页与筛选 |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `page` | int | 否 | 页码，默认 1 |
| `per_page` | int | 否 | 每页条数，默认 20 |
| `is_sensitive` | boolean | 否 | 按特货标识筛选 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "code": "JERSEY",
        "name": {"en": "Jersey", "zh": "球衣", "...": "..."},
        "icon": "jersey.svg",
        "is_sensitive": true,
        "sensitive_ratio": 0.9,
        "sort_order": 1,
        "created_at": "2026-04-17T00:00:00Z"
      }
    ],
    "total": 6,
    "page": 1,
    "per_page": 20
  }
}
```

---

#### 1.1.2 创建一级品类

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/admin/product-categories/l1` |
| **描述** | 创建新的一级品类 |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `code` | string | 是 | 品类编码（唯一，创建后不可修改） |
| `name` | object | 是 | 多语言名称（JSON，16 种语言） |
| `icon` | string | 否 | 品类图标 |
| `is_sensitive` | boolean | 否 | 是否默认特货，默认 false |
| `sensitive_ratio` | decimal | 否 | 特货比例参考值（0-1） |
| `sort_order` | int | 否 | 排序权重，默认 0 |

**成功响应 `201`**

```json
{
  "code": 0,
  "message": "Category created",
  "data": { "id": 7, "code": "NEW_CAT" }
}
```

---

#### 1.1.3 更新一级品类

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/admin/product-categories/l1/{id}` |
| **描述** | 更新一级品类信息（code 不可修改） |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**请求体**：同创建，`code` 字段忽略。

---

#### 1.1.4 删除一级品类

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/admin/product-categories/l1/{id}` |
| **描述** | 删除一级品类（有关联商品时禁止删除） |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**成功响应 `200`**

```json
{ "code": 0, "message": "Category deleted" }
```

**失败响应 `422`**

```json
{ "code": 4220, "message": "Cannot delete: category has associated products" }
```

---

#### 1.1.5 获取二级品类列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/product-categories/l2` |
| **描述** | 获取二级品类列表，支持按 L1 筛选 |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `category_l1_id` | int | 否 | 按一级品类 ID 筛选 |

---

#### 1.1.6 创建/更新/删除二级品类

| Method | URI | 描述 |
|--------|-----|------|
| `POST` | `/api/admin/product-categories/l2` | 创建二级品类 |
| `PUT` | `/api/admin/product-categories/l2/{id}` | 更新二级品类 |
| `DELETE` | `/api/admin/product-categories/l2/{id}` | 删除二级品类 |

**创建/更新请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `category_l1_id` | int | 是 | 关联一级品类 ID |
| `code` | string | 是 | 二级品类编码 |
| `name` | object | 是 | 多语言名称 |
| `sort_order` | int | 否 | 排序权重 |

---

#### 1.1.7 获取品类树

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/product-categories/tree` |
| **描述** | 获取完整品类树（L1 → L2 层级结构） |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**成功响应 `200`**

```json
{
  "code": 0,
  "data": [
    {
      "id": 1,
      "code": "JERSEY",
      "name": {"en": "Jersey"},
      "is_sensitive": true,
      "children": [
        {"id": 1, "code": "JERSEY-FB", "name": {"en": "Football Jersey"}}
      ]
    }
  ]
}
```

---

### 1.2 安全映射名称管理

> 管理品类级安全名称库，支持 16 种语言的安全名称、权重配置、缓存管理。

---

#### 1.2.1 获取安全名称列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/category-safe-names` |
| **描述** | 获取安全映射名称列表，支持按品类、SKU 前缀筛选 |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `category_l1_id` | int | 否 | 按一级品类筛选 |
| `category_l2_id` | int | 否 | 按二级品类筛选 |
| `sku_prefix` | string | 否 | 按 SKU 前缀筛选 |
| `store_id` | int | 否 | 按站点筛选 |

---

#### 1.2.2 创建安全名称

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/admin/category-safe-names` |
| **描述** | 创建新的品类安全名称映射 |
| **认证** | Admin Token |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `category_l1_id` | int | 是 | 一级品类 ID |
| `category_l2_id` | int | 否 | 二级品类 ID |
| `sku_prefix` | string | 否 | SKU 前缀 |
| `store_id` | int | 否 | 站点 ID（站点级覆盖时使用） |
| `safe_name_en` | string | 是 | 英文安全名称 |
| `safe_name_zh` | string | 否 | 中文安全名称 |
| `safe_name_ja` | string | 否 | 日文安全名称 |
| `...` | string | 否 | 其余 13 种语言安全名称 |
| `weight` | int | 否 | 权重（用于动态轮换选取），默认 1 |

---

#### 1.2.3 更新/删除安全名称

| Method | URI | 描述 |
|--------|-----|------|
| `PUT` | `/api/admin/category-safe-names/{id}` | 更新安全名称 |
| `DELETE` | `/api/admin/category-safe-names/{id}` | 删除安全名称 |

---

#### 1.2.4 清除安全名称缓存

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/admin/category-safe-names/clear-cache` |
| **描述** | 清除 Redis 中的安全名称缓存（修改映射规则后使用） |
| **认证** | Admin Token |

**成功响应 `200`**

```json
{ "code": 0, "message": "Cache cleared" }
```

---

#### 1.2.5 预览安全名称映射

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/admin/category-safe-names/preview` |
| **描述** | 预览指定商品在各场景下的安全名称映射结果 |
| **认证** | Admin Token |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `sku` | string | 是 | 商品 SKU |
| `category_l1_id` | int | 否 | 一级品类 ID |
| `store_id` | int | 否 | 站点 ID |
| `locale` | string | 否 | 语言代码，默认 en |

**成功响应 `200`**

```json
{
  "code": 0,
  "data": {
    "sku": "hic-JUVE-2026-H",
    "matched_level": "category_l2",
    "safe_name": "Athletic Training Jersey",
    "priority_chain": [
      {"level": "exact_product", "matched": false},
      {"level": "sku_prefix", "matched": true, "name": "Sports Team Jersey"},
      {"level": "category_l2", "matched": true, "name": "Athletic Training Jersey"},
      {"level": "category_l1", "matched": true, "name": "Sports Jersey"},
      {"level": "global_fallback", "matched": true, "name": "Fashion Item"}
    ]
  }
}
```

---

### 1.3 敏感品牌管理

> 管理特货品牌黑名单，用于自动特货识别引擎的品牌判定层。

---

#### 1.3.1 获取敏感品牌列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/sensitive-brands` |
| **描述** | 获取敏感品牌列表，支持分页与搜索 |
| **认证** | Admin Token |
| **中间件** | `auth:sanctum`, `admin`, `force.json` |

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `search` | string | 否 | 品牌名搜索 |
| `category_l1_id` | int | 否 | 按品类筛选 |
| `risk_level` | string | 否 | 按风险等级筛选：high/medium/low |
| `page` | int | 否 | 页码 |
| `per_page` | int | 否 | 每页条数 |

---

#### 1.3.2 创建敏感品牌

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/admin/sensitive-brands` |
| **描述** | 添加新的敏感品牌到黑名单 |
| **认证** | Admin Token |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `brand_name` | string | 是 | 品牌名称 |
| `brand_aliases` | array | 否 | 品牌别名列表（JSON），如 `["NK","N1ke"]` |
| `category_l1_id` | int | 否 | 关联品类 |
| `risk_level` | enum | 是 | 风险等级：`high` / `medium` / `low` |
| `reason` | string | 否 | 标记原因 |

---

#### 1.3.3 获取敏感品牌详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/sensitive-brands/{id}` |
| **描述** | 获取指定敏感品牌详情 |

---

#### 1.3.4 更新/删除敏感品牌

| Method | URI | 描述 |
|--------|-----|------|
| `PUT` | `/api/admin/sensitive-brands/{id}` | 更新敏感品牌 |
| `DELETE` | `/api/admin/sensitive-brands/{id}` | 删除敏感品牌 |

---

#### 1.3.5 品牌敏感性检查

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/admin/sensitive-brands/check` |
| **描述** | 检查给定品牌名是否命中敏感品牌库（含别名模糊匹配） |
| **认证** | Admin Token |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `brand_name` | string | 是 | 待检查品牌名称 |

**成功响应 `200`**

```json
{
  "code": 0,
  "data": {
    "is_sensitive": true,
    "matched_brand": "Nike",
    "matched_via": "alias",
    "risk_level": "high"
  }
}
```

---

### 1.4 同步监控

> Admin 端查看商户商品同步状态、健康度和失败记录。

---

#### 1.4.1 同步概览

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/sync-monitor/overview` |
| **描述** | 获取全平台商品同步概览统计 |
| **认证** | Admin Token |

**成功响应 `200`**

```json
{
  "code": 0,
  "data": {
    "total_merchants": 15,
    "total_synced_products": 12345,
    "sync_success_rate": 0.987,
    "last_24h_syncs": 456,
    "last_24h_failures": 6
  }
}
```

---

#### 1.4.2 商户同步统计

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/sync-monitor/merchants/{id}/stats` |
| **描述** | 获取指定商户的同步统计数据 |

---

#### 1.4.3 近期失败记录

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/admin/sync-monitor/recent-failures` |
| **描述** | 获取近期同步失败记录，支持分页 |

---

## 2. Merchant 商品管理接口 ✅ Phase M4 已实现

> 前缀：`/api/merchant`（Central 路由）  
> 中间件：`api`, `auth:sanctum`, `merchant`, `force.json`  
> 认证方式：Sanctum Token（Merchant 角色）

---

### 2.1 主商品管理

> 商户在主商品库（Merchant DB）中统一管理商品，支持 CRUD、批量操作、多语言翻译。

---

#### 2.1.1 获取主商品列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/products` |
| **描述** | 获取当前商户的主商品列表，支持分页、筛选、排序 |
| **认证** | Merchant Token |
| **中间件** | `auth:sanctum`, `merchant`, `force.json` |

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `page` | int | 否 | 页码 |
| `per_page` | int | 否 | 每页条数，默认 20 |
| `search` | string | 否 | 搜索（SKU/名称） |
| `category_l1_id` | int | 否 | 一级品类筛选 |
| `category_l2_id` | int | 否 | 二级品类筛选 |
| `is_sensitive` | boolean | 否 | 特货/普货筛选 |
| `sync_status` | string | 否 | 同步状态：pending/synced/failed |
| `sort_by` | string | 否 | 排序字段：created_at/updated_at/price |
| `sort_dir` | string | 否 | 排序方向：asc/desc |

---

#### 2.1.2 创建主商品

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/products` |
| **描述** | 创建新商品，自动执行特货识别引擎 |
| **认证** | Merchant Token |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `sku` | string | 是 | 商品 SKU（唯一） |
| `name` | string | 是 | 商品名称 |
| `category_l1_id` | int | 是 | 一级品类 ID |
| `category_l2_id` | int | 否 | 二级品类 ID |
| `base_price` | decimal | 是 | 基础价格（USD） |
| `images` | array | 否 | 商品图片 URL 数组 |
| `attributes` | object | 否 | 商品属性（JSON） |
| `variants` | array | 否 | 变体信息（JSON） |
| `translations` | array | 否 | 多语言翻译 `[{locale, title, description}]` |

**成功响应 `201`**

```json
{
  "code": 0,
  "message": "Product created",
  "data": {
    "id": 100,
    "sku": "hic-JUVE-2026-H",
    "is_sensitive": true,
    "sync_status": "pending"
  }
}
```

---

#### 2.1.3 获取主商品详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/products/{id}` |
| **描述** | 获取单个主商品详情（含翻译、变体） |

---

#### 2.1.4 更新主商品

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/merchant/products/{id}` |
| **描述** | 更新主商品信息（SKU 不可修改） |

---

#### 2.1.5 删除主商品

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/merchant/products/{id}` |
| **描述** | 删除主商品（已同步到站点的商品会标记为脱钩，不从站点删除） |

---

#### 2.1.6 批量删除

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/products/batch-delete` |
| **描述** | 批量删除主商品 |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `ids` | array | 是 | 商品 ID 列表 |

---

#### 2.1.7 批量更新状态

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/products/batch-status` |
| **描述** | 批量更新商品上下架状态 |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `ids` | array | 是 | 商品 ID 列表 |
| `status` | string | 是 | 目标状态：active/inactive |

---

### 2.2 同步规则

> 商户为每个站点配置商品同步规则（定价策略、自动同步等）。

---

#### 2.2.1 获取同步规则列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/sync-rules` |
| **描述** | 获取当前商户所有站点的同步规则 |
| **认证** | Merchant Token |

---

#### 2.2.2 创建同步规则

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/sync-rules` |
| **描述** | 为指定站点创建同步规则 |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 是 | 站点 ID |
| `pricing_strategy` | string | 是 | 定价策略：fixed/multiplier/manual |
| `price_multiplier` | decimal | 否 | 价格倍率（pricing_strategy=multiplier 时必填） |
| `auto_sync` | boolean | 否 | 是否开启自动同步，默认 false |
| `sync_interval_hours` | int | 否 | 同步间隔（小时），默认 24 |

---

#### 2.2.3 获取/更新/删除同步规则

| Method | URI | 描述 |
|--------|-----|------|
| `GET` | `/api/merchant/sync-rules/{id}` | 获取单条同步规则 |
| `PUT` | `/api/merchant/sync-rules/{id}` | 更新同步规则 |
| `DELETE` | `/api/merchant/sync-rules/{id}` | 删除同步规则 |

---

### 2.3 同步触发

> 手动或自动触发商品从主商品库同步到站点商品库。

---

#### 2.3.1 单商品同步

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/sync/single/{id}` |
| **描述** | 同步单个主商品到所有已配置站点 |
| **认证** | Merchant Token |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 主商品 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Sync initiated",
  "data": {
    "product_id": 100,
    "stores_targeted": 3,
    "job_id": "sync-abc123"
  }
}
```

---

#### 2.3.2 批量同步

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/sync/batch` |
| **描述** | 批量同步选定商品到指定站点 |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `product_ids` | array | 是 | 主商品 ID 列表 |
| `store_ids` | array | 否 | 目标站点列表（空则同步到所有站点） |

---

#### 2.3.3 全量同步

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/sync/full/{storeId}` |
| **描述** | 将所有主商品全量同步到指定站点（覆盖模式） |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `storeId` | int | 目标站点 ID |

---

#### 2.3.4 增量同步

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/sync/incremental/{storeId}` |
| **描述** | 仅同步自上次同步以来有变更的商品到指定站点（幂等） |

---

### 2.4 同步日志

> 查看商品同步历史记录、统计与趋势，支持失败重试。

---

#### 2.4.1 获取同步日志列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/sync-logs` |
| **描述** | 获取同步日志列表，支持分页与筛选 |
| **认证** | Merchant Token |

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 否 | 按站点筛选 |
| `status` | string | 否 | 按状态筛选：success/failed/pending |
| `date_from` | date | 否 | 起始日期 |
| `date_to` | date | 否 | 截止日期 |

---

#### 2.4.2 站点同步统计

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/sync-logs/stats/{storeId}` |
| **描述** | 获取指定站点的同步统计（成功率、总数等） |

---

#### 2.4.3 同步趋势

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/sync-logs/trend/{storeId}` |
| **描述** | 获取指定站点的同步趋势（按天/周维度） |

---

#### 2.4.4 重试失败同步

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/sync-logs/{id}/retry` |
| **描述** | 重试指定失败的同步记录 |

---

### 2.5 站点配置

> 商户管理每个站点的商品展示配置（价格覆盖、安全名称覆盖、显示货币）。

---

#### 2.5.1 获取站点配置

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/merchant/store-configs/{storeId}` |
| **描述** | 获取指定站点的商品配置 |
| **认证** | Merchant Token |

**成功响应 `200`**

```json
{
  "code": 0,
  "data": {
    "store_id": 1,
    "price_override_enabled": true,
    "price_override_strategy": "multiplier",
    "price_override_value": 1.2,
    "safe_name_override_enabled": false,
    "display_currency": "USD"
  }
}
```

---

#### 2.5.2 更新站点配置

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/merchant/store-configs/{storeId}` |
| **描述** | 更新站点商品配置 |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `price_override_enabled` | boolean | 否 | 是否启用价格覆盖 |
| `price_override_strategy` | string | 否 | 覆盖策略：multiplier/fixed/manual |
| `price_override_value` | decimal | 否 | 覆盖值（策略为 multiplier 时为倍率） |
| `safe_name_override_enabled` | boolean | 否 | 是否启用安全名称覆盖 |
| `display_currency` | string | 否 | 显示货币代码 |

---

#### 2.5.3 预览站点配置效果

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/merchant/store-configs/preview` |
| **描述** | 预览配置变更对商品的影响 |

**请求体**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 是 | 站点 ID |
| `config` | object | 是 | 待预览的配置 |
| `sample_product_ids` | array | 否 | 示例商品 ID 列表 |

---

## 3. 错误码说明

| 错误码 | HTTP 状态 | 说明 |
|--------|----------|------|
| 0 | 200/201 | 成功 |
| 4001 | 401 | 未认证 |
| 4003 | 403 | 无权限 |
| 4004 | 404 | 资源不存在 |
| 4220 | 422 | 参数校验失败 |
| 4221 | 422 | SKU 已存在 |
| 4222 | 422 | 品类存在关联商品，无法删除 |
| 4223 | 422 | 同步规则已存在（同站点不可重复） |
| 4290 | 429 | 请求频率超限 |
| 5000 | 500 | 服务器内部错误 |
| 5001 | 500 | 同步引擎异常 |

---

## 4. 中间件说明

| 中间件 | 说明 |
|--------|------|
| `auth:sanctum` | Laravel Sanctum Token 认证 |
| `admin` | Admin 角色鉴权 |
| `merchant` | Merchant 角色鉴权 |
| `force.json` | 强制 JSON 响应 |
| `set.locale` | 根据请求头/参数设置语言环境 |
| `throttle:api` | API 限流（默认 60 次/分钟） |

---

## 5. 实际路由注册摘要

### Admin 路由（`routes/admin.php`）

```
GET    /api/admin/product-categories/l1           → ProductCategoryController@indexL1
POST   /api/admin/product-categories/l1           → ProductCategoryController@storeL1
PUT    /api/admin/product-categories/l1/{id}      → ProductCategoryController@updateL1
DELETE /api/admin/product-categories/l1/{id}      → ProductCategoryController@destroyL1
GET    /api/admin/product-categories/l2           → ProductCategoryController@indexL2
POST   /api/admin/product-categories/l2           → ProductCategoryController@storeL2
PUT    /api/admin/product-categories/l2/{id}      → ProductCategoryController@updateL2
DELETE /api/admin/product-categories/l2/{id}      → ProductCategoryController@destroyL2
GET    /api/admin/product-categories/tree         → ProductCategoryController@tree

GET    /api/admin/category-safe-names             → CategorySafeNameController@index
POST   /api/admin/category-safe-names             → CategorySafeNameController@store
PUT    /api/admin/category-safe-names/{id}        → CategorySafeNameController@update
DELETE /api/admin/category-safe-names/{id}        → CategorySafeNameController@destroy
POST   /api/admin/category-safe-names/clear-cache → CategorySafeNameController@clearCache
POST   /api/admin/category-safe-names/preview     → CategorySafeNameController@preview

GET    /api/admin/sensitive-brands                → SensitiveBrandController@index
POST   /api/admin/sensitive-brands                → SensitiveBrandController@store
GET    /api/admin/sensitive-brands/{id}           → SensitiveBrandController@show
PUT    /api/admin/sensitive-brands/{id}           → SensitiveBrandController@update
DELETE /api/admin/sensitive-brands/{id}           → SensitiveBrandController@destroy
POST   /api/admin/sensitive-brands/check          → SensitiveBrandController@check

GET    /api/admin/sync-monitor/overview           → SyncMonitorController@overview
GET    /api/admin/sync-monitor/merchants/{id}/stats → SyncMonitorController@merchantStats
GET    /api/admin/sync-monitor/recent-failures    → SyncMonitorController@recentFailures
```

### Merchant 路由（`routes/merchant.php`）

```
GET    /api/merchant/products                     → MasterProductController@index
POST   /api/merchant/products                     → MasterProductController@store
GET    /api/merchant/products/{id}                → MasterProductController@show
PUT    /api/merchant/products/{id}                → MasterProductController@update
DELETE /api/merchant/products/{id}                → MasterProductController@destroy
POST   /api/merchant/products/batch-delete        → MasterProductController@batchDelete
POST   /api/merchant/products/batch-status        → MasterProductController@batchStatus

GET    /api/merchant/sync-rules                   → SyncRuleController@index
POST   /api/merchant/sync-rules                   → SyncRuleController@store
GET    /api/merchant/sync-rules/{id}              → SyncRuleController@show
PUT    /api/merchant/sync-rules/{id}              → SyncRuleController@update
DELETE /api/merchant/sync-rules/{id}              → SyncRuleController@destroy

POST   /api/merchant/sync/single/{id}            → ProductSyncController@syncSingle
POST   /api/merchant/sync/batch                   → ProductSyncController@syncBatch
POST   /api/merchant/sync/full/{storeId}          → ProductSyncController@syncFull
POST   /api/merchant/sync/incremental/{storeId}   → ProductSyncController@syncIncremental

GET    /api/merchant/sync-logs                    → SyncLogController@index
GET    /api/merchant/sync-logs/stats/{storeId}    → SyncLogController@stats
GET    /api/merchant/sync-logs/trend/{storeId}    → SyncLogController@trend
POST   /api/merchant/sync-logs/{id}/retry         → SyncLogController@retry

GET    /api/merchant/store-configs/{storeId}      → StoreProductConfigController@show
PUT    /api/merchant/store-configs/{storeId}      → StoreProductConfigController@update
POST   /api/merchant/store-configs/preview        → StoreProductConfigController@preview
```

**路由总计**：Admin 22 条 + Merchant 20 条 = **42 条路由**
