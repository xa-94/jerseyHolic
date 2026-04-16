# Phase 1：基础架构（第 1-2 周）

> 目标：搭建项目骨架，完成数据库设计、认证权限系统、商品映射服务和前端项目初始化  
> 里程碑：M1 — 数据库可迁移执行，认证和权限系统就绪，商品映射服务可用

---

## 📊 Phase 1 进度看板

### 待开始 (To Do)
- [ ] TASK-P1-001: 数据库表结构设计（50+ 张表） (@architect) [P0]
- [ ] TASK-P1-002: Laravel 项目初始化与基础配置 (@backend-dev) [P0]
- [ ] TASK-P1-003: 认证系统（Sanctum 多用户类型） (@backend-dev) [P0]
- [ ] TASK-P1-004: RBAC 权限系统 (@backend-dev) [P0]
- [ ] TASK-P1-005: 商品映射服务 ProductMappingService (@backend-dev) [P0 安全级]
- [ ] TASK-P1-006: Vue 3 管理后台项目初始化 (@frontend-dev) [P1]
- [ ] TASK-P1-007: Nuxt 3 买家商城项目初始化 (@frontend-dev) [P1]
- [ ] TASK-P1-008: 旧系统数据分析与映射文档 (@migration-specialist) [P1]

### 进行中 (In Progress)
_（暂无）_

### 待验收 (In Review)
_（暂无）_

### 已完成 (Done)
_（暂无）_

### 阻塞 (Blocked)
_（暂无）_

---

## 任务详情

### TASK-P1-001: 数据库表结构设计（50+ 张表）

| 属性 | 值 |
|------|-----|
| **负责人** | @architect |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | 无 |
| **输出物** | `database/migrations/*.php`（50+ 个 Migration 文件）、`docs/design/database-schema.md` |

**验收标准：**
1. 所有 Migration 文件可执行 `php artisan migrate`，无报错
2. 表关系完整，外键约束正确
3. 覆盖以下核心模块的表：商品(products, product_descriptions, product_options, product_variants)、商品映射(product_safe_mappings, safe_name_pool)、分类(categories)、订单(orders, order_items, order_histories)、支付(payment_accounts, payment_transactions, payment_groups)、物流(shipments, shipping_methods, tracking_records)、用户(admins, merchants, buyers, addresses)、权限(roles, permissions, role_permissions)、Facebook Pixel(pixel_configs)、多语言(languages)、货币(currencies)、国家地区(countries, zones, geo_zones)、系统配置(settings)
4. 所有表使用 `jh_` 前缀，InnoDB 引擎，utf8mb4 字符集
5. 核心字段有适当索引（外键、常用查询字段、唯一约束）

**技术要点：**
- 参考 PRD 中 15 个模块的数据模型需求
- 商品映射表 `jh_product_safe_mappings` 需包含：product_id, safe_name, sku_prefix, priority 等字段
- 订单表需同时支持 OC 和 TP 两个旧系统的字段映射（保留旧 ID 字段便于迁移）
- 支付账号表需包含：限额字段(daily_limit, total_limit)、健康度字段、分组字段
- 使用 `softDeletes` 软删除策略

---

### TASK-P1-002: Laravel 项目初始化与基础配置

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 1d |
| **前置依赖** | 无 |
| **输出物** | `jerseyholic-api/` 项目根目录、`.env.example`、`composer.json` |

**验收标准：**
1. `php artisan serve` 项目可正常启动
2. `.env.example` 配置完整（DB/Redis/Queue/Mail/PayPal/Stripe 等）
3. 统一 API 响应格式 `{code, message, data}` 封装完成
4. 异常处理器统一返回 JSON 格式
5. CORS 配置正确
6. API 路由分组（api/v1/admin、api/v1/merchant、api/v1/buyer、api/v1/webhook）

**技术要点：**
- Laravel 10+，PHP 8.1+
- 安装核心依赖：sanctum、predis、queue、horizon
- 配置 6 个队列优先级：payment-critical、payment、logistics、notification、sync、default
- API 版本化路由设计

---

### TASK-P1-003: 认证系统（Sanctum 多用户类型）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P1-002 |
| **输出物** | `app/Models/Admin.php`、`app/Models/Merchant.php`、`app/Models/Buyer.php`、认证控制器、认证中间件 |

**验收标准：**
1. Admin/Merchant/Buyer 三类用户可独立登录，Token 发放正常
2. Token 支持过期时间配置和手动撤销
3. Merchant 支持 HMAC-SHA256 签名认证（API 调用）
4. Webhook 路由正确跳过认证中间件
5. 密码 bcrypt 加密存储
6. 连续登录失败锁定机制（5 次失败→锁定 15 分钟）

**技术要点：**
- 使用 Sanctum 多 Guard 配置（admin/merchant/buyer）
- Merchant 认证支持双模式：Token + HMAC-SHA256 签名
- 封装 `AuthService` 统一处理登录/登出/刷新逻辑
- Webhook 路由组使用独立中间件栈（验签但不验 Token）

---

### TASK-P1-004: RBAC 权限系统

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P1-003 |
| **输出物** | `app/Models/Role.php`、`app/Models/Permission.php`、权限中间件、权限 Seeder |

**验收标准：**
1. 角色 CRUD 正常（超级管理员/运营/客服/商户 等预设角色）
2. 权限 CRUD 正常，权限按模块分组
3. 角色-权限关联管理正常
4. 中间件守卫生效：未授权用户访问受保护路由返回 403
5. 超级管理员拥有所有权限，不受限制
6. 权限 Seeder 预置所有模块的基础权限

**技术要点：**
- 权限表设计：module(模块)、action(操作)、description(描述)
- 中间件 `CheckPermission` 支持 `permission:module.action` 格式
- 角色权限缓存到 Redis，减少数据库查询
- 预置权限覆盖：商品管理、订单管理、支付管理、物流管理、用户管理、系统设置

---

### TASK-P1-005: 商品映射服务 ProductMappingService

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | **P0 安全级（最高优先级）** |
| **预估工时** | 2d |
| **前置依赖** | TASK-P1-001 |
| **输出物** | `app/Services/ProductMappingService.php`、`app/Enums/SkuCategory.php`、单元测试文件 |

**验收标准：**
1. SKU 前缀识别覆盖 hic/WPZ/DIY/NBL 四种分类，空 SKU 或长度 ≤ 3 返回"未知"
2. 三级优先级查询链路正确：精确映射 → SKU 前缀通用名 → 兜底默认名
3. 精确映射 CRUD 操作正常
4. 安全名称库管理功能正常
5. 场景使用规则正确：支付/物流用安全名、前台/Pixel 用真实名、价格永不替换
6. **单元测试覆盖率 100%**，包含所有边界场景
7. 精确映射被删除时自动回退到 SKU 前缀规则
8. 安全名称库为空时兜底名称仍可用

**技术要点：**
- `getSafeName(Product $product, string $scenario): string` — 核心方法
- `identifySku(string $sku): SkuCategory` — SKU 分类识别
- 映射优先级：精确映射(jh_product_safe_mappings) → SKU 前缀通用名(hic→"Sports Jersey", WPZ→原名, DIY→"Custom Print Shirt") → 兜底默认名("Sports Training Jersey")
- 场景枚举：PAYMENT / LOGISTICS / STOREFRONT / PIXEL / ADMIN
- 结果缓存到 Redis（TTL 1 小时），映射变更时清除缓存
- ⚠️ **此服务是 Phase 3 所有支付/物流任务的前置依赖**

---

### TASK-P1-006: Vue 3 管理后台项目初始化

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 1d |
| **前置依赖** | 无 |
| **输出物** | `jerseyholic-admin/` 项目根目录 |

**验收标准：**
1. `npm run dev` 项目可正常启动
2. Axios 封装完成，统一请求/响应拦截器（Token 注入、错误处理）
3. 路由守卫就绪（登录态检查、权限检查）
4. Element Plus 组件库引入并配置主题
5. 基础布局组件（侧边栏、顶部导航、面包屑）就绪
6. 环境变量配置（API 地址等）

**技术要点：**
- Vue 3 + TypeScript + Vite + Element Plus
- Pinia 状态管理
- 路由按模块分组，懒加载
- 封装通用组件：表格、表单、搜索、分页

---

### TASK-P1-007: Nuxt 3 买家商城项目初始化

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 1d |
| **前置依赖** | 无 |
| **输出物** | `jerseyholic-storefront/` 项目根目录 |

**验收标准：**
1. `npm run dev` SSR 模式可正常运行
2. @nuxtjs/i18n 配置完成，16 种语言 locale 文件就绪
3. URL 前缀路由策略生效（`/{locale}/...`）
4. 语言检测优先级正确：URL → Cookie → 浏览器 → 默认 → 英语
5. TailwindCSS 配置完成，RTL 支持预备
6. SEO 基础配置（useHead、useSeoMeta）就绪

**技术要点：**
- Nuxt 3 + @nuxtjs/i18n + TailwindCSS
- i18n 策略：prefix_except_default（默认英语无前缀）
- 16 种语言 locale 文件初始化（P0 语言优先翻译，其他占位）
- 布局系统：default（带头尾导航）、checkout（简化结账布局）
- composable 目录结构规划

---

### TASK-P1-008: 旧系统数据分析与映射文档

| 属性 | 值 |
|------|-----|
| **负责人** | @migration-specialist |
| **优先级** | P1 |
| **预估工时** | 3d |
| **前置依赖** | 无 |
| **输出物** | `docs/migration/data-mapping.md`、`docs/migration/data-statistics.md` |

**验收标准：**
1. OpenCart 数据库所有业务表字段映射到新系统表结构
2. ThinkPHP 数据库所有业务表字段映射到新系统表结构
3. 两系统重叠数据（订单、支付记录）的合并策略文档
4. 各表数据量统计完成（行数、占用空间）
5. 数据清洗规则定义（脏数据识别、枚举值映射）
6. 迁移优先级和批次规划

**技术要点：**
- 分析 OpenCart `oc_*` 表结构（商品、订单、客户、分类、语言等）
- 分析 ThinkPHP `tp_*` 表结构（订单、支付账号、物流、争议等）
- 重点关注：订单数据两系统合并（OC 前台订单 + TP 支付记录 → 新系统统一订单）
- 标记不可迁移/需手动处理的数据
- 评估迁移耗时和风险

---

## 并行策略

| 时间段 | @architect | @backend-dev | @frontend-dev | @migration-specialist |
|--------|-----------|-------------|--------------|----------------------|
| Day 1-3 | P1-001 数据库设计 | P1-002 项目初始化 → P1-003 认证系统 | P1-006 Vue 初始化 + P1-007 Nuxt 初始化 | P1-008 数据分析 |
| Day 4-5 | 评审/支持 | P1-003 认证系统（续）→ P1-004 RBAC | 基础组件开发 | P1-008 数据分析（续） |
| Day 6-7 | 评审/支持 | P1-005 商品映射服务 | Mock 数据准备 | P1-008 映射文档 |
| Day 8 | — | P1-004 RBAC（续）+ P1-005 验收 | — | — |

> ⚠️ **关键节点**：P1-005 必须在 Day 8 前完成验收，否则 Phase 2/3 的支付物流任务将被阻塞。
