# JerseyHolic 多租户 API 路由架构文档

> Phase M1 基础架构阶段产出，Phase M2 更新补充商户管理相关端点。本文档描述多租户路由体系、已实现端点清单及后续阶段预留端点。  
> 商户 API 详细说明（请求/响应格式）请参阅 [merchant-api.md](./merchant-api.md)。

---

## 1. 路由体系总览

系统采用 **双路由文件** 架构，由 `TenancyServiceProvider` 统一注册：

| 路由文件 | 作用域 | 域名绑定 | 中间件 | 说明 |
|-----------|--------|----------|--------|------|
| `routes/central.php` | Central | `admin.jerseyholic.com` 等中央域名 | `api` | 平台管理 + 商户后台 + Webhook |
| `routes/tenant.php` | Tenant | 任意租户域名（运行时识别） | `api`, `tenant` | 买家前台 |

### 域名→租户识别→API 处理链路

```
请求到达
  │
  ├─ Central 域名（admin.jerseyholic.com / localhost）
  │    → 匹配 central.php 路由
  │    → 不经过租户识别，直接走 Central DB
  │    → central.only 中间件阻止租户域名访问
  │
  └─ 租户域名（store1.jerseyholic.com）
       → 匹配 tenant.php 路由
       → ResolveTenantByDomain 中间件：域名 → 查找 Store 记录
       → EnsureTenantContext 中间件：初始化租户上下文
       → Bootstrappers 自动切换 DB/Cache/Queue/Filesystem/Redis
       → 请求进入 Controller，数据库已指向租户库
```

### Tenant 中间件组组成

| 中间件 | 职责 |
|--------|------|
| `ResolveTenantByDomain` | 根据请求域名查找对应 Store（租户） |
| `EnsureTenantContext` | 确保租户上下文已成功初始化 |
| `PreventAccessFromCentralDomains` | 阻止 Central 域名访问 Tenant 路由 |

---

## 2. API 认证机制

采用 **Laravel Sanctum** Token 认证，配置三套 Auth Provider：

| Provider | Model | 用途 | Guard |
|----------|-------|------|-------|
| `admins` | `App\Models\Admin` | 平台管理员 | `auth:sanctum` |
| `merchants` | `App\Models\Merchant` | 商户（注册审核元信息） | `auth:sanctum` |
| `merchant_users` | `App\Models\Central\MerchantUser` | 商户子账号（后台登录） | `auth:merchant` |
| `customers` | `App\Models\Customer` | 买家 | `auth:sanctum` |

> 当前 Sanctum guard 配置为单一 `sanctum` driver（provider=null），通过 Token 自身关联的 tokenable model 自动判别用户类型。

### 认证流程

1. 客户端通过 `/api/v1/{角色}/auth/login` 获取 Bearer Token
2. 后续请求在 Header 中携带 `Authorization: Bearer {token}`
3. Sanctum 根据 Token 的 `tokenable_type` 自动解析出 Admin / Merchant / Customer

---

## 3. Central 路由 — 已实现端点

### 3.1 Admin Auth（公开）

| 方法 | 路径 | Controller | 认证 |
|------|------|-----------|------|
| POST | `/api/v1/admin/auth/login` | `AdminAuthController@login` | 无 |

### 3.2 Admin 受保护端点

> 中间件：`auth:sanctum`, `force.json`, `central.only`

**Auth 管理**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| POST | `/api/v1/admin/auth/logout` | `AdminAuthController@logout` | 退出登录 |
| GET | `/api/v1/admin/auth/me` | `AdminAuthController@me` | 当前用户信息 |

**Dashboard**

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/admin/dashboard` | 仪表盘（占位闭包） |

**商品管理**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/admin/products` | `AdminProductController@index` | 商品列表 |
| POST | `/api/v1/admin/products` | `AdminProductController@store` | 创建商品 |
| GET | `/api/v1/admin/products/{id}` | `AdminProductController@show` | 商品详情 |
| PUT | `/api/v1/admin/products/{id}` | `AdminProductController@update` | 更新商品 |
| DELETE | `/api/v1/admin/products/{id}` | `AdminProductController@destroy` | 删除商品 |
| PATCH | `/api/v1/admin/products/{id}/stock` | `AdminProductController@updateStock` | 更新库存 |
| PATCH | `/api/v1/admin/products/{id}/toggle-status` | `AdminProductController@toggleStatus` | 切换上下架 |
| POST | `/api/v1/admin/products/bulk-delete` | `AdminProductController@bulkDelete` | 批量删除 |
| POST | `/api/v1/admin/products/bulk-status` | `AdminProductController@bulkUpdateStatus` | 批量改状态 |
| GET | `/api/v1/admin/products/export` | `AdminProductController@export` | 导出商品 |

**分类管理**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/admin/categories/tree` | `AdminCategoryController@tree` | 分类树 |
| POST | `/api/v1/admin/categories/reorder` | `AdminCategoryController@reorder` | 排序 |
| GET | `/api/v1/admin/categories` | `AdminCategoryController@index` | 分类列表 |
| POST | `/api/v1/admin/categories` | `AdminCategoryController@store` | 创建分类 |
| GET | `/api/v1/admin/categories/{id}` | `AdminCategoryController@show` | 分类详情 |
| PUT/PATCH | `/api/v1/admin/categories/{id}` | `AdminCategoryController@update` | 更新分类 |
| DELETE | `/api/v1/admin/categories/{id}` | `AdminCategoryController@destroy` | 删除分类 |

**订单管理**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/admin/orders` | `AdminOrderController@index` | 订单列表 |
| GET | `/api/v1/admin/orders/export` | `AdminOrderController@export` | 导出订单 |
| GET | `/api/v1/admin/orders/{id}` | `AdminOrderController@show` | 订单详情 |
| PATCH | `/api/v1/admin/orders/{id}/pay-status` | `AdminOrderController@updatePayStatus` | 修改支付状态 |
| PATCH | `/api/v1/admin/orders/{id}/ship-status` | `AdminOrderController@updateShipStatus` | 修改物流状态 |
| POST | `/api/v1/admin/orders/{id}/refund` | `AdminOrderController@refund` | 退款 |
| POST | `/api/v1/admin/orders/{id}/history` | `AdminOrderController@addHistory` | 添加操作记录 |

**商户管理**（Phase M2 新增）

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/admin/merchants` | `AdminMerchantController@index` | 商户列表 |
| POST | `/api/v1/admin/merchants` | `AdminMerchantController@store` | 创建商户 |
| GET | `/api/v1/admin/merchants/{id}` | `AdminMerchantController@show` | 商户详情 |
| PUT | `/api/v1/admin/merchants/{id}` | `AdminMerchantController@update` | 更新商户 |
| DELETE | `/api/v1/admin/merchants/{id}` | `AdminMerchantController@destroy` | 删除商户 |
| PATCH | `/api/v1/admin/merchants/{id}/status` | `AdminMerchantController@changeStatus` | 修改商户状态 |
| PATCH | `/api/v1/admin/merchants/{id}/level` | `AdminMerchantController@updateLevel` | 更新商户等级 |
| POST | `/api/v1/admin/merchants/{id}/review` | `AdminMerchantController@review` | 商户审核（通过/拒绝/补充信息） |

**站点管理**（Phase M2 新增）

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/admin/stores` | `AdminStoreController@index` | 站点列表 |
| POST | `/api/v1/admin/stores` | `AdminStoreController@store` | 创建站点 |
| GET | `/api/v1/admin/stores/{id}` | `AdminStoreController@show` | 站点详情 |
| PUT | `/api/v1/admin/stores/{id}` | `AdminStoreController@update` | 更新站点 |
| DELETE | `/api/v1/admin/stores/{id}` | `AdminStoreController@destroy` | 删除站点 |
| PATCH | `/api/v1/admin/stores/{id}/status` | `AdminStoreController@updateStatus` | 修改站点状态 |
| PATCH | `/api/v1/admin/stores/{id}/categories` | `AdminStoreController@updateCategories` | 更新分类配置 |
| PATCH | `/api/v1/admin/stores/{id}/markets` | `AdminStoreController@updateMarkets` | 更新市场配置 |
| PATCH | `/api/v1/admin/stores/{id}/languages` | `AdminStoreController@updateLanguages` | 更新语言配置 |
| PATCH | `/api/v1/admin/stores/{id}/currencies` | `AdminStoreController@updateCurrencies` | 更新货币配置 |
| PATCH | `/api/v1/admin/stores/{id}/payment-accounts` | `AdminStoreController@updatePaymentAccounts` | 更新支付账户 |
| PATCH | `/api/v1/admin/stores/{id}/logistics` | `AdminStoreController@updateLogistics` | 更新物流配置 |
| POST | `/api/v1/admin/stores/{id}/domains` | `AdminStoreController@addDomain` | 添加域名 |
| DELETE | `/api/v1/admin/stores/{id}/domains/{domainId}` | `AdminStoreController@removeDomain` | 移除域名 |

**RBAC 权限管理**

> 额外中间件：`check.permission:rbac.manage`

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/admin/rbac/roles` | `RbacController@roleIndex` | 角色列表 |
| POST | `/api/v1/admin/rbac/roles` | `RbacController@roleStore` | 创建角色 |
| PUT | `/api/v1/admin/rbac/roles/{id}` | `RbacController@roleUpdate` | 更新角色 |
| DELETE | `/api/v1/admin/rbac/roles/{id}` | `RbacController@roleDestroy` | 删除角色 |
| GET | `/api/v1/admin/rbac/permissions` | `RbacController@permissionIndex` | 权限列表 |
| GET | `/api/v1/admin/rbac/permissions/tree` | `RbacController@permissionTree` | 权限树 |
| POST | `/api/v1/admin/rbac/admins/{id}/roles` | `RbacController@assignRoles` | 分配角色 |
| GET | `/api/v1/admin/rbac/admins/{id}/permissions` | `RbacController@adminPermissions` | 管理员权限 |

### 3.3 Central 路由 — M3-M6 实现

| 路由前缀 | 预期 Controller | 计划阶段 | 说明 |
|----------|----------------|---------|------|
| `/api/v1/admin/payment-accounts` | PaymentAccountController | M3 | 支付账户管理 |
| `/api/v1/admin/product-mappings` | ProductMappingController | M3 | 商品映射（P0 安全） |
| `/api/v1/admin/customers` | CustomerController | M3 | 客户管理 |
| `/api/v1/admin/shipments` | ShipmentController | M4 | 物流管理 |
| `/api/v1/admin/settlements` | SettlementController | M5 | 结算管理 |
| `/api/v1/admin/risk` | RiskController | M6 | 风控管理 |
| `/api/v1/admin/settings` | SettingController | M3 | 系统设置 |
| `/api/v1/admin/fb-pixels` | FbPixelController | M4 | Facebook Pixel |
| `/api/v1/admin/logs` | OperationLogController | M3 | 操作日志 |

### 3.4 Merchant 路由组 — Phase M2 已实现

**公开端点（无需认证）**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| POST | `/api/v1/merchant/register` | `MerchantRegisterController@register` | 商户公开注册 |
| POST | `/api/v1/merchant/auth/login` | `MerchantAuthController@login` | 商户登录 |

**受保护端点**

> 中间件：`auth:merchant`, `force.json`, `central.only`

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| POST | `/api/v1/merchant/auth/logout` | `MerchantAuthController@logout` | 退出登录 |
| GET | `/api/v1/merchant/auth/me` | `MerchantAuthController@me` | 当前用户信息 |
| POST | `/api/v1/merchant/auth/refresh` | `MerchantAuthController@refresh` | 刷新 Token |
| GET | `/api/v1/merchant/dashboard` | `MerchantDashboardController@index` | 商户仪表盘 |
| GET | `/api/v1/merchant/stores` | `MerchantDashboardController@stores` | 商户名下站点列表 |
| GET | `/api/v1/merchant/users` | `MerchantUserController@index` | 子账号列表 |
| POST | `/api/v1/merchant/users` | `MerchantUserController@store` | 创建子账号 |
| GET | `/api/v1/merchant/users/{id}` | `MerchantUserController@show` | 子账号详情 |
| PUT | `/api/v1/merchant/users/{id}` | `MerchantUserController@update` | 更新子账号 |
| DELETE | `/api/v1/merchant/users/{id}` | `MerchantUserController@destroy` | 删除子账号 |
| PATCH | `/api/v1/merchant/users/{id}/password` | `MerchantUserController@changePassword` | 修改密码 |
| PATCH | `/api/v1/merchant/users/{id}/permissions` | `MerchantUserController@updatePermissions` | 更新权限 |
| POST | `/api/v1/merchant/users/{id}/unlock` | `MerchantUserController@unlock` | 解除账号锁定 |
| GET | `/api/v1/merchant/orders` | `MerchantOrderController@index` | 订单列表（只读） |
| GET | `/api/v1/merchant/orders/{id}` | `MerchantOrderController@show` | 订单详情（只读） |
| GET | `/api/v1/merchant/api-keys` | `ApiKeyController@index` | API 密钥列表 |
| POST | `/api/v1/merchant/api-keys` | `ApiKeyController@store` | 生成新密钥对 |
| POST | `/api/v1/merchant/api-keys/download` | `ApiKeyController@download` | 下载私钥（一次性） |
| GET | `/api/v1/merchant/api-keys/{keyId}` | `ApiKeyController@show` | 密钥详情 |
| POST | `/api/v1/merchant/api-keys/{keyId}/rotate` | `ApiKeyController@rotate` | 轮换密钥 |
| DELETE | `/api/v1/merchant/api-keys/{keyId}` | `ApiKeyController@revoke` | 吊销密钥 |

**待实现（M3+）**

| 路由前缀 | 预期 Controller | 说明 |
|----------|----------------|------|
| `/api/v1/merchant/shop` | MerchantShopController | 店铺管理 |
| `/api/v1/merchant/products` | MerchantProductController | 商品管理 |
| `/api/v1/merchant/settlements` | MerchantSettlementController | 结算查看 |

### 3.5 Webhook 路由 — TODO 占位

> 前缀：`/api/v1/webhook`，无需认证

| 方法 | 路径 | 预期 Controller | 说明 |
|------|------|----------------|------|
| POST | `/api/v1/webhook/paypal/ipn` | PayPalWebhookController@handleIpn | PayPal IPN |
| POST | `/api/v1/webhook/paypal/webhook` | PayPalWebhookController@handleWebhook | PayPal Webhook |
| POST | `/api/v1/webhook/stripe/webhook` | StripeWebhookController@handle | Stripe Webhook |
| POST | `/api/v1/webhook/logistics/{provider}/callback` | LogisticsWebhookController@handle | 物流回调 |
| POST | `/api/v1/webhook/antom/notify` | AntomWebhookController@handle | Antom 支付回调 |

---

## 4. Tenant 路由 — 已实现端点

> 域名：租户域名（如 `store1.jerseyholic.com`）
> 中间件：`api`, `tenant`（自动识别租户）, `force.json`, `set.locale`

### 4.1 公开端点（无需认证）

**商品浏览**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/products` | `BuyerProductController@index` | 商品列表 |
| GET | `/api/v1/products/search` | `BuyerProductController@search` | 商品搜索 |
| GET | `/api/v1/products/category/{categoryId}` | `BuyerProductController@byCategory` | 分类商品 |
| GET | `/api/v1/products/{id}` | `BuyerProductController@show` | 商品详情 |

**分类**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/categories` | `BuyerCategoryController@index` | 分类列表 |
| GET | `/api/v1/categories/{id}` | `BuyerCategoryController@show` | 分类详情 |

**买家认证**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| POST | `/api/v1/auth/login` | `BuyerAuthController@login` | 登录 |
| POST | `/api/v1/auth/register` | `BuyerAuthController@register` | 注册 |
| POST | `/api/v1/auth/forgot-password` | `BuyerAuthController@forgotPassword` | 忘记密码 |
| POST | `/api/v1/auth/reset-password` | `BuyerAuthController@resetPassword` | 重置密码 |

**地址数据**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/countries` | `AddressController@countries` | 国家列表 |
| GET | `/api/v1/countries/{countryId}/zones` | `AddressController@zones` | 省/州列表 |

**购物车**（支持游客 + 登录用户）

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/cart` | `CartController@index` | 查看购物车 |
| POST | `/api/v1/cart/add` | `CartController@add` | 添加商品 |
| PUT | `/api/v1/cart/update` | `CartController@update` | 更新数量 |
| DELETE | `/api/v1/cart/{itemKey}` | `CartController@remove` | 移除商品 |
| DELETE | `/api/v1/cart` | `CartController@clear` | 清空购物车 |
| GET | `/api/v1/cart/summary` | `CartController@summary` | 购物车汇总 |

**结账**（支持游客 + 登录用户）

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| POST | `/api/v1/checkout/preview` | `CheckoutController@preview` | 预览订单 |
| POST | `/api/v1/checkout/submit` | `CheckoutController@submit` | 提交订单 |

**店铺信息**

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/store/info` | 获取当前租户店铺信息（name, status） |

### 4.2 受保护端点（需 Sanctum 认证）

**Auth**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| POST | `/api/v1/auth/logout` | `BuyerAuthController@logout` | 退出登录 |
| GET | `/api/v1/auth/me` | `BuyerAuthController@me` | 当前用户信息 |

**订单**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/orders` | `BuyerOrderController@index` | 订单列表 |
| POST | `/api/v1/orders` | `BuyerOrderController@store` | 创建订单 |
| GET | `/api/v1/orders/{id}` | `BuyerOrderController@show` | 订单详情 |
| POST | `/api/v1/orders/{id}/cancel` | `BuyerOrderController@cancel` | 取消订单 |

**账户**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/account/profile` | `AccountController@profile` | 个人资料 |
| PUT | `/api/v1/account/profile` | `AccountController@updateProfile` | 更新资料 |
| PUT | `/api/v1/account/password` | `AccountController@changePassword` | 修改密码 |
| GET | `/api/v1/account/orders` | `AccountController@orderHistory` | 订单历史 |

**收货地址**

| 方法 | 路径 | Controller | 说明 |
|------|------|-----------|------|
| GET | `/api/v1/account/addresses` | `AddressController@index` | 地址列表 |
| POST | `/api/v1/account/addresses` | `AddressController@store` | 新增地址 |
| GET | `/api/v1/account/addresses/{id}` | `AddressController@show` | 地址详情 |
| PUT | `/api/v1/account/addresses/{id}` | `AddressController@update` | 更新地址 |
| DELETE | `/api/v1/account/addresses/{id}` | `AddressController@destroy` | 删除地址 |
| PATCH | `/api/v1/account/addresses/{id}/default` | `AddressController@setDefault` | 设为默认 |

### 4.3 Tenant 路由 — TODO 占位

| 路由前缀 | 预期 Controller | 说明 |
|----------|----------------|------|
| `/api/v1/wishlist` | WishlistController | 收藏夹 |
| `/api/v1/shipping/rates` | ShippingRateController | 运费计算 |

---

## 5. 路由文件结构说明

```
api/routes/
├── central.php     ← 多租户 Central 路由（绑定 Central 域名）
├── tenant.php      ← 多租户 Tenant 路由（租户域名，带 tenant 中间件组）
├── api.php         ← 加载 admin/merchant/buyer/webhook（兼容非多租户场景）
├── admin.php       ← Admin 路由（被 api.php 引用）
├── buyer.php       ← Buyer 路由（被 api.php 引用）
├── merchant.php    ← Merchant 路由占位（被 api.php 引用）
├── webhook.php     ← Webhook 路由占位（被 api.php 引用）
└── web.php         ← Web 路由
```

> **注意**：`central.php` 和 `admin.php` 的 Admin 端点内容一致，前者由 `TenancyServiceProvider` 注册（带域名绑定和 `central.only` 中间件），后者由 `api.php` 引用（传统注册方式）。部署时应根据是否启用多租户选择加载策略。

---

## 6. Bootstrappers（租户上下文初始化器）

当请求进入 Tenant 路由并识别到租户后，以下 Bootstrapper 自动执行：

| Bootstrapper | 职责 |
|-------------|------|
| `DatabaseTenancyBootstrapper` | 切换数据库连接到租户库 |
| `CacheTenancyBootstrapper` | 缓存 key 前缀隔离 |
| `QueueTenancyBootstrapper` | 队列任务自动携带租户上下文 |
| `FilesystemTenancyBootstrapper` | 文件系统路径隔离 |
| `RedisTenancyBootstrapper` | Redis key 前缀隔离 |

---

## 7. 各阶段路由实现进度

### Phase M1 已完成

1. 搞建 `central.php` / `tenant.php` 双路由文件架构
2. `TenancyServiceProvider` 实现域名级路由分发
3. `tenant` 中间件组（域名识别→租户初始化→防越权访问）
4. 将现有 Admin/Buyer 端点迁移至多租户路由结构
5. Central DB 双库隔离架构、Redis/Cache/Queue/Filesystem Bootstrapper 配置

### Phase M2 已完成

1. Admin 商户管理 API（merchants 端点全套）— CRUD + 审核 + 状态/等级变更
2. Admin 站点管理 API（stores 端点全套）— CRUD + 域名/分类/语言/货币/支付账户配置
3. Merchant 公开注册 + 登录端点
4. Merchant 受保护 API：仪表盘、站点列表、子账号管理、订单查看、RSA 密钥管理
5. Sanctum 三套认证体系（sanctum / merchant / customers）完整配置

### M3+ 待实现

1. Webhook 回调处理逻辑
2. 支付账户、商品映射、客户管理、物流管理（M3-M4）
3. 商户店铺管理、商品管理、结算查看 API（M3+）
4. 结算管理、风控管理（M5-M6）
