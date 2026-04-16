# JerseyHolic 支付 API 文档

> ✅ Phase M3 已实现。覆盖支付网关接口、Admin 支付管理接口（支付账号分组、三层映射、安全描述、黑名单、结算、佣金规则、风控、通知）以及 Merchant 支付相关接口。

---

## 目录

1. [支付网关接口](#1-支付网关接口)
2. [Admin 支付管理接口](#2-admin-支付管理接口)
3. [Merchant 支付相关接口](#3-merchant-支付相关接口)
4. [错误码说明](#4-错误码说明)
5. [安全说明](#5-安全说明)
6. [中间件说明](#6-中间件说明)
7. [实际路由注册摘要](#7-实际路由注册摘要)

---

## 1. 支付网关接口 ✅ Phase M3 已实现

> 前缀：`/api/v1/payment`（Tenant 路由）  
> 中间件：`api`, `tenant`, `force.json`, `set.locale`  
> 认证方式：部分端点需 Sanctum Token，Webhook 端点无需认证

---

### 1.1 创建 PayPal 订单

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/payment/paypal/create-order` |
| **描述** | 为当前订单创建 PayPal 支付订单，返回 PayPal approve URL |
| **认证** | 可选（支持游客结账） |
| **中间件** | `tenant`, `force.json`, `set.locale` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `order_id` | int | 是 | 系统订单 ID |
| `return_url` | string | 否 | 支付成功跳转 URL |
| `cancel_url` | string | 否 | 支付取消跳转 URL |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "PayPal order created",
  "data": {
    "paypal_order_id": "5O190127TN364715T",
    "approve_url": "https://www.paypal.com/checkoutnow?token=5O190127TN364715T",
    "status": "CREATED"
  }
}
```

**错误响应 `422`**

```json
{
  "code": 422,
  "message": "Order not found or already paid"
}
```

---

### 1.2 Capture PayPal 订单

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/payment/paypal/capture/{orderId}` |
| **描述** | 买家在 PayPal 完成授权后，系统 Capture 扣款。**需 RSA 签名验证** |
| **认证** | 可选 |
| **中间件** | `tenant`, `force.json` |

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| `orderId` | string | PayPal 订单 ID |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Payment captured successfully",
  "data": {
    "payment_id": 1001,
    "payment_no": "PAY-20260417-001",
    "status": "COMPLETED",
    "amount": 129.99,
    "currency": "USD",
    "external_transaction_id": "8MC585209K746631H"
  }
}
```

**错误响应 `400`**

```json
{
  "code": 400,
  "message": "PayPal capture failed: INSTRUMENT_DECLINED"
}
```

---

### 1.3 PayPal Webhook 回调

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/payment/paypal/webhook` |
| **描述** | 接收 PayPal 异步事件通知（PAYMENT.CAPTURE.COMPLETED、CUSTOMER.DISPUTE.CREATED 等） |
| **认证** | 无（通过 PayPal Webhook 签名验证） |
| **中间件** | `tenant`, `force.json` |

**请求体**：PayPal 标准 Webhook Event JSON

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Webhook processed"
}
```

> 注意：该端点必须在 5 秒内返回 `200`，否则 PayPal 将重试。处理逻辑应异步执行。

---

### 1.4 创建 Stripe Checkout Session

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/payment/stripe/create-session` |
| **描述** | 创建 Stripe Checkout Session，返回 checkout URL |
| **认证** | 可选 |
| **中间件** | `tenant`, `force.json`, `set.locale` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `order_id` | int | 是 | 系统订单 ID |
| `success_url` | string | 否 | 支付成功跳转 URL |
| `cancel_url` | string | 否 | 支付取消跳转 URL |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Stripe session created",
  "data": {
    "session_id": "cs_test_a1b2c3d4...",
    "checkout_url": "https://checkout.stripe.com/pay/cs_test_a1b2c3d4...",
    "status": "open"
  }
}
```

---

### 1.5 Stripe Webhook 回调

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/payment/stripe/webhook` |
| **描述** | 接收 Stripe 异步事件通知（checkout.session.completed、charge.dispute.created 等） |
| **认证** | 无（通过 Stripe Webhook Signing Secret 验证） |
| **中间件** | `force.json` |

**请求体**：Stripe 标准 Event JSON

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "Webhook processed"
}
```

---

## 2. Admin 支付管理接口 ✅ Phase M3 已实现

> 路由前缀：`/api/v1/admin`  
> 认证方式：Bearer Token（admin guard — `auth:sanctum`）  
> 中间件：`auth:sanctum`, `force.json`, `central.only`

---

### 2.1 支付账号分组管理

**路由前缀：** `/api/v1/admin/payment-account-groups`

---

#### 2.1.1 获取分组列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/payment-account-groups` |
| **描述** | 分页查询支付账号分组 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `type` | string | 否 | 按分组类型筛选：`paypal` / `credit_card` / `stripe` / `antom` |
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
        "id": 1,
        "name": "VIP PayPal Group",
        "type": "paypal",
        "description": "VIP 商户专用 PayPal 分组",
        "is_blacklist_group": 0,
        "status": 1,
        "account_count": 5,
        "created_at": "2026-01-01T00:00:00+00:00"
      }
    ],
    "total": 10,
    "page": 1,
    "per_page": 15
  }
}
```

---

#### 2.1.2 创建分组

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/payment-account-groups` |
| **描述** | 创建新的支付账号分组 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `name` | string | 是 | `required\|string\|max:64` | 分组名称 |
| `type` | string | 是 | `required\|string\|in:paypal,credit_card,stripe,antom` | 分组类型 |
| `description` | string | 否 | `nullable\|string\|max:255` | 分组描述 |
| `is_blacklist_group` | int | 否 | `in:0,1` | 是否黑名单专用组，默认 `0` |
| `status` | int | 否 | `in:0,1` | 状态，默认 `1` |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "分组创建成功",
  "data": { "id": 5, "name": "New Group", "type": "paypal", "status": 1 }
}
```

---

#### 2.1.3 更新分组

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/payment-account-groups/{id}` |
| **描述** | 更新分组信息 |

**请求体**：同创建，所有字段可选。

---

#### 2.1.4 删除分组

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/payment-account-groups/{id}` |
| **描述** | 删除分组（仅当分组下无关联账号时可删除） |

**成功响应 `200`**

```json
{ "code": 0, "message": "分组已删除", "data": null }
```

**错误响应 `422`**

```json
{ "code": 422, "message": "该分组下仍有关联支付账号，无法删除" }
```

---

### 2.2 三层映射管理（商户-支付方式-分组）

**路由前缀：** `/api/v1/admin/merchants/{id}/payment-group-mappings`

---

#### 2.2.1 获取商户支付分组映射

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/merchants/{id}/payment-group-mappings` |
| **描述** | 获取指定商户的「支付方式 → 支付分组」映射列表 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": 1,
      "merchant_id": 1,
      "pay_method": "paypal",
      "payment_group_id": 3,
      "payment_group_name": "VIP PayPal Group",
      "priority": 10
    },
    {
      "id": 2,
      "merchant_id": 1,
      "pay_method": "credit_card",
      "payment_group_id": 7,
      "payment_group_name": "Standard CC Group",
      "priority": 5
    }
  ]
}
```

---

#### 2.2.2 设置/更新映射

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/merchants/{id}/payment-group-mappings` |
| **描述** | 批量设置商户支付分组映射（覆盖式更新） |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `mappings` | array | 是 | 映射数组 |
| `mappings.*.pay_method` | string | 是 | 支付方式：`paypal` / `credit_card` / `stripe` / `antom` |
| `mappings.*.payment_group_id` | int | 是 | 支付分组 ID |
| `mappings.*.priority` | int | 否 | 优先级，默认 0 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "映射已更新",
  "data": { "merchant_id": 1, "mapping_count": 2 }
}
```

---

#### 2.2.3 更新单条映射

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/merchants/{id}/payment-group-mappings` |
| **描述** | 更新单条映射的分组或优先级 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `pay_method` | string | 是 | 支付方式 |
| `payment_group_id` | int | 是 | 新的支付分组 ID |
| `priority` | int | 否 | 优先级 |

---

### 2.3 支付账号管理

**路由前缀：** `/api/v1/admin/payment-accounts`

---

#### 2.3.1 获取支付账号列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/payment-accounts` |
| **描述** | 分页查询支付账号池 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `pay_method` | string | 否 | 支付方式筛选 |
| `status` | int | 否 | `0`=禁用, `1`=启用 |
| `category_id` | int | 否 | 分组 ID 筛选 |
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
        "account": "paypal-vip-01",
        "email": "vip01@business.com",
        "pay_method": "paypal",
        "category_id": 3,
        "status": 1,
        "permission": 1,
        "priority": 10,
        "money_total": 15000.00,
        "daily_money_total": 500.00,
        "deal_count": 120,
        "created_at": "2026-01-01T00:00:00+00:00"
      }
    ],
    "total": 50,
    "page": 1,
    "per_page": 15
  }
}
```

---

#### 2.3.2 创建支付账号

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/payment-accounts` |
| **描述** | 创建支付账号。**需 RSA 签名验证**（涉及资金操作凭证） |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `account` | string | 是 | `required\|string\|max:128` | 账号标识 |
| `email` | string | 否 | `nullable\|email\|max:128` | PayPal 邮箱 |
| `client_id` | string | 是 | `required\|string\|max:255` | Client ID |
| `client_secret` | string | 是 | `required\|string\|max:500` | Client Secret |
| `pay_method` | string | 是 | `required\|in:paypal,credit_card,stripe,antom,payssion` | 支付方式 |
| `category_id` | int | 否 | `integer` | PayPal 分组 ID |
| `cc_category_id` | int | 否 | `integer` | 信用卡分组 ID |
| `min_money` | decimal | 否 | `numeric\|min:0` | 最小金额 |
| `max_money` | decimal | 否 | `numeric\|min:0` | 最大金额 |
| `limit_money` | decimal | 否 | `numeric\|min:0` | 总限额，0=不限 |
| `daily_limit_money` | decimal | 否 | `numeric\|min:0` | 日限额，0=不限 |
| `priority` | int | 否 | `integer` | 优先级 |
| `domain` | string | 否 | `nullable\|string\|max:255` | 关联域名 |
| `success_url` | string | 否 | `nullable\|url\|max:512` | 支付成功回调 URL |
| `cancel_url` | string | 否 | `nullable\|url\|max:512` | 支付取消回调 URL |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "支付账号创建成功",
  "data": { "id": 10, "account": "paypal-new-01", "pay_method": "paypal", "status": 1 }
}
```

---

#### 2.3.3 更新支付账号

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/payment-accounts/{id}` |
| **描述** | 更新支付账号信息。**需 RSA 签名验证** |

**请求体**：同创建，所有字段可选。

---

#### 2.3.4 切换账号状态

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/payment-accounts/{id}` |
| **描述** | 快速切换账号状态/权限 |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `status` | int | 否 | `0`=禁用, `1`=启用 |
| `permission` | int | 否 | `1`=可收款, `2`=暂停, `3`=已封禁 |

---

#### 2.3.5 删除支付账号

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/payment-accounts/{id}` |
| **描述** | 软删除支付账号 |

**成功响应 `200`**

```json
{ "code": 0, "message": "支付账号已删除", "data": null }
```

---

### 2.4 PayPal 安全描述管理

**路由前缀：** `/api/v1/admin/safe-descriptions`

---

#### 2.4.1 获取安全描述列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/safe-descriptions` |
| **描述** | 获取 PayPal 安全描述映射列表，支持按站点和分类筛选 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `store_id` | int | 否 | 按站点筛选 |
| `product_category` | string | 否 | 按商品分类筛选 |
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
        "id": 1,
        "store_id": null,
        "product_category": "jerseys",
        "safe_name": "Sports Apparel",
        "safe_description": "Premium sports team clothing and accessories",
        "safe_category_code": "5699",
        "weight": 100,
        "status": 1,
        "created_at": "2026-01-01T00:00:00+00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "per_page": 15
  }
}
```

---

#### 2.4.2 创建安全描述

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/safe-descriptions` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `store_id` | int | 否 | `nullable\|integer\|exists:stores,id` | 关联站点，null=全局 |
| `product_category` | string | 是 | `required\|string\|max:64` | 商品分类 |
| `safe_name` | string | 是 | `required\|string\|max:128` | 安全名称 |
| `safe_description` | string | 是 | `required\|string\|max:255` | 安全描述 |
| `safe_category_code` | string | 否 | `nullable\|string\|max:16` | MCC 分类码 |
| `weight` | int | 否 | `integer\|min:0` | 权重，默认 0 |
| `status` | int | 否 | `in:0,1` | 状态，默认 1 |

---

#### 2.4.3 更新安全描述

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/safe-descriptions/{id}` |

**请求体**：同创建，所有字段可选。

---

#### 2.4.4 删除安全描述

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/safe-descriptions/{id}` |

---

### 2.5 黑名单管理

**路由前缀：** `/api/v1/admin/blacklist`

---

#### 2.5.1 获取黑名单列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/blacklist` |
| **描述** | 分页查询黑名单记录 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `scope` | string | 否 | `platform` / `merchant` |
| `dimension` | string | 否 | `ip` / `email` / `device` / `payment_account` |
| `keyword` | string | 否 | 按 value 模糊搜索 |
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
        "scope": "platform",
        "merchant_id": null,
        "dimension": "email",
        "value": "fraud@example.com",
        "reason": "Multiple chargeback fraud",
        "expires_at": null,
        "created_at": "2026-01-01T00:00:00+00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "per_page": 15
  }
}
```

---

#### 2.5.2 创建黑名单记录

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/blacklist` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `scope` | string | 是 | `required\|in:platform,merchant` | 作用范围 |
| `merchant_id` | int | 否 | `nullable\|integer\|exists:jh_merchants,id` | scope=merchant 时必填 |
| `dimension` | string | 是 | `required\|in:ip,email,device,payment_account` | 维度 |
| `value` | string | 是 | `required\|string\|max:255` | 黑名单值 |
| `reason` | string | 否 | `nullable\|string\|max:500` | 原因 |
| `expires_at` | datetime | 否 | `nullable\|date\|after:now` | 过期时间，null=永久 |

---

#### 2.5.3 更新黑名单记录

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/blacklist/{id}` |

---

#### 2.5.4 删除黑名单记录

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/blacklist/{id}` |

---

### 2.6 结算管理

**路由前缀：** `/api/v1/admin/settlements`

---

#### 2.6.1 获取结算单列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/settlements` |
| **描述** | 分页查询结算单 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `merchant_id` | int | 否 | 按商户筛选 |
| `status` | string | 否 | `pending` / `reviewed` / `paid` / `rejected` |
| `period_start` | date | 否 | 结算周期开始日期 |
| `period_end` | date | 否 | 结算周期结束日期 |
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
        "merchant_name": "My Jersey Store",
        "period_start": "2026-04-01",
        "period_end": "2026-04-15",
        "total_revenue": 50000.00,
        "total_refunds": 1200.00,
        "total_disputes": 300.00,
        "platform_fee": 2500.00,
        "payment_processing_fee": 1450.00,
        "net_amount": 44550.00,
        "status": "pending",
        "created_at": "2026-04-16T00:00:00+00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "per_page": 15
  }
}
```

---

#### 2.6.2 获取结算单详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/settlements/{id}` |
| **描述** | 获取结算单详情，含各站点明细 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "merchant_id": 1,
    "period_start": "2026-04-01",
    "period_end": "2026-04-15",
    "total_revenue": 50000.00,
    "total_refunds": 1200.00,
    "total_disputes": 300.00,
    "platform_fee": 2500.00,
    "payment_processing_fee": 1450.00,
    "net_amount": 44550.00,
    "status": "pending",
    "details": [
      {
        "id": 1,
        "settlement_id": 1,
        "store_id": 1,
        "store_name": "JerseyHolic US",
        "revenue": 30000.00,
        "refunds": 800.00,
        "disputes": 200.00,
        "net_amount": 29000.00
      }
    ]
  }
}
```

---

#### 2.6.3 生成结算单

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/settlements/generate` |
| **描述** | 为指定商户生成结算单。**需 RSA 签名验证** |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `merchant_id` | int | 是 | 商户 ID |
| `period_start` | date | 是 | 结算周期开始日期 |
| `period_end` | date | 是 | 结算周期结束日期 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "结算单已生成",
  "data": { "id": 5, "merchant_id": 1, "net_amount": 44550.00, "status": "pending" }
}
```

---

#### 2.6.4 审核结算单

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/settlements/{id}/review` |
| **描述** | 审核结算单（通过或拒绝）。**需 RSA 签名验证** |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | 是 | `approve` / `reject` |
| `comment` | string | 否 | 审核意见 |

---

#### 2.6.5 确认打款

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/settlements/{id}/pay` |
| **描述** | 标记结算单为已打款。**需 RSA 签名验证** |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `payment_method` | string | 否 | 打款方式说明 |
| `payment_reference` | string | 否 | 打款流水号 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "结算单已标记为已打款",
  "data": { "id": 1, "status": "paid" }
}
```

---

### 2.7 佣金规则管理

**路由前缀：** `/api/v1/admin/commission-rules`

---

#### 2.7.1 获取佣金规则列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/commission-rules` |
| **描述** | 查询佣金规则列表 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `merchant_id` | int | 否 | 按商户筛选，不传则查全局规则 |
| `rule_type` | string | 否 | 规则类型筛选 |
| `enabled` | int | 否 | `0`=禁用, `1`=启用 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": 1,
        "merchant_id": null,
        "store_id": null,
        "rule_type": "standard",
        "tier_name": "Default Tier",
        "base_rate": 5.00,
        "volume_discount": 0.50,
        "loyalty_discount": 0.30,
        "min_rate": 2.00,
        "max_rate": 8.00,
        "enabled": 1
      }
    ]
  }
}
```

---

#### 2.7.2 创建佣金规则

| 项目 | 值 |
|------|-----|
| **Method** | `POST` |
| **URI** | `/api/v1/admin/commission-rules` |

**请求体 `application/json`**

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|----------|------|
| `merchant_id` | int | 否 | `nullable\|integer\|exists:jh_merchants,id` | 商户 ID，null=全局规则 |
| `store_id` | int | 否 | `nullable\|integer\|exists:stores,id` | 站点 ID，null=商户级规则 |
| `rule_type` | string | 是 | `required\|string\|max:32` | 规则类型 |
| `tier_name` | string | 是 | `required\|string\|max:64` | 阶梯名称 |
| `base_rate` | decimal | 是 | `required\|numeric\|min:0\|max:100` | 基础费率(%) |
| `volume_discount` | decimal | 否 | `nullable\|numeric\|min:0` | 量级折扣(%) |
| `loyalty_discount` | decimal | 否 | `nullable\|numeric\|min:0` | 忠诚度折扣(%) |
| `min_rate` | decimal | 否 | `nullable\|numeric\|min:0` | 最低费率(%) |
| `max_rate` | decimal | 否 | `nullable\|numeric\|min:0` | 最高费率(%) |
| `enabled` | int | 否 | `in:0,1` | 状态，默认 1 |

---

#### 2.7.3 更新佣金规则

| 项目 | 值 |
|------|-----|
| **Method** | `PUT` |
| **URI** | `/api/v1/admin/commission-rules/{id}` |

---

#### 2.7.4 删除佣金规则

| 项目 | 值 |
|------|-----|
| **Method** | `DELETE` |
| **URI** | `/api/v1/admin/commission-rules/{id}` |

---

### 2.8 风控管理

**路由前缀：** `/api/v1/admin/risk`

---

#### 2.8.1 获取商户风控评分

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/risk/merchants/{id}/score` |
| **描述** | 获取指定商户的风控评分及风险指标 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "merchant_id": 1,
    "merchant_name": "My Jersey Store",
    "risk_score": 25,
    "risk_level": "low",
    "indicators": {
      "chargeback_rate": 0.8,
      "dispute_rate": 1.2,
      "refund_rate": 3.5,
      "fraud_attempts": 0,
      "blacklist_hits": 0
    },
    "updated_at": "2026-04-17T00:00:00+00:00"
  }
}
```

---

#### 2.8.2 风控仪表盘

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/risk/dashboard` |
| **描述** | 平台级风控仪表盘，展示高风险商户、近期风险事件概览 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "high_risk_merchants": 3,
    "medium_risk_merchants": 8,
    "low_risk_merchants": 45,
    "recent_alerts": [
      {
        "merchant_id": 5,
        "merchant_name": "Risky Store",
        "alert_type": "high_chargeback_rate",
        "risk_score": 85,
        "created_at": "2026-04-16T15:00:00+00:00"
      }
    ],
    "platform_stats": {
      "total_chargeback_rate": 1.2,
      "total_dispute_rate": 2.1,
      "blacklist_count": 150
    }
  }
}
```

---

### 2.9 通知管理

**路由前缀：** `/api/v1/admin/notifications`

---

#### 2.9.1 获取通知列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/admin/notifications` |
| **描述** | 获取管理员站内通知列表 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `type` | string | 否 | `risk_alert` / `settlement` / `account_issue` / `blacklist` |
| `is_read` | int | 否 | `0`=未读, `1`=已读 |
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
        "user_type": "admin",
        "user_id": 1,
        "type": "risk_alert",
        "title": "高风险商户预警",
        "content": "商户 Risky Store 的争议率已超过阈值 5%",
        "channel": "site",
        "is_read": 0,
        "read_at": null,
        "created_at": "2026-04-16T15:00:00+00:00"
      }
    ],
    "total": 50,
    "unread_count": 12,
    "page": 1,
    "per_page": 15
  }
}
```

---

#### 2.9.2 标记通知已读

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/admin/notifications/{id}/read` |
| **描述** | 将指定通知标记为已读 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "通知已标记为已读",
  "data": { "id": 1, "is_read": 1, "read_at": "2026-04-17T08:00:00+00:00" }
}
```

---

## 3. Merchant 支付相关接口 ✅ Phase M3 已实现

> 路由前缀：`/api/v1/merchant`  
> 认证方式：Bearer Token（`auth:merchant`）  
> 中间件：`auth:merchant`, `force.json`, `central.only`

---

### 3.1 商户结算单列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/settlements` |
| **描述** | 商户查看自己的结算单列表 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `status` | string | 否 | `pending` / `reviewed` / `paid` / `rejected` |
| `period_start` | date | 否 | 周期开始日期 |
| `period_end` | date | 否 | 周期结束日期 |
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
        "period_start": "2026-04-01",
        "period_end": "2026-04-15",
        "total_revenue": 50000.00,
        "total_refunds": 1200.00,
        "total_disputes": 300.00,
        "platform_fee": 2500.00,
        "payment_processing_fee": 1450.00,
        "net_amount": 44550.00,
        "status": "paid",
        "created_at": "2026-04-16T00:00:00+00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "per_page": 15
  }
}
```

---

### 3.2 商户结算单详情

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/settlements/{id}` |
| **描述** | 商户查看结算单详情（含各站点明细） |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "period_start": "2026-04-01",
    "period_end": "2026-04-15",
    "total_revenue": 50000.00,
    "net_amount": 44550.00,
    "status": "paid",
    "details": [
      {
        "store_id": 1,
        "store_name": "JerseyHolic US",
        "revenue": 30000.00,
        "refunds": 800.00,
        "disputes": 200.00,
        "net_amount": 29000.00
      }
    ]
  }
}
```

---

### 3.3 商户通知列表

| 项目 | 值 |
|------|-----|
| **Method** | `GET` |
| **URI** | `/api/v1/merchant/notifications` |
| **描述** | 商户站内通知列表 |

**Query 参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `type` | string | 否 | `risk_alert` / `settlement` / `account_issue` / `blacklist` |
| `is_read` | int | 否 | `0`=未读, `1`=已读 |
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
        "type": "settlement",
        "title": "结算单已到账",
        "content": "您的 2026-04-01 ~ 2026-04-15 结算款 $44,550.00 已到账",
        "channel": "site",
        "is_read": 0,
        "read_at": null,
        "created_at": "2026-04-17T00:00:00+00:00"
      }
    ],
    "total": 10,
    "unread_count": 3,
    "page": 1,
    "per_page": 15
  }
}
```

---

### 3.4 标记通知已读

| 项目 | 值 |
|------|-----|
| **Method** | `PATCH` |
| **URI** | `/api/v1/merchant/notifications/{id}/read` |
| **描述** | 商户标记通知为已读 |

**成功响应 `200`**

```json
{
  "code": 0,
  "message": "通知已标记为已读",
  "data": { "id": 10, "is_read": 1, "read_at": "2026-04-17T08:30:00+00:00" }
}
```

---

## 4. 错误码说明 ✅ Phase M3 已实现

### 4.1 支付相关错误码

| HTTP 状态码 | code | 说明 |
|-------------|------|------|
| `400` | `40001` | 支付网关调用失败（PayPal/Stripe 返回错误） |
| `400` | `40002` | Webhook 签名验证失败 |
| `409` | `40901` | 订单已支付，不可重复支付 |
| `422` | `42201` | 订单不存在或状态不可支付 |
| `422` | `42202` | 支付账号不可用（已禁用/额度不足） |
| `422` | `42203` | 命中黑名单拦截 |
| `500` | `50001` | 支付网关超时 |

### 4.2 结算相关错误码

| HTTP 状态码 | code | 说明 |
|-------------|------|------|
| `422` | `42210` | 结算周期重叠 |
| `422` | `42211` | 结算单状态不允许此操作 |
| `403` | `40310` | RSA 签名验证失败 |

---

## 5. 安全说明 ✅ Phase M3 已实现

### 5.1 RSA 签名验证

以下资金操作接口需要 RSA 签名验证：

| 接口 | 说明 |
|------|------|
| `POST /api/v1/payment/paypal/capture/{orderId}` | Capture 扣款 |
| `POST /api/v1/admin/payment-accounts` | 创建支付账号 |
| `PUT /api/v1/admin/payment-accounts/{id}` | 更新支付账号 |
| `POST /api/v1/admin/settlements/generate` | 生成结算单 |
| `PATCH /api/v1/admin/settlements/{id}/review` | 审核结算单 |
| `PATCH /api/v1/admin/settlements/{id}/pay` | 确认打款 |

**签名方式：**

1. 将请求体 JSON 按 key 升序排列后序列化为字符串
2. 使用商户私钥（RSA-4096）对字符串进行 SHA256WithRSA 签名
3. 将签名值 Base64 编码后放入请求头 `X-Signature`
4. 请求头 `X-Key-Id` 携带密钥 UUID

```
X-Key-Id: a1b2c3d4-e5f6-7890-abcd-ef1234567890
X-Signature: Base64(RSA_SHA256(sorted_json_body, private_key))
X-Timestamp: 1713340800
```

### 5.2 Webhook 安全

- **PayPal**：通过 `PAYPAL-TRANSMISSION-SIG` 和 `PAYPAL-CERT-URL` 验证 Webhook 签名
- **Stripe**：通过 `Stripe-Signature` Header 和 Webhook Signing Secret 验证
- 所有 Webhook 端点实现**幂等处理**，重复事件不会导致重复操作

---

## 6. 中间件说明 ✅ Phase M3 已实现

| 中间件 | 算法 | 说明 |
|--------|------|------|
| `verify.merchant.signature` | RSA-SHA256 | 商户签名验证，Timestamp ±5min 时间窗口校验，Nonce 通过 Redis SETNX 防重放 |
| `verify.paypal.webhook` | PayPal Verify Webhook Signature API | 调用 PayPal 官方 API 验证 Webhook 签名真实性 |
| `verify.stripe.webhook` | HMAC-SHA256 | 通过 `Stripe-Signature` header 验签，容差 300s |

---

## 7. 实际路由注册摘要 ✅ Phase M3 已实现

### 7.1 Admin 路由（`auth:sanctum` 保护）

| 路由注册 | 说明 |
|---------|------|
| `apiResource('payment-account-groups')` | 分组 CRUD |
| `apiResource('payment-accounts')` | 账号 CRUD + 状态切换 |
| `apiResource('payment-group-mappings')` | 三层映射 |
| `apiResource('safe-descriptions')` | 脱敏模板 |
| `apiResource('commission-rules')` | 佣金规则 |
| `apiResource('blacklist')` | 黑名单 |
| `settlements/` | 结算管理（列表/详情/生成/审核/打款/拒绝/取消） |
| `risk/dashboard` + `risk/merchants/{id}/score` | 风控仪表板 |
| `refund-impact/` | 退款影响 |
| `notifications/` | 通知管理 |

### 7.2 Merchant 路由

| 路由注册 | 说明 |
|---------|------|
| `settlements/` | 商户查看结算单 |
| `notifications/` | 商户通知 |

### 7.3 Webhook 路由（无 auth）

| Method | URI | 中间件 |
|--------|-----|--------|
| `POST` | `/webhooks/paypal` | `verify.paypal.webhook` |
| `POST` | `/webhooks/stripe` | `verify.stripe.webhook` |

### 7.4 Tenant 路由（`auth:sanctum` 买家）

| Method | URI | 说明 |
|--------|-----|------|
| `POST` | `/payment/create` | 创建支付 |
| `POST` | `/payment/capture/{orderNo}` | 捕获支付 |
