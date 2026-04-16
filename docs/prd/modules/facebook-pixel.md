# Facebook Pixel PRD

> 优先级：**P0（核心事件）+ P1（完整事件）** | 版本：v1.0 | 更新日期：2026-04-16
> 来源系统：OpenCart fbecommevnt 模块

## 1. 概述

### 功能简述
Facebook Pixel 追踪系统从 OpenCart 的 fbecommevnt 模块继承，负责在买家商城（Nuxt 3 SSR）中注入 Facebook 像素代码，追踪用户购物旅程的所有关键事件。支持多像素 ID、多语言自定义事件名、SSR 兼容。

### 业务价值
- 为 Facebook/Meta 广告投放提供准确的转化数据
- 支持 Lookalike Audience 和 Custom Audience 建设
- 追踪完整购物漏斗，优化广告 ROAS

### ⚠️ 安全约束
**所有 Pixel 事件必须使用真实商品信息**（名称、价格、ID），**禁止使用安全映射后的名称**。Pixel 追踪数据面向 Facebook 广告系统，不存在品牌审核风险。

## 2. 事件清单

### 标准电商事件

| 事件名称 | 触发时机 | 必要参数 | 优先级 |
|---------|---------|---------|--------|
| PageView | 每个页面加载 | — | P0 |
| ViewContent | 商品详情页浏览 | content_ids, content_type, content_name, content_category, value, currency | P0 |
| AddToCart | 加入购物车 | content_ids, content_type, value, currency, contents[{id, quantity, item_price}] | P0 |
| Purchase | 完成购买 | content_ids, content_type, value, currency, contents, num_items | P0 |
| InitiateCheckout | 进入结账页 | content_ids, content_type, value, currency, num_items, contents | P1 |
| AddPaymentInfo | 选择支付方式 | content_category, value, currency | P1 |
| Search | 搜索商品 | search_string, content_category, contents | P1 |
| AddToWishlist | 加入愿望清单 | content_ids, value, currency | P1 |
| CompleteRegistration | 注册完成 | status, currency, value | P1 |
| Contact | 联系客服 | — | P2 |
| Lead | 潜在客户（询价） | content_category, value, currency | P2 |
| Subscribe | 邮件订阅 | — | P2 |

### 自定义事件（从 fbecommevnt 继承）

| 事件名 | 触发时机 | 优先级 |
|--------|---------|--------|
| CheckoutStep1~6 | 结账各步骤完成 | P2 |
| LoginEvent | 用户登录 | P2 |
| RegisterEvent | 用户注册（自定义） | P2 |
| RemoveFromCart | 从购物车移除商品 | P2 |
| CompareProduct | 添加商品对比 | P2 |

## 3. 用户故事

#### US-PIX-001: PageView + ViewContent

**作为** 营销运营人员，
**我希望** 买家每次浏览页面和商品详情时自动触发 Pixel 事件，
**以便** 追踪用户浏览行为。

**验收标准：**
- Given 买家访问任意页面，When 页面加载完成，Then 触发 PageView 事件
- Given 买家访问商品详情页，When 页面加载，Then 触发 ViewContent 事件，参数包含真实商品 ID/名称/价格/分类
- Given Nuxt 3 SSR 渲染，When 服务端渲染，Then PageView 仅在客户端 hydration 后触发一次（不重复）
- Given 多像素 ID 配置，When 触发事件，Then 每个像素 ID 独立初始化和追踪

**优先级**: P0

---

#### US-PIX-002: AddToCart + Purchase

**作为** 营销运营人员，
**我希望** 追踪加入购物车和完成购买事件，
**以便** 衡量广告转化效果。

**验收标准：**
- Given 买家点击"加入购物车"，When 商品加入成功，Then 触发 AddToCart（含 content_ids, quantity, value, currency）
- Given 买家完成支付，When 支付成功页加载，Then 触发 Purchase 事件（含所有商品明细、总金额、订单号）
- Given 触发 Purchase 事件，When 传递参数，Then **使用真实商品名称和 ID**
- Given 触发 Purchase 事件，When 传递价格，Then **使用真实价格**

**优先级**: P0

---

#### US-PIX-003: 多像素 ID 管理

**作为** 管理员，
**我希望** 按店铺/语言配置不同的 Pixel ID（支持多个），
**以便** 不同市场使用不同的广告账户追踪。

**验收标准：**
- Given 配置像素 ID "111,222,333"，When 页面加载，Then 三个像素 ID 各自独立 fbq('init') 和 fbq('track', 'PageView')
- Given 不同店铺配置不同 Pixel ID，When 访问不同域名，Then 使用对应店铺的 Pixel ID
- Given 后台可配置事件开关，When 关闭某事件，Then 该事件不再触发

**优先级**: P0 | **复杂度**: M

---

#### US-PIX-004: SSR 兼容

**作为** 前端开发者，
**我希望** Pixel 代码在 Nuxt 3 SSR 环境下正确工作，
**以便** 不影响 SEO 和首屏渲染。

**验收标准：**
- Given Nuxt 3 SSR 页面，When 服务端渲染，Then Pixel 基础脚本在 `<head>` 中静态输出
- Given 客户端 hydration 完成，When 路由切换（SPA导航），Then 动态触发新页面的事件
- Given SSR 到 CSR 切换，When 页面渲染，Then 事件不重复触发

**优先级**: P0 | **复杂度**: M

## 4. 事件参数详情

### ViewContent 事件
```json
{
  "content_ids": ["product_123"],
  "content_type": "product",
  "content_name": "真实商品名称（非映射名）",
  "content_category": "分类名称",
  "value": 49.99,
  "currency": "USD",
  "product_catalog_id": "catalog_123"
}
```

### Purchase 事件
```json
{
  "value": 99.99,
  "currency": "USD",
  "content_type": "product",
  "num_items": 3,
  "order_id": "ORD-2026-001",
  "contents": [
    {
      "id": "product_123",
      "quantity": 2,
      "item_price": 49.99,
      "product_catalog_id": "catalog_123"
    }
  ]
}
```

### AddToCart 事件
```json
{
  "content_ids": ["product_456"],
  "content_type": "product",
  "value": 29.99,
  "currency": "USD",
  "contents": [
    {"id": "product_456", "quantity": 1, "item_price": 29.99}
  ]
}
```

## 5. 数据需求

**jh_pixel_configs** — Pixel 配置表
- id, store_id(店铺), locale(语言, 可null)
- pixel_ids(逗号分隔多ID), catalog_id
- status(启用/禁用)
- event_config(JSON: 各事件开关)
- created_at, updated_at

## 6. API 需求

| 接口 | 说明 |
|------|------|
| GET /api/pixel/config | 前台获取当前店铺 Pixel 配置 |
| GET /api/admin/pixel-configs | 后台 Pixel 配置列表 |
| POST /api/admin/pixel-configs | 创建/更新 Pixel 配置 |

## 7. 验收标准

### 功能验收
- [ ] PageView 每个页面触发一次
- [ ] ViewContent 在商品详情页正确触发
- [ ] AddToCart 在加入购物车时触发（异步/客户端）
- [ ] Purchase 在支付成功页触发，包含完整订单信息
- [ ] 多像素 ID 各自独立初始化和追踪
- [ ] SSR 渲染不重复触发事件

### 安全验收（**关键**）
- [ ] **所有 Pixel 事件使用真实商品名称（非安全映射名）**
- [ ] **所有 Pixel 事件使用真实价格**
- [ ] **content_ids 使用真实商品 ID**

### 多语言验收
- [ ] 不同语言版本的页面正确触发事件
- [ ] content_category 使用对应语言的分类名
- [ ] 结账步骤自定义事件名支持多语言配置

## 8. 非功能需求

- **性能**：Pixel 脚本异步加载，不阻塞页面渲染
- **隐私**：遵循 GDPR/CCPA，支持 Cookie 同意管理（事件仅在用户同意后触发）
- **调试**：支持 Facebook Pixel Helper 浏览器扩展调试
