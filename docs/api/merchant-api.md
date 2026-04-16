# JerseyHolic 商户 API 文档

> Phase M2 产出。覆盖商户管理、站点管理、商户认证、API 密钥、子账号管理、仪表盘及订单查询全部端点。

---

## 目录

1. [Admin 商户管理 API](#1-admin-商户管理-api)
2. [Admin 站点管理 API](#2-admin-站点管理-api)
3. [商户认证 API](#3-商户认证-api)
4. [商户 API 密钥管理](#4-商户-api-密钥管理)
5. [商户子账号管理](#5-商户子账号管理)
6. [商户仪表盘 & 订单](#6-商户仪表盘--订单)
7. [错误码说明](#7-错误码说明)
8. [认证说明](#8-认证说明)

---

## 1. Admin 商户管理 API

**路由前缀：** `/api/v1/admin/merchants`  
**认证方式：** Bearer Token（admin guard — `auth:sanctum`）  
**中间件：** `auth:sanctum`, `force.json`, `central.only`

---

### 1.1 获取商户列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/merchants` |
| **描述** | 分页查询平台全部商户，支持关键词、状态、等级筛选 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `keyword` | string | 否 | 按商户名 / 邮箱 / 联系人模糊搜索 |
| `status` | string | 否 | 状态筛选：`pending` \| `active` \| `rejected` \| `info_required` \| `suspended` \| `banned` |
| `level` | string | 否 | 等级筛选：`starter` \| `standard` \| `advanced` \| `vip` |
| `per_page` | int | 否 | 每页条数，默认 15 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": 1,
        "merchant_name": "Demo Store",
        "email": "merchant@example.com",
        "contact_name": "张三",
        "phone": "+8613800138000",
        "level": "standard",
        "status": "active",
        "created_at": "2025-01-01T00:00:00+00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "per_page": 15
  }
}
```

---

### 1.2 获取商户详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/merchants/{id}` |
| **描述** | 返回指定商户的完整信息 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 商户 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "merchant_name": "Demo Store",
    "email": "merchant@example.com",
    "contact_name": "张三",
    "phone": "+8613800138000",
    "level": "standard",
    "status": "active",
    "created_at": "2025-01-01T00:00:00+00:00",
    "updated_at": "2025-06-01T12:00:00+00:00"
  }
}
```

**错误响应 `404`**

```json
{
  "code": 404,
  "message": "Not Found"
}
```

---

### 1.3 创建商户（管理员手动创建）

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/merchants` |
| **描述** | 管理员手动创建商户账号，初始状态为 `pending` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `merchant_name` | string | 是 | `required\|string\|max:100` | 商户名称 |
| `email` | string | 是 | `required\|email\|max:255\|unique:jh_merchants,email` | 邮箱（唯一） |
| `password` | string | 是 | `required\|string\|min:8` | 初始密码，至少 8 位 |
| `contact_name` | string | 是 | `required\|string\|max:100` | 联系人姓名 |
| `phone` | string | 否 | `nullable\|string\|max:30` | 手机号 |
| `level` | string | 否 | `sometimes\|string\|in:starter,standard,advanced,vip` | 商户等级，默认 `starter` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "商户创建成功",
  "data": {
    "id": 2,
    "merchant_name": "New Shop",
    "email": "newshop@example.com",
    "contact_name": "李四",
    "level": "starter",
    "status": "pending"
  }
}
```

**错误响应 `422`**

```json
{
  "code": 42200,
  "message": "该邮箱已被注册",
  "data": {
    "email": ["该邮箱已被注册"]
  }
}
```

---

### 1.4 更新商户信息

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/merchants/{id}` |
| **描述** | 更新商户基本信息，所有字段均为可选 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 商户 ID |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `merchant_name` | string | 否 | `sometimes\|string\|max:100` | 商户名称 |
| `email` | string | 否 | `sometimes\|email\|max:255\|unique:jh_merchants,email,{id}` | 邮箱 |
| `password` | string | 否 | `sometimes\|string\|min:8` | 密码 |
| `contact_name` | string | 否 | `sometimes\|string\|max:100` | 联系人姓名 |
| `phone` | string | 否 | `nullable\|string\|max:30` | 手机号 |
| `level` | string | 否 | `sometimes\|string\|in:starter,standard,advanced,vip` | 商户等级 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "商户信息已更新",
  "data": {
    "id": 1,
    "merchant_name": "Updated Store",
    "email": "updated@example.com",
    "level": "standard",
    "status": "active"
  }
}
```

---

### 1.5 变更商户状态

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/merchants/{id}/status` |
| **描述** | 变更指定商户的状态（F-MCH-013） |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 商户 ID |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `status` | string | 是 | `required\|string\|in:pending,active,rejected,info_required,suspended,banned` | 目标状态 |
| `reason` | string | 否 | `nullable\|string\|max:500` | 变更原因 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "商户状态已更新",
  "data": {
    "id": 1,
    "status": "suspended",
    "merchant_name": "Demo Store"
  }
}
```

---

### 1.6 调整商户等级

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/merchants/{id}/level` |
| **描述** | 调整商户等级，同时返回新等级对应的站点数量上限（F-MCH-014） |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 商户 ID |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `level` | string | 是 | `required\|string\|in:starter,standard,advanced,vip` | 目标等级 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "商户等级已调整为 standard，站点上限：5",
  "data": {
    "id": 1,
    "level": "standard",
    "store_limit": "5"
  }
}
```

---

### 1.7 审核商户

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/merchants/{id}/review` |
| **描述** | 对处于 `pending` 状态的商户执行审核操作 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 商户 ID |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `action` | string | 是 | `required\|string\|in:approve,reject,request_info` | 审核操作：通过 / 拒绝 / 要求补充信息 |
| `comment` | string | 否 | `nullable\|string\|max:1000` | 审核意见 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "审核操作成功：已审核通过",
  "data": {
    "id": 1,
    "status": "active",
    "merchant_name": "Demo Store"
  }
}
```

---

### 1.8 删除商户

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/merchants/{id}` |
| **描述** | 软删除指定商户 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 商户 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "商户已删除",
  "data": null
}
```

---

## 2. Admin 站点管理 API

**路由前缀：** `/api/v1/admin/stores`  
**认证方式：** Bearer Token（admin guard — `auth:sanctum`）  
**中间件：** `auth:sanctum`, `force.json`, `central.only`

---

### 2.1 获取站点列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/stores` |
| **描述** | 分页查询全部站点，支持按商户、状态筛选 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `merchant_id` | int | 否 | 按商户筛选 |
| `status` | int | 否 | `0`=inactive, `1`=active, `2`=maintenance |
| `per_page` | int | 否 | 每页条数，默认 15 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": 1,
        "merchant_id": 1,
        "store_name": "JerseyHolic US",
        "store_code": "jh-us",
        "domain": "us.jerseyholic.com",
        "status": 1,
        "created_at": "2025-01-01T00:00:00+00:00"
      }
    ],
    "total": 50,
    "page": 1,
    "per_page": 15
  }
}
```

---

### 2.2 获取站点详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/stores/{id}` |
| **描述** | 返回指定站点的完整配置信息 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 站点 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "merchant_id": 1,
    "store_name": "JerseyHolic US",
    "store_code": "jh-us",
    "domain": "us.jerseyholic.com",
    "status": 1,
    "target_markets": ["US", "CA"],
    "supported_languages": ["en"],
    "supported_currencies": ["USD"],
    "product_categories": ["jerseys", "accessories"],
    "payment_preferences": [],
    "logistics_config": {},
    "theme_config": {}
  }
}
```

---

### 2.3 创建站点

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/stores` |
| **描述** | 为指定商户创建新站点（M2-003） |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `merchant_id` | int | 是 | `required\|integer\|exists:jh_merchants,id` | 所属商户 ID |
| `store_name` | string | 是 | `required\|string\|max:100` | 站点名称 |
| `store_code` | string | 是 | `required\|string\|max:50\|unique:stores,store_code` | 站点唯一编码 |
| `domain` | string | 是 | `required\|string\|max:255` | 主域名 |
| `target_markets` | array | 否 | `nullable\|array` | 目标市场列表，如 `["US","CA"]` |
| `supported_languages` | array | 否 | `nullable\|array` | 支持语言，如 `["en","zh"]` |
| `supported_currencies` | array | 否 | `nullable\|array` | 支持货币，如 `["USD","CNY"]` |
| `product_categories` | array | 否 | `nullable\|array` | 产品分类 |
| `payment_preferences` | array | 否 | `nullable\|array` | 支付偏好配置 |
| `logistics_config` | array | 否 | `nullable\|array` | 物流配置 |
| `theme_config` | array | 否 | `nullable\|array` | 主题配置 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "站点创建成功",
  "data": {
    "id": 10,
    "merchant_id": 1,
    "store_name": "JerseyHolic UK",
    "store_code": "jh-uk",
    "domain": "uk.jerseyholic.com",
    "status": 1
  }
}
```

**错误响应 `422`**

```json
{
  "code": 42200,
  "message": "该站点编码已被占用",
  "data": {
    "store_code": ["该站点编码已被占用"]
  }
}
```

---

### 2.4 更新站点信息

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/stores/{id}` |
| **描述** | 更新站点基本信息及配置，所有字段均为可选 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 站点 ID |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `store_name` | string | 否 | `sometimes\|string\|max:100` | 站点名称 |
| `store_code` | string | 否 | `sometimes\|string\|max:50\|unique:stores,store_code,{id}` | 站点编码 |
| `target_markets` | array | 否 | `nullable\|array` | 目标市场 |
| `supported_languages` | array | 否 | `nullable\|array` | 支持语言 |
| `supported_currencies` | array | 否 | `nullable\|array` | 支持货币 |
| `product_categories` | array | 否 | `nullable\|array` | 产品分类 |
| `payment_preferences` | array | 否 | `nullable\|array` | 支付偏好 |
| `logistics_config` | array | 否 | `nullable\|array` | 物流配置 |
| `theme_config` | array | 否 | `nullable\|array` | 主题配置 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "站点信息已更新",
  "data": {
    "id": 1,
    "store_name": "JerseyHolic US v2",
    "store_code": "jh-us",
    "status": 1
  }
}
```

---

### 2.5 变更站点状态

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/status` |
| **描述** | 切换站点运行状态 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `status` | string | 是 | `required\|string\|in:active,maintenance,inactive` | 目标状态 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "站点状态已更新",
  "data": {
    "id": 1,
    "status": "maintenance"
  }
}
```

---

### 2.6 删除站点

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/stores/{id}` |
| **描述** | 软删除站点并标记待清理 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "站点已删除",
  "data": null
}
```

---

### 2.7 更新产品分类

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/categories` |
| **描述** | 替换站点的产品分类列表 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `categories` | array | 是 | `required\|array` | 分类列表，如 `["jerseys","accessories"]` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "产品分类已更新",
  "data": { "id": 1, "product_categories": ["jerseys", "accessories"] }
}
```

---

### 2.8 更新目标市场

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/markets` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `markets` | array | 是 | `required\|array` | 市场列表，如 `["US","GB","AU"]` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "目标市场已更新",
  "data": { "id": 1, "target_markets": ["US", "GB", "AU"] }
}
```

---

### 2.9 更新支持语言

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/languages` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `languages` | array | 是 | `required\|array` | 语言列表，如 `["en","zh","fr"]` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "支持语言已更新",
  "data": { "id": 1, "supported_languages": ["en", "zh"] }
}
```

---

### 2.10 更新支持货币

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/currencies` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `currencies` | array | 是 | `required\|array` | 货币列表，如 `["USD","EUR","CNY"]` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "支持货币已更新",
  "data": { "id": 1, "supported_currencies": ["USD", "EUR"] }
}
```

---

### 2.11 更新关联支付账号

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/payment-accounts` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `account_ids` | array | 是 | `required\|array` | 支付账号 ID 列表 |
| `account_ids.*` | int | 是 | `integer\|exists:payment_accounts,id` | 每个 ID 必须存在于 payment_accounts 表 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "支付账号关联已更新",
  "data": { "id": 1, "payment_account_ids": [3, 7] }
}
```

---

### 2.12 更新物流配置

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/stores/{id}/logistics` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `config` | object | 是 | `required\|array` | 物流配置对象（格式由物流供应商决定） |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "物流配置已更新",
  "data": { "id": 1, "logistics_config": { "provider": "DHL", "api_key": "xxx" } }
}
```

---

### 2.13 添加域名

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/stores/{id}/domains` |
| **描述** | 为站点绑定额外域名（stancl/tenancy 域名表同步） |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `domain` | string | 是 | `required\|string\|max:255\|unique:domains,domain` | 域名，须全局唯一 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "域名已添加",
  "data": {
    "id": 5,
    "domain": "shop2.jerseyholic.com",
    "tenant_id": "store_10"
  }
}
```

**错误响应 `422`**

```json
{
  "code": 42200,
  "message": "The domain has already been taken.",
  "data": { "domain": ["The domain has already been taken."] }
}
```

---

### 2.14 移除域名

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/stores/{id}/domains/{domainId}` |
| **描述** | 删除站点的指定绑定域名 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 站点 ID |
| `domainId` | int | 域名记录 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "域名已移除",
  "data": null
}
```

---

## 3. 商户认证 API

### 3.1 商户注册

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/register` |
| **描述** | 商户公开自助注册，初始状态 `pending`，须平台审核后激活 |
| **认证方式** | 无（公开端点） |
| **中间件** | `force.json` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `merchant_name` | string | 是 | `required\|string\|max:100` | 商户名称 |
| `email` | string | 是 | `required\|email\|max:255` | 邮箱（全局唯一） |
| `password` | string | 是 | `required\|string\|min:8` | 密码，至少 8 位 |
| `contact_name` | string | 是 | `required\|string\|max:100` | 联系人姓名 |
| `phone` | string | 否 | `nullable\|string\|max:30` | 手机号 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "注册成功，请等待平台审核。",
  "data": {
    "id": 5,
    "merchant_name": "My Jersey Store",
    "email": "owner@mystore.com",
    "status": "pending"
  }
}
```

**错误响应 `422`**

```json
{
  "code": 42200,
  "message": "该邮箱已被注册",
  "data": {
    "email": ["该邮箱已被注册"]
  }
}
```

---

### 3.2 商户登录

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/auth/login` |
| **描述** | 商户用户登录，支持 email 或 username，失败 5 次锁定账号 15 分钟 |
| **认证方式** | 无（公开端点） |
| **中间件** | `force.json` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `login` | string | 是 | `required\|string` | 邮箱或用户名 |
| `password` | string | 是 | `required\|string` | 密码 |
| `remember` | bool | 否 | `boolean` | 记住登录，默认 `false` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz...",
    "token_type": "Bearer",
    "user": {
      "id": 10,
      "merchant_id": 1,
      "username": "owner",
      "email": "owner@mystore.com",
      "name": "张老板",
      "role": "owner",
      "allowed_store_ids": null,
      "last_login_at": "2025-06-01T08:00:00+00:00"
    },
    "merchant": {
      "id": 1,
      "merchant_name": "My Jersey Store",
      "level": "standard",
      "status": "active"
    }
  }
}
```

**错误响应 `401`（账号或密码错误）**

```json
{
  "code": 401,
  "message": "账号或密码错误"
}
```

**错误响应 `423`（账号被锁定）**

```json
{
  "code": 423,
  "message": "账号已被锁定，请 12 分钟后再试"
}
```

**错误响应 `403`（账号被禁用）**

```json
{
  "code": 403,
  "message": "账号已被禁用，请联系平台管理员"
}
```

---

### 3.3 商户登出

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/auth/logout` |
| **描述** | 撤销当前 Bearer Token |
| **认证方式** | Bearer Token（`auth:merchant`） |
| **中间件** | `auth:merchant`, `force.json`, `central.only` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "已登出",
  "data": null
}
```

---

### 3.4 获取当前用户信息

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/auth/me` |
| **描述** | 返回当前登录商户用户信息、所属商户及可访问的站点列表 |
| **认证方式** | Bearer Token（`auth:merchant`） |
| **中间件** | `auth:merchant`, `force.json`, `central.only` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "user": {
      "id": 10,
      "merchant_id": 1,
      "username": "owner",
      "email": "owner@mystore.com",
      "name": "张老板",
      "phone": "+8613800138000",
      "avatar": null,
      "role": "owner",
      "allowed_store_ids": null,
      "last_login_at": "2025-06-01T08:00:00+00:00"
    },
    "merchant": {
      "id": 1,
      "merchant_name": "My Jersey Store",
      "level": "standard",
      "status": "active"
    },
    "stores": [
      {
        "id": 1,
        "store_name": "JerseyHolic US",
        "domain": "us.jerseyholic.com",
        "status": 1
      }
    ]
  }
}
```

---

### 3.5 刷新 Token

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/auth/refresh` |
| **描述** | 撤销当前 Token 并生成新 Token（旧 Token 立即失效） |
| **认证方式** | Bearer Token（`auth:merchant`） |
| **中间件** | `auth:merchant`, `force.json`, `central.only` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Token 已刷新",
  "data": {
    "token": "2|NewTokenAbCdEfGhIjKl...",
    "token_type": "Bearer"
  }
}
```

---

## 4. 商户 API 密钥管理

**路由前缀：** `/api/v1/merchant/api-keys`  
**认证方式：** Bearer Token（`auth:merchant`）  
**中间件：** `auth:merchant`, `force.json`, `central.only`  
**说明：** 使用 RSA-4096 非对称密钥对，私钥仅允许通过 `download_token` 一次性下载，系统不做持久化存储。

---

### 4.1 列出密钥

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/api-keys` |
| **描述** | 列出当前商户所有 API 密钥（不含私钥与 download_token） |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "key_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "algorithm": "RSA",
        "key_size": 4096,
        "status": "active",
        "public_key": "-----BEGIN PUBLIC KEY-----\nMII...\n-----END PUBLIC KEY-----",
        "store_id": null,
        "activated_at": "2025-06-01T00:00:00+00:00",
        "expires_at": "2026-06-01T00:00:00+00:00",
        "revoked_at": null,
        "revoke_reason": null,
        "downloaded_at": "2025-06-01T00:01:00+00:00",
        "is_downloaded": true,
        "created_at": "2025-06-01T00:00:00+00:00"
      }
    ]
  }
}
```

---

### 4.2 生成新密钥对

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/api-keys` |
| **描述** | 生成新 RSA-4096 密钥对，返回 `download_token`（仅出现一次，需立即下载私钥） |

**请求体 `application/json`**（可选）

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 否 | 关联到指定站点，不填则为商户级密钥 |

**成功响应 `201`**

```json
{
  "code": 0,
  "message": "Key pair generated successfully. Download the private key immediately using the download_token — it will not be shown again.",
  "data": {
    "key_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "algorithm": "RSA",
    "key_size": 4096,
    "status": "active",
    "public_key": "-----BEGIN PUBLIC KEY-----\nMII...\n-----END PUBLIC KEY-----",
    "download_token": "64位十六进制字符串（仅此一次）",
    "store_id": null,
    "expires_at": "2026-06-01T00:00:00+00:00",
    "created_at": "2025-06-01T00:00:00+00:00"
  }
}
```

**错误响应 `422`**（store_id 不属于该商户）

```json
{
  "code": 422,
  "message": "Store not found or does not belong to this merchant."
}
```

---

### 4.3 下载私钥（一次性）

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/api-keys/download` |
| **描述** | 凭生成密钥时返回的 `download_token`（64位十六进制）一次性下载加密私钥，下载后 token 立即失效 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `download_token` | string | 是 | `required\|string\|size:64` | 64 位十六进制 download token |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Private key downloaded successfully. This token is now invalidated and cannot be used again.",
  "data": {
    "key_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "private_key": "-----BEGIN RSA PRIVATE KEY-----\nMII...\n-----END RSA PRIVATE KEY-----"
  }
}
```

**错误响应 `410`**（Token 已使用或过期）

```json
{
  "code": 410,
  "message": "Download token has expired or already been used."
}
```

**错误响应 `404`**（Token 不存在）

```json
{
  "code": 404,
  "message": "Download token not found."
}
```

---

### 4.4 获取密钥详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/api-keys/{keyId}` |
| **描述** | 获取指定密钥信息（不含私钥） |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `keyId` | string | 密钥 UUID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "algorithm": "RSA",
    "key_size": 4096,
    "status": "active",
    "public_key": "-----BEGIN PUBLIC KEY-----\nMII...\n-----END PUBLIC KEY-----",
    "store_id": null,
    "activated_at": "2025-06-01T00:00:00+00:00",
    "expires_at": "2026-06-01T00:00:00+00:00",
    "revoked_at": null,
    "revoke_reason": null,
    "downloaded_at": "2025-06-01T00:01:00+00:00",
    "is_downloaded": true,
    "created_at": "2025-06-01T00:00:00+00:00"
  }
}
```

---

### 4.5 轮换密钥

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/api-keys/{keyId}/rotate` |
| **描述** | 轮换指定密钥：旧密钥进入 24 小时宽限期（Grace Period），新密钥即时激活。宽限期内新旧密钥均有效。 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `keyId` | string | 待轮换的密钥 UUID |

**成功响应 `201`**

```json
{
  "code": 0,
  "message": "Key rotated successfully. The old key will expire in 24 hours. Download the new private key immediately.",
  "data": {
    "old_key": {
      "key_id": "old-key-uuid",
      "status": "grace_period",
      "expires_at": "2025-06-02T00:00:00+00:00"
    },
    "new_key": {
      "key_id": "new-key-uuid",
      "algorithm": "RSA",
      "key_size": 4096,
      "status": "active",
      "public_key": "-----BEGIN PUBLIC KEY-----\nMII...\n-----END PUBLIC KEY-----",
      "download_token": "64位十六进制字符串（仅此一次）"
    }
  }
}
```

**错误响应 `422`**（密钥已吊销或已过期）

```json
{
  "code": 422,
  "message": "Revoked keys cannot be rotated."
}
```

---

### 4.6 吊销密钥

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/merchant/api-keys/{keyId}` |
| **描述** | 立即吊销指定密钥，吊销后不可恢复 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `keyId` | string | 密钥 UUID |

**请求体 `application/json`**（可选）

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `reason` | string | 否 | 吊销原因，默认 `"Revoked by merchant."` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Key revoked successfully.",
  "data": {
    "key_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "status": "revoked",
    "revoked_at": "2025-06-15T09:30:00+00:00",
    "revoke_reason": "Revoked by merchant."
  }
}
```

**错误响应 `422`**（密钥已吊销）

```json
{
  "code": 422,
  "message": "Key is already revoked."
}
```

---

## 5. 商户子账号管理

**路由前缀：** `/api/v1/merchant/users`  
**认证方式：** Bearer Token（`auth:merchant`）  
**中间件：** `auth:merchant`, `force.json`, `central.only`

**权限说明：**
- `owner`：可管理所有用户（owner / manager / operator）
- `manager`：只能创建、管理和删除 `operator` 角色的用户
- `operator`：无用户管理权限，所有管理端点返回 `403`

---

### 5.1 列出子账号

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/users` |
| **描述** | 列出当前商户下所有子账号，仅 `owner` / `manager` 可访问 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `role` | string | 否 | 按角色筛选：`owner` \| `manager` \| `operator` |
| `status` | int | 否 | `0`=禁用, `1`=启用 |
| `per_page` | int | 否 | 每页条数，默认 15 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": 10,
        "merchant_id": 1,
        "username": "operator1",
        "email": "op1@mystore.com",
        "name": "操作员甲",
        "phone": null,
        "avatar": null,
        "role": "operator",
        "status": 1,
        "allowed_store_ids": [1, 2],
        "login_failures": 0,
        "locked_until": null,
        "last_login_at": "2025-06-01T09:00:00+00:00",
        "created_at": "2025-01-01T00:00:00+00:00",
        "updated_at": "2025-06-01T09:00:00+00:00"
      }
    ],
    "total": 5,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

**错误响应 `403`**

```json
{
  "code": 403,
  "message": "权限不足，仅 owner/manager 可查看用户列表"
}
```

---

### 5.2 创建子账号

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/users` |
| **描述** | 创建商户子账号，`manager` 只能创建 `operator` 角色 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `username` | string | 是 | `required\|string\|max:64` | 用户名 |
| `email` | string | 是 | `required\|email\|max:255` | 邮箱 |
| `password` | string | 是 | `required\|string\|min:8\|max:128` | 密码 |
| `name` | string | 是 | `required\|string\|max:100` | 真实姓名 |
| `phone` | string | 否 | `nullable\|string\|max:32` | 手机号 |
| `role` | string | 否 | `nullable\|string\|in:owner,manager,operator` | 角色，默认 `operator` |
| `status` | int | 否 | `nullable\|integer\|in:0,1` | `0`=禁用, `1`=启用，默认 `1` |
| `allowed_store_ids` | array | 否 | `nullable\|array` | 可访问站点 ID 列表，`null` 表示全站点 |
| `allowed_store_ids.*` | int | 否 | `integer` | 站点 ID 元素 |

**成功响应 `201`**

```json
{
  "code": 0,
  "message": "用户创建成功",
  "data": {
    "id": 20,
    "merchant_id": 1,
    "username": "newop",
    "email": "newop@mystore.com",
    "name": "新操作员",
    "role": "operator",
    "status": 1,
    "allowed_store_ids": [1],
    "created_at": "2025-06-15T10:00:00+00:00"
  }
}
```

---

### 5.3 获取子账号详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/users/{id}` |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 用户 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 20,
    "merchant_id": 1,
    "username": "newop",
    "email": "newop@mystore.com",
    "name": "新操作员",
    "phone": null,
    "avatar": null,
    "role": "operator",
    "status": 1,
    "allowed_store_ids": [1],
    "login_failures": 0,
    "locked_until": null,
    "last_login_at": null,
    "created_at": "2025-06-15T10:00:00+00:00",
    "updated_at": "2025-06-15T10:00:00+00:00"
  }
}
```

---

### 5.4 更新子账号信息

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/merchant/users/{id}` |
| **描述** | 更新子账号信息，`manager` 只能修改 `operator`，且不能将角色提升为非 `operator` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `username` | string | 否 | `sometimes\|string\|max:64` | 用户名 |
| `email` | string | 否 | `sometimes\|email\|max:255` | 邮箱 |
| `name` | string | 否 | `sometimes\|string\|max:100` | 真实姓名 |
| `phone` | string | 否 | `nullable\|string\|max:32` | 手机号 |
| `role` | string | 否 | `sometimes\|string\|in:owner,manager,operator` | 角色 |
| `status` | int | 否 | `sometimes\|integer\|in:0,1` | 状态 |
| `allowed_store_ids` | array | 否 | `nullable\|array` | 可访问站点 ID 列表 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "用户更新成功",
  "data": {
    "id": 20,
    "username": "newop",
    "role": "operator",
    "status": 1
  }
}
```

---

### 5.5 删除子账号

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/merchant/users/{id}` |
| **描述** | 软删除子账号，`manager` 只能删除 `operator` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "用户已删除",
  "data": null
}
```

---

### 5.6 修改用户密码

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/merchant/users/{id}/password` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `password` | string | 是 | `required\|string\|min:8\|max:128` | 新密码 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "密码已更新",
  "data": null
}
```

---

### 5.7 更新站点访问权限

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/merchant/users/{id}/permissions` |
| **描述** | 更新用户可访问的站点列表，`null` 表示允许访问全部站点 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `allowed_store_ids` | array\|null | 是 | `nullable\|array` | 站点 ID 列表，`null` 表示全站点访问；字段必须显式传入 |
| `allowed_store_ids.*` | int | 否 | `integer` | 站点 ID 元素 |

> 注意：`allowed_store_ids` 字段必须存在于请求体中（即使值为 `null`），否则返回 `422`。

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "站点权限已更新",
  "data": {
    "id": 20,
    "allowed_store_ids": [1, 3]
  }
}
```

---

### 5.8 解锁用户

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/merchant/users/{id}/unlock` |
| **描述** | 重置用户登录失败次数，解除登录锁定 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "用户已解锁",
  "data": null
}
```

---

## 6. 商户仪表盘 & 订单

**认证方式：** Bearer Token（`auth:merchant`）  
**中间件：** `auth:merchant`, `force.json`, `central.only`  
**说明：** 仪表盘和订单数据需访问 Tenant DB（通过 `Store::run()` 跨数据库查询），`operator` 角色受 `allowed_store_ids` 限制。

---

### 6.1 仪表盘概览

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/dashboard` |
| **描述** | 获取当前商户所有可访问站点的跨站聚合统计（今日/本周/本月订单量与销售额） |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "merchant": {
      "id": 1,
      "name": "My Jersey Store"
    },
    "stores_summary": [
      {
        "store_id": 1,
        "store_name": "JerseyHolic US",
        "store_code": "jh-us",
        "domain": "us.jerseyholic.com",
        "status": 1,
        "orders_today": 12,
        "orders_week": 87,
        "orders_month": 320,
        "revenue_today": 1580.00,
        "revenue_week": 11200.00,
        "revenue_month": 42000.00,
        "pending_orders": 5
      }
    ],
    "totals": {
      "orders_today": 12,
      "orders_week": 87,
      "orders_month": 320,
      "revenue_today": 1580.00,
      "revenue_week": 11200.00,
      "revenue_month": 42000.00,
      "pending_orders": 5
    }
  }
}
```

> 若某个站点数据库查询失败，该站点数据降级为 0 并附带 `"error"` 字段，不影响其他站点。

---

### 6.2 可访问站点列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/stores` |
| **描述** | 返回当前用户可访问的站点列表，供前端站点切换器使用 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "list": [
    {
      "store_id": 1,
      "store_name": "JerseyHolic US",
      "store_code": "jh-us",
      "domain": "us.jerseyholic.com",
      "status": 1
    },
    {
      "store_id": 2,
      "store_name": "JerseyHolic UK",
      "store_code": "jh-uk",
      "domain": "uk.jerseyholic.com",
      "status": 1
    }
  ]
}
```

---

### 6.3 订单列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/orders` |
| **描述** | 查询订单列表。指定 `store_id` 时为单站点分页查询；不指定时聚合全部可访问站点（每站最多 200 条后分页） |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 否 | 限定单个站点（不传则聚合查询） |
| `status` | int | 否 | 支付状态：`0`=unpaid, `1`=pending, `2`=paid, `3`=shipped, `4`=completed, `5`=refunded |
| `date_from` | string | 否 | 开始日期，格式 `Y-m-d`，如 `2025-06-01` |
| `date_to` | string | 否 | 结束日期，格式 `Y-m-d` |
| `page` | int | 否 | 页码，默认 1 |
| `per_page` | int | 否 | 每页条数，默认 20，最大 100 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "list": [
    {
      "id": 1001,
      "order_no": "ORD-20250601-001",
      "a_order_no": "EXT-REF-001",
      "customer_name": "John Doe",
      "customer_email": "john@example.com",
      "total": 129.99,
      "currency": "USD",
      "pay_status": 2,
      "shipment_status": 1,
      "pay_time": "2025-06-01T10:30:00+00:00",
      "created_at": "2025-06-01T10:00:00+00:00",
      "store_id": 1,
      "store_name": "JerseyHolic US"
    }
  ],
  "meta": {
    "total": 320,
    "per_page": 20,
    "current_page": 1,
    "last_page": 16
  }
}
```

**错误响应 `403`**（无权访问该站点）

```json
{
  "code": 403,
  "message": "Access denied"
}
```

---

### 6.4 订单详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/orders/{id}` |
| **描述** | 获取订单详情（含订单项、收货地址、账单地址、历史记录）。必须传入 `store_id`，因订单数据存于 Tenant DB。 |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `id` | int | 订单 ID（Tenant DB 中的主键） |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 是 | 订单所属站点 ID（必填） |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1001,
    "order_no": "ORD-20250601-001",
    "a_order_no": "EXT-REF-001",
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "total": 129.99,
    "currency": "USD",
    "pay_status": 2,
    "shipment_status": 1,
    "pay_time": "2025-06-01T10:30:00+00:00",
    "created_at": "2025-06-01T10:00:00+00:00",
    "items": [
      {
        "id": 1,
        "product_name": "Argentina Home Jersey",
        "quantity": 1,
        "price": 129.99
      }
    ],
    "shippingAddress": {
      "name": "John Doe",
      "address1": "123 Main St",
      "city": "New York",
      "country": "US"
    },
    "billingAddress": null,
    "histories": [],
    "store_id": 1,
    "store_name": "JerseyHolic US"
  }
}
```

**错误响应 `422`**（未传 store_id）

```json
{
  "code": 422,
  "message": "store_id is required"
}
```

**错误响应 `404`**（订单不存在）

```json
{
  "code": 404,
  "message": "Order not found"
}
```

---

## 7. 错误码说明

### 7.1 通用 HTTP 状态码

| HTTP 状态码 | 含义 | 场景 |
|-------------|------|------|
| `200` | 请求成功 | 标准成功响应 |
| `201` | 创建成功 | 资源创建（如生成密钥对） |
| `401` | 未认证 | Token 缺失或无效 |
| `403` | 权限不足 | 角色不满足要求 / 跨商户越权 |
| `404` | 资源不存在 | 指定 ID 记录不存在 |
| `410` | 资源已失效 | Download token 已使用或过期 |
| `422` | 参数验证失败 | 请求体字段不合法 |
| `423` | 账号锁定 | 登录失败次数达到上限 |
| `500` | 服务器错误 | 内部异常（如密钥生成失败） |

### 7.2 业务错误码（`code` 字段）

| code | 说明 |
|------|------|
| `0` | 成功 |
| `401` | 账号或密码错误 / Token 无效 |
| `403` | 权限不足 |
| `404` | 资源不存在 |
| `410` | Download token 已失效 |
| `422` | 参数验证失败（无 FormRequest 场景） |
| `423` | 账号已锁定 |
| `42200` | FormRequest 验证失败（含详细字段错误） |
| `500` | 服务器内部错误 |

### 7.3 商户状态枚举

| 值 | 说明 |
|----|------|
| `pending` | 待审核（注册初始状态） |
| `active` | 已激活 |
| `rejected` | 已拒绝 |
| `info_required` | 需补充信息 |
| `suspended` | 已暂停 |
| `banned` | 已封禁 |

### 7.4 商户等级枚举

| 值 | 说明 |
|----|------|
| `starter` | 入门级 |
| `standard` | 标准级 |
| `advanced` | 高级 |
| `vip` | VIP |

### 7.5 订单支付状态（`pay_status`）

| 值 | 说明 |
|----|------|
| `0` | 未支付（unpaid） |
| `1` | 待支付（pending） |
| `2` | 已支付（paid） |
| `3` | 已发货（shipped） |
| `4` | 已完成（completed） |
| `5` | 已退款（refunded） |

---

## 8. 认证说明

### 8.1 Sanctum Token 认证机制

JerseyHolic API 使用 **Laravel Sanctum** 实现 Token 认证。

**认证流程：**

1. 调用登录接口获取 Bearer Token（`token` 字段）
2. 后续所有受保护的请求在 `Authorization` Header 中携带 Token：
   ```
   Authorization: Bearer {token}
   ```
3. 登出时调用 `/auth/logout` 撤销 Token

**Token 的 Abilities（权限声明）**

商户 Token 携带以下 abilities：
- `role:{owner|manager|operator}` — 用户角色
- `store:{store_id}` — 可访问的站点（当 `allowed_store_ids` 非 null 时）

### 8.2 Guard 说明

| Guard | 用途 | 用户模型 | 使用端点 |
|-------|------|----------|----------|
| `sanctum`（默认） | 平台管理员认证 | `Admin` | `/api/v1/admin/*` |
| `merchant` | 商户用户认证 | `MerchantUser` | `/api/v1/merchant/*`（受保护） |

- **Admin guard（`auth:sanctum`）**：用于平台管理员操作，只能访问 `/api/v1/admin/` 前缀端点。
- **Merchant guard（`auth:merchant`）**：用于商户后台用户（owner / manager / operator）操作，只能访问 `/api/v1/merchant/` 前缀端点。

两者 Token 互不兼容，使用错误 guard 认证会返回 `401`。

### 8.3 `central.only` 中间件

所有受保护的 Admin 和 Merchant 端点均附加 `central.only` 中间件，确保这些路由只在 Central 数据库上下文中运行，**不经过租户识别中间件**，防止误操作 Tenant 数据库。

### 8.4 `force.json` 中间件

所有 API 端点均附加 `force.json` 中间件，强制响应格式为 `application/json`，无论客户端 `Accept` 头如何设置。

### 8.5 请求头要求

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}    # 受保护端点必填
```
