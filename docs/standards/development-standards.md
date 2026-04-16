# JerseyHolic 项目开发规范

> 版本：1.0.0 | 更新日期：2026-04-16  
> 维护人：架构组 | 适用范围：jerseyholic-new 全栈项目

---

## 目录

1. [问题复盘与根因分析](#1-问题复盘与根因分析)
2. [API 路由与版本管理规范](#2-api-路由与版本管理规范)
3. [前后端接口契约规范](#3-前后端接口契约规范)
4. [认证与安全规范](#4-认证与安全规范)
5. [错误处理规范](#5-错误处理规范)
6. [代码风格规范](#6-代码风格规范)
7. [接口文档管理](#7-接口文档管理)
8. [开发环境配置规范](#8-开发环境配置规范)
9. [检查清单](#9-检查清单)

---

## 1. 问题复盘与根因分析

### 1.1 问题一：接口字段不一致（422 Parameter Error）

**现象**：前端 admin-ui 登录页发送 `{ email, password }`，后端 Admin AuthController 验证 `username`，导致 422 验证失败。

**根因定位**：

```
# 前端 src/types/api.d.ts 第 24-27 行
export interface LoginParams {
  username: string   ← 类型定义写的是 username
  password: string
}

# 前端 src/api/auth.ts 第 5-7 行
export function login(data: LoginParams) {
  return post<LoginResult>('/auth/login', data)  ← 直接传 { username, password }
}

# 后端 app/Http/Controllers/Admin/AuthController.php 第 18-21 行
$request->validate([
  'username' => 'required|string',   ← 后端期望 username
  'password' => 'required|string|min:6',
]);
```

而前端实际登录表单可能使用了 `email` 字段（因为 `LoginParams` 和实际表单绑定字段不一致），造成了字段名混乱。

**影响范围**：Admin 登录功能完全不可用。

**修复方案**：
- 统一 Admin 登录字段为 `email`（与 Buyer 端保持一致，管理员账号也以邮箱标识）
- 后端 `Admin\AuthController::login()` 改为 `'email' => 'required|email'`
- 前端 `src/types/api.d.ts` 中 `LoginParams.username` 改为 `LoginParams.email`
- 提取 Admin Login 专用 FormRequest 类 `App\Http\Requests\Admin\LoginRequest`

**教训**：**后端字段定义是契约的单一真相来源（SSOT）**，前端类型定义必须与后端 FormRequest 的 `rules()` 完全对应，不得各自随意命名。

---

### 1.2 问题二：路由前缀不统一（404 Not Found）

**现象**：Admin API 使用 `api/v1/admin`，Merchant API 使用 `api/merchant`（缺少 v1），Webhook 使用 `api/webhook`（缺少 v1），导致前端请求 404。

**根因定位**：

```php
# routes/admin.php - 正确，有 v1
Route::prefix('api/v1/admin')...

# routes/merchant.php - 错误，缺少 v1
Route::prefix('api/merchant')...    ← 应为 api/v1/merchant

# routes/webhook.php - 错误，缺少 v1
Route::prefix('api/webhook')...     ← 应为 api/v1/webhook
```

**影响范围**：Merchant 端和 Webhook 所有接口路径错误，上线后整体不可用。

**修复方案**：
- 将 `routes/merchant.php` 和 `routes/webhook.php` 中的前缀改为 `api/v1/merchant` 和 `api/v1/webhook`
- 建立路由前缀常量，防止再次遗漏（见第 2 章）

**教训**：路由前缀须统一规范，新增路由文件时必须按本文档第 2 章规范配置。

---

### 1.3 问题三：限流配置过于严格（429 Too Many Requests）

**现象**：开发阶段频繁调试登录接口时触发 429 限流。

**根因定位**：

```php
# app/Providers/RouteServiceProvider.php 第 30-38 行
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(300)->by($request->ip());  // 通用 OK
});

RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(30)->by($request->ip());   // 每分钟 30 次，开发调试很容易触发
});
```

当前 admin 和 buyer 登录路由均未显式绑定 `throttle:login`，使用的是 `api` 中间件组的 `throttle:api`（300次/分钟），但配置中 `login` 限流器定义了 30 次，若将来绑定到登录路由后将严重影响开发体验。

**影响范围**：开发/测试环境调试体验差，测试自动化脚本容易被限流阻断。

**修复方案**：限流策略按环境差异化配置（见第 4 章），开发环境登录限流放宽至 1000 次/分钟。

**教训**：限流配置须区分环境，`APP_ENV=local` 时应使用宽松策略。

---

### 1.4 问题四：API 版本管理不一致

**现象**：已修复的 merchant 和 webhook 路由缺少 v1 前缀，未来扩展时各端版本升级无统一策略。

**根因**：项目初期未制定路由版本规范，各路由文件独立编写，缺乏统一审查。

**影响范围**：影响未来版本迭代的 URL 兼容性管理。

**修复方案**：建立版本管理规范，所有 API 强制使用 `api/v1/` 前缀（见第 2 章）。

**教训**：API 版本策略必须在项目启动时确定并写入规范，不能等到问题暴露时再补救。

---

### 1.5 教训总结

| 教训 | 防范措施 |
|------|----------|
| 前后端字段命名各自为政 | 后端 FormRequest 是 SSOT，前端 TS 类型必须与 FormRequest.rules() 对应生成 |
| 路由文件无人审查 | 新路由文件合并前必须经过路由前缀检查（见检查清单） |
| 限流不分环境 | RouteServiceProvider 使用 `app()->environment()` 判断环境配置不同限流 |
| 缺乏接口联调前的契约确认 | 新接口开发后，前端必须在 Scramble 文档 UI 确认字段定义（http://localhost:8000/docs/api） |

---

## 2. API 路由与版本管理规范

### 2.1 统一路由前缀规则

**所有 API 路由必须使用 `api/v1/` 前缀**，格式如下：

| 路由文件 | 前缀 | 说明 |
|----------|------|------|
| `routes/admin.php` | `api/v1/admin` | 管理后台接口 |
| `routes/buyer.php` | `api/v1` | 买家端接口（公开+认证） |
| `routes/merchant.php` | `api/v1/merchant` | 商家端接口 |
| `routes/webhook.php` | `api/v1/webhook` | 第三方回调接口 |

**当前需修复**：

```php
// routes/merchant.php - 需修改
Route::prefix('api/v1/merchant')   // 原为 api/merchant

// routes/webhook.php - 需修改
Route::prefix('api/v1/webhook')    // 原为 api/webhook
```

### 2.2 版本升级策略

当需要破坏性变更时，新增 v2 路由文件：
- 新建 `routes/admin_v2.php`，前缀改为 `api/v2/admin`
- `routes/api.php` 中同时加载 v1 和 v2
- v1 接口维持 6 个月过渡期后废弃

```php
// routes/api.php - 版本共存示例
require __DIR__ . '/admin.php';       // v1
require __DIR__ . '/admin_v2.php';    // v2（未来）
```

### 2.3 路由文件结构规范

每个路由文件必须遵循如下结构：

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\XxxController;

// ── 1. 公开路由（无需认证） ─────────────────────────────
Route::prefix('api/v1/admin/auth')
    ->middleware(['force.json'])
    ->group(function () {
        Route::post('login', [AdminAuthController::class, 'login'])
             ->middleware('throttle:login');
    });

// ── 2. 认证路由 ───────────────────────────────────────
Route::prefix('api/v1/admin')
    ->middleware(['auth:sanctum', 'force.json'])
    ->group(function () {
        // 按资源分组
    });
```

### 2.4 URL 命名规范

**资源名使用复数 kebab-case**：

```
# 正确
GET  /api/v1/admin/products
GET  /api/v1/admin/product-mappings
GET  /api/v1/admin/payment-accounts
POST /api/v1/admin/fb-pixels

# 错误
GET  /api/v1/admin/product        ← 单数
GET  /api/v1/admin/productMapping ← camelCase
GET  /api/v1/admin/ProductMapping ← PascalCase
```

**操作型路由使用动词短语**：

```
POST   /api/v1/admin/products/{id}/toggle-status   ← 切换状态
PATCH  /api/v1/admin/products/{id}/stock           ← 更新库存
POST   /api/v1/admin/products/bulk-delete          ← 批量删除
POST   /api/v1/admin/products/bulk-status          ← 批量改状态
POST   /api/v1/admin/orders/{id}/refund            ← 退款
```

### 2.5 路由中间件配置标准

| 中间件别名 | 定义位置 | 作用 |
|-----------|---------|------|
| `force.json` | `App\Http\Middleware\ForceJsonResponse` | 强制 Accept: application/json |
| `set.locale` | `App\Http\Middleware\SetLocaleMiddleware` | 从 Accept-Language 设置 app locale（仅 buyer） |
| `auth:sanctum` | Laravel Sanctum | 验证 Bearer Token |
| `check.permission:{key}` | `App\Http\Middleware\CheckPermission` | 权限检查，如 `check.permission:rbac.manage` |
| `check.role:{role}` | `App\Http\Middleware\CheckRole` | 角色检查 |
| `throttle:login` | `RouteServiceProvider` | 登录专用限流 |
| `throttle:api` | `RouteServiceProvider` | 通用 API 限流（`api` 中间件组已包含） |

**Admin 路由必须包含**：`['auth:sanctum', 'force.json']`  
**Buyer 公开路由必须包含**：`['force.json', 'set.locale']`  
**登录路由额外加**：`throttle:login`  
**Webhook 路由必须去掉认证**：`->withoutMiddleware(['auth'])`

---

## 3. 前后端接口契约规范

### 3.1 字段命名规范

**后端（Laravel）与前端（TypeScript）字段命名策略**：

| 层级 | 命名规范 | 示例 |
|------|---------|------|
| 后端 PHP 字段（数据库/请求/响应） | `snake_case` | `first_name`, `created_at`, `per_page` |
| 前端 TypeScript 接口字段 | `snake_case`（与后端保持一致，**不转换**） | `first_name`, `created_at`, `per_page` |
| 前端组件内部变量 | `camelCase` | `firstName`（仅组件内部使用时） |

> **重要**：前端 API 层（`src/api/*.ts`、`src/types/*.d.ts`）的字段名必须与后端 `FormRequest.rules()` / `Resource` 字段名完全一致，**不做 camelCase 转换**。组件内部如需 camelCase，在组件层做映射。

**当前项目实际遵守情况**（`src/types/api.d.ts`）：

```typescript
// 正确做法 - 与后端保持一致
export interface PaginationParams {
  page: number
  per_page: number     // snake_case ✓
}

export interface PaginatedData<T> {
  items: T[]
  total: number
  page: number
  per_page: number     // snake_case ✓
  last_page: number    // snake_case ✓
}
```

### 3.2 统一响应格式

**所有 API 响应必须使用以下格式**：

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

**对应后端实现**（`app/Http/Traits/ApiResponse.php`）：

```php
// 成功响应
protected function success($data = null, string $message = 'success'): JsonResponse
{
    return response()->json([
        'code'    => 0,
        'message' => $message,
        'data'    => $data,
    ]);
}

// 错误响应
protected function error(int $code = 50000, string $message = 'error', $data = null): JsonResponse
{
    return response()->json([
        'code'    => $code,
        'message' => $message,
        'data'    => $data,
    ]);
}
```

**使用规范**：
- `code = 0`：业务成功
- `code != 0`：业务失败，`message` 为用户可读错误信息，`data` 可含错误详情
- **禁止**在 `data` 字段直接返回裸数组或原始 Eloquent 集合，必须通过 `ApiResponse` trait 或 `JsonResource` 包装

### 3.3 分页响应格式

**标准分页响应**（`app/Http/Traits/ApiResponse.php::paginate()`）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [ ... ],
    "total": 128,
    "page": 1,
    "per_page": 20
  }
}
```

**注意**：当前 `ApiResponse::paginate()` 使用 `list` 作为数组键，而前端 `PaginatedData<T>` 类型定义使用 `items`。**必须统一为 `list`**（以后端为准），前端类型需修正：

```typescript
// src/types/api.d.ts - 待修正
export interface PaginatedData<T> {
  list: T[]        // 改为 list，与后端 ApiResponse::paginate() 一致
  total: number
  page: number
  per_page: number
}
```

**分页请求参数**：

```
GET /api/v1/admin/products?page=1&per_page=20&keyword=jersey&status=1
```

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `page` | integer | 1 | 页码，从 1 开始 |
| `per_page` | integer | 20 | 每页条数，最大 100 |

### 3.4 错误响应格式

**验证错误**（422）：

```json
{
  "code": 42200,
  "message": "参数验证失败",
  "data": {
    "errors": {
      "email": ["邮箱格式不正确"],
      "password": ["密码不能少于6位"]
    }
  }
}
```

**业务错误**（200，由 BusinessException 触发）：

```json
{
  "code": 50000,
  "message": "账号已被禁用",
  "data": null
}
```

**认证失败**（401）：

```json
{
  "code": 40100,
  "message": "未认证",
  "data": null
}
```

### 3.5 请求参数验证规范（FormRequest）

**规则**：涉及复杂请求体（3个以上字段）的接口**必须使用 FormRequest**，禁止在 Controller 中直接 `$request->validate()`。

**FormRequest 文件位置**：

```
app/Http/Requests/
├── Admin/
│   ├── LoginRequest.php         ← 待创建
│   ├── StoreProductRequest.php  ← 已存在
│   ├── UpdateProductRequest.php ← 已存在
│   ├── CategoryRequest.php      ← 已存在
│   └── RefundRequest.php        ← 已存在
└── Api/
    ├── LoginRequest.php         ← 待创建
    ├── RegisterRequest.php      ← 待创建
    └── CheckoutRequest.php      ← 待创建
```

**FormRequest 编写规范**（参考 `StoreProductRequest.php`）：

```php
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // 认证由中间件处理，此处始终 true
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:6|max:128',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => '邮箱不能为空',
            'email.email'       => '邮箱格式不正确',
            'password.required' => '密码不能为空',
            'password.min'      => '密码不能少于6位',
        ];
    }
}
```

### 3.6 接口契约同步流程

**标准流程：后端定义 → 前端实现**

```
1. 后端开发接口
   ↓ 创建/修改 FormRequest（字段定义）
   ↓ 创建/修改 JsonResource（响应字段）
   ↓ 写接口注释（PHPDoc）

2. 后端通知前端（通过 Git PR 描述或接口文档）
   ↓ 说明新增/变更的字段名、类型、必填性
   ↓ 提供 curl/Postman 示例

3. 前端同步更新
   ↓ 更新 src/types/*.d.ts 中对应接口类型
   ↓ 更新 src/api/*.ts 中请求/响应类型注解

4. 联调验证
   ↓ 前端发请求，检查字段名是否 100% 匹配
   ↓ 确认 code=0 时 data 字段结构正确
```

**变更通知模板（PR 描述中必须包含）**：

```markdown
## 接口变更说明

**接口**：POST /api/v1/admin/auth/login

**变更类型**：修改请求字段

**变更前**：`{ username: string, password: string }`
**变更后**：`{ email: string, password: string }`

**前端需更新**：
- [ ] src/types/api.d.ts 中 LoginParams.username → LoginParams.email
- [ ] 登录表单 v-model 绑定字段名
```

---

## 4. 认证与安全规范

### 4.1 Sanctum Token 认证流程

**登录流程**：

```
POST /api/v1/admin/auth/login
Body: { email, password }
                  ↓
  AuthService::adminLogin() 验证 jh_admins 表
                  ↓
  Sanctum 颁发 Token：$user->createToken('admin-token')->plainTextToken
                  ↓
Response: { code:0, data: { token: "...", user: {...} } }
                  ↓
前端存储：localStorage.setItem('jh_token', token)
          (admin-ui: utils/auth.ts)
                  ↓
后续请求 Header：Authorization: Bearer {token}
```

**Token 存储位置**：

| 端 | 存储方式 | 键名 |
|----|---------|------|
| admin-ui | `localStorage` | `jh_token`（见 `src/utils/auth.ts`） |
| storefront | `localStorage` | `jh_token`（见 `storefront/composables/useApi.ts` 第14行） |

**Token 命名规范**：

```php
// 不同端的 Token 名称区分
$user->createToken('admin-token')    // 管理后台
$user->createToken('buyer-token')    // 买家端
$user->createToken('merchant-token') // 商家端
```

**登出流程**：

```php
// 当前 token
$request->user()->currentAccessToken()->delete();

// 所有设备登出
$request->user()->tokens()->delete();
```

### 4.2 限流策略（差异化配置）

**当前配置**（`app/Providers/RouteServiceProvider.php`）：

```php
// 通用 API：300次/分钟（按 IP）
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(300)->by($request->ip());
});

// 登录接口：30次/分钟
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(30)->by($request->ip());
});
```

**规范要求**：限流必须区分环境，修改如下：

```php
protected function configureRateLimiting(): void
{
    $isLocal = app()->environment('local', 'testing');

    // 通用 API 限流
    RateLimiter::for('api', function (Request $request) use ($isLocal) {
        return $isLocal
            ? Limit::none()                                  // 本地开发不限流
            : Limit::perMinute(300)->by($request->ip());    // 生产 300次/分钟
    });

    // 登录接口限流
    RateLimiter::for('login', function (Request $request) use ($isLocal) {
        return $isLocal
            ? Limit::perMinute(1000)->by($request->ip())   // 开发 1000次/分钟
            : Limit::perMinute(10)->by($request->ip());    // 生产 10次/分钟（严格）
    });
}
```

**登录路由显式绑定限流**（在路由定义处添加）：

```php
Route::post('login', [AdminAuthController::class, 'login'])
     ->middleware('throttle:login');
```

### 4.3 CORS 配置

**当前配置**（`config/cors.php`）：

```php
return [
    'paths'           => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],         // ← 开发环境 OK，生产需收紧
    'allowed_headers' => ['*'],
    'supports_credentials' => false,
];
```

**规范要求**：

| 环境 | `allowed_origins` | `supports_credentials` |
|------|------------------|----------------------|
| local | `['*']` | `false` |
| staging | `['https://staging.jerseyholic.xyz', 'https://admin-staging.jerseyholic.xyz']` | `false` |
| production | `['https://jerseyholic.xyz', 'https://admin.jerseyholic.xyz']` | `false` |

生产环境通过环境变量控制：

```php
// config/cors.php 推荐写法
'allowed_origins' => env('APP_ENV') === 'production'
    ? explode(',', env('CORS_ALLOWED_ORIGINS', 'https://jerseyholic.xyz'))
    : ['*'],
```

### 4.4 Sanctum 有状态域名配置

**`.env` 当前配置**：

```
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001
```

**规范**：将每个前端应用的端口都加入，storefront 默认 3000，admin-ui 默认 3001：

```
# 本地开发
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001

# 生产
SANCTUM_STATEFUL_DOMAINS=jerseyholic.xyz,admin.jerseyholic.xyz
```

### 4.5 敏感数据处理

- **密码**：永远不在响应中返回，使用 `$dontFlash = ['password', 'password_confirmation', 'current_password']`（`Handler.php` 已配置）
- **支付凭证**：PayPal/Stripe Secret 只存 `.env`，绝不硬编码
- **Admin Token**：`localStorage` 存储，前端路由守卫 (`router/guard.ts`) 需验证 token 有效性
- **日志脱敏**：RequestLogMiddleware 不得记录完整请求体（需过滤 `password`、`card_number` 等字段）

---

## 5. 错误处理规范

### 5.1 HTTP 状态码使用标准

| HTTP 状态码 | 使用场景 | `code` 字段值 |
|------------|---------|--------------|
| 200 OK | 所有正常响应（包括业务错误） | 0（成功）或 5xxxx（业务失败） |
| 401 Unauthorized | Token 缺失/过期/无效 | 40100 |
| 403 Forbidden | 权限不足 | 40300 |
| 404 Not Found | 路由不存在 / 资源 ModelNotFound | 40400 |
| 422 Unprocessable Entity | 参数验证失败 | 42200 |
| 429 Too Many Requests | 限流 | 42900 |
| 500 Internal Server Error | 系统异常（生产隐藏详情） | 50000 |

**重要约定**：所有业务逻辑错误（账号禁用、余额不足等）使用 **HTTP 200 + code != 0** 返回，不使用 4xx/5xx。

### 5.2 业务错误码体系

**当前定义**（`app/Enums/ErrorCode.php`）：

```php
enum ErrorCode: int
{
    case SUCCESS         = 0;       // 成功
    case PARAM_ERROR     = 42200;   // 参数错误
    case UNAUTHORIZED    = 40100;   // 未认证
    case FORBIDDEN       = 40300;   // 无权限
    case NOT_FOUND       = 40400;   // 资源不存在
    case BUSINESS_ERROR  = 50000;   // 通用业务错误
    case PAYMENT_ERROR   = 50100;   // 支付错误
    case LOGISTICS_ERROR = 50200;   // 物流错误
    case MAPPING_ERROR   = 50300;   // 映射错误
    case ACCOUNT_DISABLED = 50400;  // 账号禁用
    case RATE_LIMIT      = 42900;   // 限流
}
```

**错误码设计规则**：
- `0`：成功
- `4xxxx`：客户端错误（对应 HTTP 4xx）
- `5xxxx`：服务端/业务错误
- `501xx`：支付相关，`502xx`：物流相关，`503xx`：映射相关，`504xx`：账号相关

**新增错误码必须在 ErrorCode 枚举中定义**，禁止在业务代码中硬编码数字。

### 5.3 异常处理层级

```
Controller
    └─ 调用 Service
           └─ Service 抛出 BusinessException(ErrorCode::XXX, '具体原因')
                   ↓
           app/Exceptions/Handler.php 捕获并格式化
                   ↓
         { code: errorCode.value, message: '...', data: null }
```

**`BusinessException` 使用示例**：

```php
use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;

// 在 Service 中
if ($admin->status === 0) {
    throw new BusinessException(ErrorCode::ACCOUNT_DISABLED, '账号已被禁用，请联系管理员');
}

if (!Hash::check($password, $admin->password)) {
    throw new BusinessException(ErrorCode::BUSINESS_ERROR, '用户名或密码错误');
}
```

**禁止**：在 Controller 中 `return response()->json(['code' => 50000, ...])` 手动构造错误响应，应使用 `BusinessException` 或 `$this->error()`。

### 5.4 前端错误处理标准

**admin-ui 错误处理**（`src/api/request.ts` 响应拦截器）：

```typescript
// 当前实现（正确）
service.interceptors.response.use(
  (response) => {
    const res = response.data
    if (res.code !== 0) {
      ElMessage.error(res.message || '请求失败')
      if (res.code === 401) { removeToken(); router.push('/login') }
      return Promise.reject(new Error(res.message))
    }
    return res
  },
  (error) => {
    // HTTP 层错误（401/403/404/500）
    switch (error.response?.status) {
      case 401: removeToken(); router.push('/login'); break
      case 403: ElMessage.error('无权限访问'); break
      // ...
    }
    return Promise.reject(error)
  }
)
```

**storefront 错误处理**（`composables/useApi.ts`）：

```typescript
// 当前实现（正确）
if (response.code !== 0) {
  throw new Error(response.message || 'Request failed')
}
```

**storefront 待增强**：需要根据 `code` 值做细化处理（401 跳转登录、网络错误提示等）。

---

## 6. 代码风格规范

### 6.1 后端 PHP 规范

#### Controller 规范

```
# 命名规则
Admin 端：App\Http\Controllers\Admin\{Resource}Controller
Buyer 端：App\Http\Controllers\Api\{Resource}Controller
基类：
  - App\Http\Controllers\BaseController（use ApiResponse trait）
  - App\Http\Controllers\Admin\BaseAdminController extends BaseController
  - App\Http\Controllers\Api\BaseApiController extends BaseController

# 方法命名（RESTful 标准）
index()    → 列表
store()    → 创建
show()     → 详情
update()   → 更新
destroy()  → 删除

# 自定义操作
toggleStatus()   → 切换状态
updateStock()    → 更新库存
bulkDelete()     → 批量删除
bulkUpdateStatus() → 批量更新状态
export()         → 导出
```

**Controller 职责**：只做请求解析、调用 Service、返回响应，**禁止**在 Controller 写业务逻辑。

#### Service 规范

```
位置：app/Services/{Resource}Service.php
命名：{Resource}Service（非接口绑定时直接具体类）

方法命名：
  getList(array $params): LengthAwarePaginator
  getById(int $id): Model
  create(array $data): Model
  update(int $id, array $data): Model
  delete(int $id): bool
  bulkDelete(array $ids): int
```

#### Model 规范

```
位置：app/Models/{Resource}.php
表名：jh_{resources}（复数，如 jh_products、jh_orders）
主键：id（自增整型）

必须定义：
  protected $table = 'jh_products';
  protected $fillable = [...];
  protected $hidden = ['deleted_at'];  // 软删除字段隐藏
  protected $casts = ['status' => 'integer', 'created_at' => 'datetime'];

软删除：敏感资源（Product、Order、Customer）必须使用 SoftDeletes
```

#### Request 规范

```
位置：app/Http/Requests/{Admin|Api}/{Action}{Resource}Request.php
示例：
  Admin\StoreProductRequest   ← 已存在
  Admin\UpdateProductRequest  ← 已存在
  Admin\LoginRequest          ← 待创建（替代 Controller 内 validate）
  Api\LoginRequest            ← 待创建
  Api\RegisterRequest         ← 待创建

规范：
  - authorize() 始终返回 true（认证由 auth:sanctum 中间件处理）
  - rules() 方法有完整的 中文 messages()
  - 使用 $this->validated() 而非 $request->all() 获取数据
```

#### 数据库命名规范

```
表名：jh_{resource_plural}（如 jh_products、jh_product_images、jh_order_items）
字段：snake_case（如 first_name、created_at、sort_order）
主键：id
外键：{resource}_id（如 product_id、category_id、order_id）
时间戳：created_at、updated_at、deleted_at（软删除）
状态字段：status（tinyint，0/1/2 等）
布尔字段：is_{xxx}（如 is_featured、is_default）
索引命名：{table}_{column(s)}_index（如 jh_products_status_index）
唯一索引：{table}_{column(s)}_unique（如 jh_products_sku_unique）
```

### 6.2 前端 admin-ui（Vue 3 + TypeScript）规范

#### 目录结构

```
src/
├── api/          # API 调用层（每个资源一个文件）
│   ├── request.ts    # Axios 封装（唯一 HTTP 客户端）
│   ├── auth.ts       # 认证相关 API
│   ├── product.ts    # 商品 API
│   └── ...
├── types/        # TypeScript 类型定义
│   ├── api.d.ts      # 通用 API 类型（ApiResponse、PaginatedData等）
│   ├── product.d.ts  # 商品类型
│   └── ...
├── stores/       # Pinia 状态管理
│   ├── user.ts       # 用户/认证状态
│   └── permission.ts # 权限状态
├── router/       # 路由
│   ├── index.ts
│   ├── routes.ts
│   └── guard.ts      # 路由守卫（Token 验证）
├── utils/
│   └── auth.ts       # Token 读写（getToken/setToken/removeToken）
└── components/   # 公共组件
```

#### API 层规范

```typescript
// 每个 API 文件结构规范
// src/api/product.ts

import { get, post, put, del } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

// 1. 先定义本模块的 interface（或从 types/ 引入）
export interface Product { ... }

// 2. 参数类型
export interface ProductListParams extends PaginationParams { ... }

// 3. API 函数（必须有 JSDoc 注释）
/** 获取商品分页列表 */
export function getProductList(params: ProductListParams): Promise<ApiResponse<PaginatedData<Product>>> {
  return get('/products', params as unknown as Record<string, unknown>)
}
```

**URL 规则**：`api/request.ts` 的 `baseURL` 已设为 `/api/v1/admin`，API 文件中 URL **不带前缀**，直接写资源路径：

```typescript
get('/products')        // → /api/v1/admin/products
post('/auth/login', ..) // → /api/v1/admin/auth/login
```

#### 类型定义规范

```typescript
// src/types/api.d.ts - 禁止在此文件定义业务类型
// 业务类型放各自的 types/*.d.ts

// 注意：PaginatedData.list 与后端保持一致（当前需修正 items → list）
export interface PaginatedData<T> {
  list: T[]          // 与后端 ApiResponse::paginate() 一致
  total: number
  page: number
  per_page: number
}
```

#### Store 规范

```typescript
// stores/user.ts 命名规范
export const useUserStore = defineStore('user', () => {
  // state
  const token = ref(getToken())
  const userInfo = ref<UserInfo | null>(null)

  // actions - 动词开头
  async function login(params: LoginParams) { ... }
  function logout() { ... }
  async function fetchUserInfo() { ... }

  return { token, userInfo, login, logout, fetchUserInfo }
})
```

### 6.3 前端 storefront（Nuxt 3 + TypeScript）规范

#### API 调用规范

```typescript
// composables/useApi.ts 是统一 HTTP 客户端，所有 API 调用通过它
const { apiFetch } = useApi()

// API base 来自 nuxt.config.ts runtimeConfig
// 本地：http://localhost:8000/api/v1
// 生产：通过 NUXT_PUBLIC_API_BASE 环境变量注入
```

**URL 规则**：storefront API URL **不带前缀**，直接写资源路径：

```typescript
apiFetch('/products')           // → {apiBase}/products = .../api/v1/products
apiFetch('/categories')         // → {apiBase}/categories
apiFetch('/auth/login', {...})  // → {apiBase}/auth/login
```

#### i18n 规范

```typescript
// nuxt.config.ts 中已配置 16 种语言
// 语言文件位置：storefront/locales/{locale}.json
// locale 格式：en, de, fr, es, it, ja, ko, pt-BR, pt-PT, nl, pl, sv, da, ar, tr, el

// Accept-Language header 在 useApi.ts 中自动注入
headers: { 'Accept-Language': locale.value }

// 后端 SetLocaleMiddleware 支持的 locale 列表需与 nuxt.config.ts locales 保持同步
```

---

## 7. 接口文档管理

### 7.1 文档工具：Scramble（自动化 OpenAPI 文档）

项目使用 **dedoc/scramble**（v0.13.18+）自动生成 OpenAPI 3.1.0 接口文档。Scramble 通过静态代码分析，从 FormRequest、Resource、Enum 等自动推断 API 结构，**代码变更自动同步文档，零手动维护**。

**访问地址**：

| 资源 | URL | 说明 |
|------|-----|------|
| 交互式文档 UI | `http://localhost:8000/docs/api` | Stoplight Elements，支持在线测试 |
| OpenAPI JSON | `http://localhost:8000/docs/api.json` | 标准 OpenAPI 3.1.0 spec |
| 静态备份 | `docs/api-spec.json` | 定期导出的离线副本 |

**配置文件**：
- Scramble 配置：`api/config/scramble.php`
- 认证方案注册：`api/app/Providers/AppServiceProvider.php`（`afterOpenApiGenerated` 回调）
- 环境变量：`.env` 中 `SCRAMBLE_ENABLED=true`

> Scramble 默认仅在 `local` 环境可见。生产环境如需暴露，须通过 `Gate::define('viewApiDocs')` 限制访问。

### 7.2 接口注释规范（PHPDoc）

Scramble 依赖 PHPDoc 注释生成接口描述。**所有 Controller 方法必须有 PHPDoc**：

```php
/**
 * 获取商品分页列表
 *
 * 支持按关键词、分类、状态、价格区间等多条件筛选。
 * 列表同时返回真实名称和安全映射名称。
 */
public function index(ProductListRequest $request): JsonResponse
```

**注解规范要点**：
- 第一行：方法简述（Scramble 提取为 `summary`）
- 空行后：详细说明（Scramble 提取为 `description`，支持 Markdown）
- **查询参数**：通过 FormRequest 的 `rules()` 自动推断，无需手动写 `@queryParam`
- **请求体**：通过 FormRequest 验证规则自动推断嵌套结构
- **响应格式**：通过 Resource 的 `toArray()` 自动推断字段和类型
- **Enum 类型**：PHP 8.1 Enum 自动识别为枚举值列表

**Scramble 无法自动推断的场景**（需补充注解）：
- Controller 方法内使用 `$request->only([...])` 而非 FormRequest（应重构为 FormRequest）
- 闭包路由（应改为 Controller 方法）
- 动态条件返回的字段（Resource 中 `when()` 条件字段）

### 7.3 确保 Scramble 推断准确的最佳实践

| 做法 | 原因 |
|------|------|
| 使用 FormRequest 替代 `$request->validate()` | Scramble 从 FormRequest `rules()` 推断参数 |
| Controller 方法声明返回类型 `JsonResponse` | 帮助 Scramble 推断响应格式 |
| Resource 中使用强类型 cast | 确保字段类型正确（`(int)`, `(float)`, `(bool)`） |
| Enum 使用 PHP 8.1 BackedEnum | 自动生成枚举值文档 |
| 关联数据用 `whenLoaded()` | Scramble 识别为可选字段 |

### 7.4 文档更新流程

```
后端完成接口开发（Controller + FormRequest + Resource）
       ↓
编写 PHPDoc 注释（方法简述 + 详细说明）
       ↓
访问 http://localhost:8000/docs/api 验证文档自动生成正确
       ↓
前端参照文档 UI 确认字段名、类型、必填项
       ↓
前端同步更新 TypeScript 类型定义（types/api.d.ts）
       ↓
定期导出 OpenAPI JSON 备份：
  curl http://localhost:8000/docs/api.json -o docs/api-spec.json
```

**关键原则**：Scramble 文档是接口契约的**单一事实来源**（Single Source of Truth）。前端类型定义必须与文档保持一致。

### 7.5 前后端联调检查清单

联调前双方必须确认：

- [ ] 在 `http://localhost:8000/docs/api` 中找到目标接口
- [ ] 接口 URL（含 `api/v1/` 版本前缀）完全一致
- [ ] HTTP 方法（GET/POST/PUT/PATCH/DELETE）一致
- [ ] 请求字段名（snake_case）和类型与文档完全匹配
- [ ] 必填字段（required）双方认知一致
- [ ] 响应 `data` 结构正确（特别是分页使用 `list` 字段）
- [ ] 错误场景（422 验证失败、401 未授权、403 无权限、404 不存在）前端已处理
- [ ] 前端 TypeScript 类型定义已同步更新

---

## 8. 开发环境配置规范

### 8.1 环境变量命名规范

**后端 `.env`**（`jerseyholic-new/api/.env`）：

```
# 命名规则：{MODULE}_{KEY}，全大写下划线
APP_NAME=JerseyHolic
APP_ENV=local          # local | staging | production
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jerseyholic_new

# 第三方服务加模块前缀
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=
STRIPE_KEY=
FB_PIXEL_ID=

# 旧系统数据库（迁移用）
DB_OC_HOST=...         # OpenCart
DB_TP_HOST=...         # ThinkPHP
```

**前端 admin-ui `.env.development`**（`jerseyholic-new/admin-ui/.env.development`）：

```
# Vite 环境变量必须以 VITE_ 开头
VITE_API_BASE_URL=http://localhost:8000
VITE_API_PREFIX=/api/v1/admin
VITE_APP_TITLE=JerseyHolic Admin
```

**前端 storefront `.env`**（`jerseyholic-new/storefront/.env`）：

```
# Nuxt 3 公开变量以 NUXT_PUBLIC_ 开头
NUXT_PUBLIC_API_BASE=http://localhost:8000/api/v1
```

### 8.2 各环境差异化配置

| 配置项 | local | staging | production |
|--------|-------|---------|------------|
| `APP_DEBUG` | `true` | `false` | `false` |
| `APP_ENV` | `local` | `staging` | `production` |
| `CORS allowed_origins` | `*` | staging 域名 | 正式域名 |
| API 通用限流 | 不限 | 300次/分钟 | 300次/分钟 |
| 登录限流 | 1000次/分钟 | 30次/分钟 | 10次/分钟 |
| 日志级别 | `debug` | `info` | `warning` |
| `APP_URL` | `http://localhost:8000` | staging URL | 正式 URL |
| 队列驱动 | `redis` | `redis` | `redis` |
| 缓存驱动 | `redis` | `redis` | `redis` |

### 8.3 本地开发启动标准流程

```powershell
# 1. 启动 Laragon（确保 MySQL、Redis、PHP 8.1+ 在 PATH 中）
# 参考：Laragon 路径通常为 C:\laragon\bin\php\php8.x\

# 2. 启动后端 API
cd E:\wuyin\com\jerseyholic-new\api
php artisan serve --port=8000

# 3. 启动 admin-ui（新终端）
cd E:\wuyin\com\jerseyholic-new\admin-ui
npm run dev    # 默认 http://localhost:3001

# 4. 启动 storefront（新终端）
cd E:\wuyin\com\jerseyholic-new\storefront
npm run dev    # 默认 http://localhost:3000

# 5. 启动队列（可选，处理异步任务）
cd E:\wuyin\com\jerseyholic-new\api
php artisan queue:work --queue=default
```

**首次环境初始化**：

```powershell
# 后端初始化
cd E:\wuyin\com\jerseyholic-new\api
composer install
copy .env.example .env          # 不存在则根据本规范手动创建 .env
php artisan key:generate
php artisan migrate --seed

# 前端初始化
cd E:\wuyin\com\jerseyholic-new\admin-ui
npm install

cd E:\wuyin\com\jerseyholic-new\storefront
npm install
```

### 8.4 数据库迁移规范

```
# 迁移文件命名
database/migrations/{timestamp}_create_jh_{resource}_table.php
database/migrations/{timestamp}_add_{column}_to_jh_{resource}_table.php
database/migrations/{timestamp}_modify_{column}_in_jh_{resource}_table.php

# 每次迁移后必须运行
php artisan migrate:status   # 确认所有迁移已运行

# 禁止直接修改已合并的迁移文件，须新建修改迁移
```

---

## 9. 检查清单

### 9.1 新接口开发检查清单

在开始开发新接口前，确认以下信息：

**需求阶段**：
- [ ] 确定接口 URL（含正确的版本前缀 `api/v1/`）
- [ ] 确定 HTTP 方法
- [ ] 确定请求字段名（snake_case）和类型
- [ ] 确定响应 `data` 结构
- [ ] 确定需要的认证和权限中间件

**后端开发阶段**：
- [ ] 路由已添加到正确的路由文件（admin/buyer/merchant/webhook）
- [ ] 路由前缀包含 `api/v1/`
- [ ] 创建了 FormRequest（3个以上字段时）
- [ ] FormRequest 有完整的 `messages()`
- [ ] Controller 只做解析+调用，业务逻辑在 Service
- [ ] 响应使用 `$this->success()` 或 `$this->paginate()`（不手写 JSON）
- [ ] 错误使用 `BusinessException` 抛出
- [ ] Controller 方法有 PHPDoc 注释
- [ ] 单元测试已编写（Service 层）

**通知前端阶段**：
- [ ] 在 PR 描述中填写"接口变更说明"
- [ ] 明确列出所有字段名和类型
- [ ] 提供 curl 或 Postman 示例

**前端开发阶段**：
- [ ] `src/types/*.d.ts` 已更新（字段名与后端 100% 一致）
- [ ] `src/api/*.ts` 已更新
- [ ] 分页响应使用 `PaginatedData<T>.list`（非 `items`）
- [ ] 错误处理已在拦截器中覆盖（401/403/422/500）
- [ ] 不在组件中直接使用 axios，通过 `src/api/*.ts` 调用

### 9.2 代码评审检查清单

**路由**：
- [ ] URL 使用 kebab-case 复数资源名
- [ ] 前缀包含 `api/v1/`
- [ ] 中间件配置完整（`force.json`、`auth:sanctum`）
- [ ] 登录路由绑定 `throttle:login`

**后端代码**：
- [ ] Controller 无业务逻辑（仅解析请求+调用 Service+返回响应）
- [ ] 复杂请求使用 FormRequest
- [ ] 不在代码中硬编码错误码数字（使用 ErrorCode 枚举）
- [ ] 数据库表名使用 `jh_` 前缀
- [ ] 新增字段名使用 snake_case

**前端代码**：
- [ ] API 层字段名与后端保持一致（snake_case）
- [ ] 类型定义有完整注释
- [ ] 分页数据结构一致（`list` 而非 `items`）
- [ ] 敏感操作（删除等）有确认提示

**安全**：
- [ ] 无硬编码密码/密钥
- [ ] 用户输入已通过 FormRequest 验证
- [ ] 权限控制已正确配置（`check.permission` 中间件）

### 9.3 上线前检查清单

**配置验证**：
- [ ] `APP_ENV=production`，`APP_DEBUG=false`
- [ ] CORS `allowed_origins` 已收紧为正式域名
- [ ] 登录限流已改为 10次/分钟
- [ ] `SANCTUM_STATEFUL_DOMAINS` 已设置正式域名
- [ ] 所有第三方 API Key 已替换为生产凭证

**数据库**：
- [ ] `php artisan migrate --force` 已在生产数据库执行
- [ ] `php artisan migrate:status` 无 pending 迁移

**缓存**：
- [ ] `php artisan config:cache` 已执行
- [ ] `php artisan route:cache` 已执行（路由无 Closure 时）
- [ ] `php artisan view:cache` 已执行

**前端构建**：
- [ ] admin-ui `npm run build` 构建无错误
- [ ] storefront `npm run build` 构建无错误
- [ ] 环境变量（`VITE_API_PREFIX`、`NUXT_PUBLIC_API_BASE`）已改为生产 URL

**接口联调**：
- [ ] Admin 登录接口 `POST /api/v1/admin/auth/login` 正常（字段 `email`）
- [ ] Buyer 登录接口 `POST /api/v1/auth/login` 正常（字段 `email`）
- [ ] Token 认证接口 `GET /api/v1/admin/auth/me` 正常
- [ ] 分页列表接口响应结构含 `list/total/page/per_page`

---

## 附录 A：快速参考

### 路由前缀速查

```
管理后台（公开）：/api/v1/admin/auth/...
管理后台（认证）：/api/v1/admin/...
买家端（全部）：  /api/v1/...
商家端（认证）：  /api/v1/merchant/...
Webhook（无认证）：/api/v1/webhook/...
```

### 响应格式速查

```json
// 成功（单对象）
{ "code": 0, "message": "success", "data": { ... } }

// 成功（分页列表）
{ "code": 0, "message": "success", "data": { "list": [], "total": 0, "page": 1, "per_page": 20 } }

// 成功（无数据）
{ "code": 0, "message": "操作成功", "data": null }

// 验证错误
{ "code": 42200, "message": "参数验证失败", "data": { "errors": { "field": ["msg"] } } }

// 业务错误
{ "code": 50000, "message": "具体原因", "data": null }

// 未认证
{ "code": 40100, "message": "未认证", "data": null }
```

### 文件路径速查

| 类型 | 路径 |
|------|------|
| 路由文件 | `api/routes/admin.php`, `buyer.php`, `merchant.php`, `webhook.php` |
| ApiResponse Trait | `api/app/Http/Traits/ApiResponse.php` |
| 异常处理 | `api/app/Exceptions/Handler.php` |
| 错误码枚举 | `api/app/Enums/ErrorCode.php` |
| 限流配置 | `api/app/Providers/RouteServiceProvider.php` |
| CORS 配置 | `api/config/cors.php` |
| axios 封装 | `admin-ui/src/api/request.ts` |
| API 类型定义 | `admin-ui/src/types/api.d.ts` |
| storefront HTTP | `storefront/composables/useApi.ts` |
| storefront 配置 | `storefront/nuxt.config.ts` |

---

*本文档应随项目演进持续更新，每次修改须更新文档头部版本号和日期。*
