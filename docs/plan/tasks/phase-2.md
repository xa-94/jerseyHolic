# Phase 2：核心电商功能（第 3-5 周）

> 目标：完成商品管理、订单流程、购物车、用户中心、Facebook Pixel 等核心电商功能  
> 里程碑：M2 — 核心电商流程跑通（浏览→加购→下单），后台管理基本可用

---

## 📊 Phase 2 进度看板

### 待开始 (To Do)
- [ ] TASK-P2-001: 商品管理 API（CRUD + 多语言描述 + 变体） (@backend-dev) [P0]
- [ ] TASK-P2-002: 分类管理 API（树形结构） (@backend-dev) [P1]
- [ ] TASK-P2-003: 订单管理 API（创建/查询/状态流转） (@backend-dev) [P0]
- [ ] TASK-P2-004: 购物车 API (@backend-dev) [P0]
- [ ] TASK-P2-005: 用户中心 API（注册/登录/地址簿/订单历史） (@backend-dev) [P1]
- [ ] TASK-P2-006: 后台商品管理页面（含映射管理） (@frontend-dev) [P0]
- [ ] TASK-P2-007: 后台订单管理页面 (@frontend-dev) [P1]
- [ ] TASK-P2-008: 买家商城首页 + 商品列表 + 详情页（SSR） (@frontend-dev) [P0]
- [ ] TASK-P2-009: 购物车 + 结账流程前端 (@frontend-dev) [P0]
- [ ] TASK-P2-010: Facebook Pixel 后端配置 API (@backend-dev) [P1]
- [ ] TASK-P2-011: useFacebookPixel composable 实现 (@frontend-dev) [P1]

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

### TASK-P2-001: 商品管理 API（CRUD + 多语言描述 + 变体）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P1-001, TASK-P1-005 |
| **输出物** | `app/Http/Controllers/Admin/ProductController.php`、`app/Services/ProductService.php`、`app/Models/Product.php` 及关联模型 |

**验收标准：**
1. 商品 CRUD 全流程正常（创建、编辑、删除、列表查询）
2. 多语言描述正确存储和查询（16 种语言，每语言独立标题/描述/meta）
3. 变体/SKU 组合生成正确（尺码/颜色等选项组合）
4. SKU 前缀自动识别（hic/WPZ/DIY/NBL）并标记分类
5. 商品图片管理（多图上传、主图设置、排序）
6. 商品价格管理（基础价、特殊价/促销价、多货币转换）
7. 库存管理（库存跟踪、缺货提醒、允许负库存配置）
8. 后台列表同时返回真实名和安全映射名（调用 ProductMappingService）

**技术要点：**
- 商品描述使用 `product_descriptions` 表，每语言一条记录
- SKU 编码规则：前 3 位为分类前缀（hic/WPZ/DIY/NBL）
- 特殊价格按时间段自动切换（`special_price` + `date_start` + `date_end`）
- 列表查询支持分面搜索、属性过滤、关键词搜索
- 图片存储使用 Laravel Storage，缩略图使用 Intervention Image

---

### TASK-P2-002: 分类管理 API（树形结构）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 1d |
| **前置依赖** | TASK-P1-001 |
| **输出物** | `app/Http/Controllers/Admin/CategoryController.php`、`app/Models/Category.php` |

**验收标准：**
1. 分类树 CRUD 正常（创建、编辑、删除、排序）
2. 多语言分类名支持
3. 无限级树形结构
4. 分类排序功能
5. 前台分类树查询 API（带商品计数）

**技术要点：**
- 使用 `parent_id` 实现树形结构，递归查询或 Nested Set 模型
- 分类描述多语言存储（`category_descriptions` 表）
- 分类图片和 SEO URL 管理

---

### TASK-P2-003: 订单管理 API（创建/查询/状态流转）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P2-001 |
| **输出物** | `app/Http/Controllers/Admin/OrderController.php`、`app/Services/OrderService.php`、`app/Models/Order.php` |

**验收标准：**
1. 订单创建正确记录所有字段，生成唯一订单号
2. SKU 分类标记正确（is_zw/is_diy/is_wpz 标记）
3. 支付状态 9 种枚举流转正确
4. 发货状态枚举流转正确
5. 退款状态/纠纷状态枚举流转正确
6. 每次状态变更记录历史（操作员 + 备注）
7. 多条件搜索正确（订单号/状态/日期/域名/支付类型/SKU 类型）
8. 订单超时自动取消（定时任务）
9. 退货/退款申请流程（全额/部分退款）
10. 订单导出功能（Excel）

**技术要点：**
- 订单状态机设计，使用枚举类定义所有状态值
- 参考 BR-ORD-001 ~ BR-ORD-008 业务规则
- 订单号生成规则：保留 `a_order_no`（旧系统 ID）字段便于迁移
- 金额计算 Pipeline：小计 + 税费 + 运费 - 折扣 = 总计
- 订单创建时锁定当前汇率
- 使用 Laravel Scheduled Task 实现订单超时自动取消

---

### TASK-P2-004: 购物车 API

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-001 |
| **输出物** | `app/Http/Controllers/Buyer/CartController.php`、`app/Services/CartService.php` |

**验收标准：**
1. 购物车增删改查正常
2. 数量调整实时更新小计
3. 库存校验：加购时检查库存，下单时再次校验
4. 游客购物车支持（Session 存储）
5. 登录后购物车合并
6. 运费实时计算（根据地址和重量）
7. 多货币价格显示

**技术要点：**
- 登录用户购物车存数据库，游客存 Session/Cookie
- 购物车项包含：商品 ID、变体 ID、数量、单价
- 金额计算服务独立封装（CartCalculationService）

---

### TASK-P2-005: 用户中心 API（注册/登录/地址簿/订单历史）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P1-003 |
| **输出物** | `app/Http/Controllers/Buyer/AccountController.php`、`app/Http/Controllers/Buyer/AddressController.php` |

**验收标准：**
1. 买家注册（邮箱 + 密码）流程正常
2. 买家登录/登出流程正常
3. 密码重置邮件发送和链接处理正常
4. 地址簿 CRUD 正常，支持默认地址设置
5. 买家订单历史查看
6. 个人信息编辑
7. 游客结账支持（不注册直接下单）

**技术要点：**
- 密码重置链接一次性有效且 24 小时过期
- 地址包含：国家、州/省、城市、地址行 1/2、邮编、电话
- 买家只能查看自己的订单（数据隔离）

---

### TASK-P2-006: 后台商品管理页面（含映射管理）

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P1-006 |
| **输出物** | `jerseyholic-admin/src/views/product/` 目录下页面组件 |

**验收标准：**
1. 商品列表页：支持搜索、筛选（分类/状态/SKU 类型）、分页
2. 商品列表同时显示真实名称和安全映射名称
3. 商品编辑页：基本信息、多语言描述（Tab 切换 16 语言）、变体管理、图片上传、价格设置
4. 商品映射配置页面：精确映射管理、安全名称库管理
5. 分类管理页面：树形结构展示、拖拽排序
6. 批量操作（状态切换、删除）

**技术要点：**
- 商品编辑使用多 Tab 布局（基本信息/多语言/变体/图片/SEO/映射）
- 映射管理集成到商品编辑页的独立 Tab
- 使用 Element Plus Tree 组件展示分类树
- 图片上传使用拖拽排序组件
- 初期可使用 Mock 数据开发，后端 API 就绪后联调

---

### TASK-P2-007: 后台订单管理页面

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P1-006 |
| **输出物** | `jerseyholic-admin/src/views/order/` 目录下页面组件 |

**验收标准：**
1. 订单列表页：多条件搜索（订单号/状态/日期/域名/支付类型）、分页
2. 订单详情页：订单信息、商品明细（真实名 + 安全名）、地址、支付信息、物流轨迹
3. 订单状态操作：手动更新支付状态、发货状态
4. 退款操作界面（全额/部分退款）
5. 订单历史时间线展示
6. 订单导出功能
7. 补货订单创建

**技术要点：**
- 订单详情使用多区块布局（基本信息/商品明细/支付记录/物流轨迹/操作日志）
- 状态流转可视化（状态机图或进度条）
- 商品明细同时展示真实名和安全映射名（参考 BR-MAP-003）

---

### TASK-P2-008: 买家商城首页 + 商品列表 + 详情页（SSR）

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P0 |
| **预估工时** | 4d |
| **前置依赖** | TASK-P1-007 |
| **输出物** | `jerseyholic-storefront/pages/` 目录下页面组件 |

**验收标准：**
1. 首页：Banner 轮播、热门商品推荐、分类导航
2. 商品列表页：分类筛选、排序（价格/热度/最新）、分页、分面搜索
3. 商品详情页：图片画廊、多语言描述、变体选择、价格展示、加购按钮
4. SSR 渲染正常（服务端获取数据，客户端激活）
5. SEO 元信息完整（title/description/og:image/hreflang 标签）
6. 16 语言路由切换正常（`/{locale}/product/...`）
7. 响应式布局（PC + 移动端适配）
8. **商品展示使用真实商品名称（非安全映射名）**

**技术要点：**
- 使用 `useAsyncData` / `useFetch` 进行 SSR 数据获取
- hreflang 标签自动生成（每个页面生成 16 语言的 alternate 链接）
- 图片懒加载 + WebP 格式优化
- 缺失翻译自动回退到英语

---

### TASK-P2-009: 购物车 + 结账流程前端

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P2-008 |
| **输出物** | `jerseyholic-storefront/pages/cart.vue`、`jerseyholic-storefront/pages/checkout/` |

**验收标准：**
1. 购物车页面：商品列表、数量调整、小计实时计算、删除商品
2. 结账流程：收货地址 → 配送方式 → 支付方式 → 确认 → 支付
3. 游客结账支持（不需登录）
4. 运费实时计算展示
5. 优惠券输入框（预留，Phase 4 实现逻辑）
6. 订单金额明细展示（小计 + 运费 - 折扣 = 总计）
7. 多货币价格切换
8. 订单确认页面

**技术要点：**
- 购物车状态使用 Pinia store 管理
- 结账流程使用步骤组件（Step 1-4）
- 地址表单复用地址簿组件
- 支付方式选择界面（Phase 3 对接实际支付 SDK）

---

### TASK-P2-010: Facebook Pixel 后端配置 API

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 1d |
| **前置依赖** | TASK-P1-001 |
| **输出物** | `app/Http/Controllers/Admin/PixelConfigController.php`、`app/Models/PixelConfig.php` |

**验收标准：**
1. Pixel 配置 CRUD 正常（按店铺/语言配置 Pixel ID）
2. 多像素 ID 支持（逗号分隔多个 Pixel ID）
3. 事件开关管理（可单独启用/禁用每种事件类型）
4. 前台 API：根据域名 + 语言获取当前 Pixel 配置
5. 配置变更后自动清除前端缓存

**技术要点：**
- `pixel_configs` 表：store_id, locale, pixel_ids, enabled_events(JSON), status
- 前台 API 缓存配置数据（Redis TTL 1 小时）

---

### TASK-P2-011: useFacebookPixel composable 实现

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-010, TASK-P2-008 |
| **输出物** | `jerseyholic-storefront/composables/useFacebookPixel.ts` |

**验收标准：**
1. 12+ 种事件追踪正常（PageView, ViewContent, AddToCart, Purchase, InitiateCheckout, AddPaymentInfo, Search, AddToWishlist, CompleteRegistration, Contact, Lead, Subscribe）
2. 多像素 ID 各自独立初始化和追踪
3. SSR 兼容：仅在客户端执行 fbq 调用，不在服务端触发
4. 事件开关：根据后端配置控制事件是否触发
5. **所有事件使用真实商品名称和真实价格（⚠️ 不使用安全映射名）**
6. content_ids 使用真实商品 ID

**技术要点：**
- 使用 `onMounted` 确保仅客户端执行
- fbq 脚本动态加载（避免 SSR 报错）
- 事件数据格式参考 Facebook Pixel 标准参数
- PageView 事件使用 Nuxt 路由守卫全局触发
- Purchase 事件在支付成功回调页触发

---

## 并行策略

| 时间段 | @backend-dev | @frontend-dev |
|--------|-------------|--------------|
| Week 3 | P2-001 商品 API + P2-002 分类 API + P2-010 Pixel API | P2-006 后台商品页面 + P2-008 商城页面（Mock 数据） |
| Week 4 | P2-003 订单 API + P2-004 购物车 API | P2-008 商城页面（续）+ P2-007 后台订单页面 |
| Week 5 | P2-005 用户中心 API + 联调支持 | P2-009 购物车+结账前端 + P2-011 Pixel composable + 联调 |

> ⚠️ **联调时间**：Week 5 后半段开始前后端联调，后端 API 需在 Week 4 末通过单测。
