---
title: 商户管理模块 PRD
module: MCH (Merchant Management)
version: v2.0
updated: 2026-04-16
status: Draft
priority: P0
---

# 商户管理模块 PRD

> 优先级：**P0** | 版本：v2.0 | 更新日期：2026-04-16
> 关联业务规则：BR-MCH-001 ~ BR-MCH-012, BR-SEC-001 ~ BR-SEC-002
> 依赖调研报告：业务模式分析、多租户架构、支付资金池与结算

## 1. 模块概述

### 模块定位

商户管理模块是 JerseyHolic 平台的**核心运营基座**，负责管理平台上所有商户的全生命周期，包括入驻审核、站点创建与配置、商户后台、佣金结算和风控监管。

### 业务价值

- **多商户运营**：支持多个商户同时运营各自品类与市场，收入集中到平台统一管理
- **风险分散**：通过多站点矩阵（独立域名+独立数据库），单一站点被封不影响其他站点
- **自动化结算**：按商户维度聚合所有站点收入，自动计算佣金和可结算金额
- **精细化管控**：基于商户等级实现差异化佣金、功能权限和风控策略

### 核心目标

1. 实现商户从入驻到运营的完整管理闭环
2. 支持**一个商户管理多个独立站（1:N 关系）**，每个站点完全数据隔离
3. 提供商户独立后台：查看订单、管理商品、查看结算（**不管理支付账号**）
4. 按商户维度聚合结算（非站点维度），支持佣金阶梯计算
5. 建立商户风险评估体系，异常行为自动预警

### 影响范围

- 支付系统模块（站点→收款分组映射、支付账号由平台管理）
- 订单管理模块（商户后台跨站点订单聚合查看）
- 商品管理模块（主商品库→站点商品同步）
- 物流管理模块（站点级物流渠道配置）
- 多语言模块（站点级语言配置）
- 斗篷系统（站点级 Nginx 域名配置）

---

## 2. 术语定义

| 术语 | 英文 | 定义 |
|------|------|------|
| 平台 | Platform | JerseyHolic 系统整体，拥有所有支付账号和基础设施 |
| 商户 | Merchant | 入驻平台的运营方，负责选品、推广和客服，拥有独立后台 |
| 站点 | Store | 商户名下的一个独立站，拥有独立域名和独立数据库 |
| 租户 | Tenant | 技术层面对应一个 Store，通过 stancl/tenancy 实现数据库隔离 |
| 中央库 | Central DB | `jerseyholic_central`，存储商户、站点、支付账号、结算等全局数据 |
| 商户库 | Merchant DB | `jerseyholic_merchant_{id}`，存储商户级主商品数据和同步规则 |
| 站点库 | Store DB | `jerseyholic_store_{id}`，存储站点级商品、订单、客户等业务数据 |
| 佣金 | Commission | 平台从商户销售额中抽取的比例，按商户等级和品类计算 |
| 结算 | Settlement | 平台将扣除佣金后的金额支付给商户的过程 |
| 主商品库 | Master Product Library | 商户级别的商品主数据，可同步到名下各站点 |

---

## 3. 用户角色

| 角色 | 英文 | 权限范围 | 说明 |
|------|------|---------|------|
| 平台超级管理员 | Super Admin | 全局所有功能 | 商户审核、支付账号管理、结算审批、系统配置 |
| 平台运营 | Platform Operator | 商户管理、站点配置、数据查看 | 日常运营管理，不可修改支付账号凭证 |
| 商户管理员 | Merchant Admin (owner) | 商户级全部功能 | 商户主账号，管理所有站点和子账号 |
| 商户运营 | Merchant Manager | 商户级部分功能 | 订单管理、商品管理、数据查看 |
| 商户操作员 | Merchant Operator | 指定站点的操作权限 | 通过 `allowed_store_ids` 限制可访问站点 |

---

## 4. 功能需求

### 4.1 商户入驻管理

#### F-MCH-010: 商户注册申请

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 商户通过注册表单提交入驻申请，提供基本信息和经营计划 |

**输入**：
- 商户名称、联系人姓名、联系邮箱、联系电话
- 经营品类（球衣/鞋类/配饰/其他）
- 目标市场（国家/地区列表）
- 预估月销量

**输出**：
- 创建 `jh_merchants` 记录，状态为 `pending`
- 发送邮件确认通知给商户
- 通知平台管理员有新的入驻申请

**业务规则**：
- BR-MCH-001: 商户邮箱唯一，不可重复注册
- BR-MCH-002: 商户 slug 自动从名称生成，用于数据库命名

---

#### F-MCH-011: 商户审核流程

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 平台管理员审核商户申请，可通过、拒绝或要求补充材料 |

**输入**：
- 商户 ID、审核结果（approved/rejected/need_info）
- 审核备注
- 初始佣金比例、初始商户等级

**输出**：
- 通过：商户状态变为 `active`，自动创建商户库（`jerseyholic_merchant_{id}`）
- 拒绝：商户状态变为 `rejected`，发送拒绝原因邮件
- 补充材料：商户状态变为 `info_required`，发送通知

**业务规则**：
- BR-MCH-003: 审核通过后自动初始化商户库（master_products、sync_rules 等表）
- 审核记录需保留操作员和时间戳

---

#### F-MCH-012: 商户信息管理

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 管理商户基本信息、联系方式、银行结算信息 |

**输入/输出**：
- 基本信息：名称、slug、联系人、邮箱、电话
- 银行信息：开户行、账户名、账号、SWIFT Code（用于线下结算）
- 扩展设置：JSON 格式自定义配置

**业务规则**：
- 银行信息字段加密存储
- 商户可编辑自身基本信息，银行信息修改需平台审核

---

#### F-MCH-013: 商户状态管理

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | S |
| **描述** | 管理商户的生命周期状态 |

**状态枚举**：

| 状态 | 值 | 说明 | 可转换到 |
|------|-----|------|---------|
| 待审核 | pending | 新注册，等待平台审核 | active, rejected, info_required |
| 需补充信息 | info_required | 需补充资料 | pending |
| 正常 | active | 审核通过，正常运营中 | suspended, banned |
| 已拒绝 | rejected | 审核未通过 | pending（允许重新申请） |
| 已暂停 | suspended | 临时停止运营（平台主动暂停） | active, banned |
| 已封禁 | banned | 永久封禁 | — |

**业务规则**：
- BR-MCH-004: 商户暂停时，名下所有站点自动切换为 `maintenance` 状态
- BR-MCH-004: 商户封禁时，名下所有站点切换为 `inactive` 状态，停止接收新订单

---

#### F-MCH-014: 商户等级管理

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 商户分为入门/标准/高级/VIP 四个等级，不同等级享受不同佣金和功能 |

**等级定义**：

| 等级 | 英文 | 基础佣金 | 月均成交额门槛 | 最大站点数 | 功能限制 |
|------|------|---------|-------------|----------|---------|
| 入门 | starter | 20%-25% | — | 2 | 基础功能 |
| 标准 | standard | 15%-20% | ≥$3,000 | 5 | 全部功能 |
| 高级 | advanced | 10%-15% | ≥$15,000 | 10 | 全部功能 + 优先支持 |
| VIP | vip | 8%-12% | ≥$50,000 | 不限 | 全部功能 + 专属客服 + 自定义佣金 |

**业务规则**：
- BR-MCH-005: 等级可手动调整，也可基于近3个月平均成交额自动升/降级
- 升级即时生效，降级需确认并通知商户
- 佣金下限 8%，上限 35%

---

### 4.2 站点管理

#### F-MCH-020: 站点创建

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | XL |
| **描述** | 为商户创建新站点，自动创建独立数据库并初始化表结构 |

**输入**：
- 站点名称、域名
- 目标市场、支持语言、支持货币、主货币
- 经营品类、时区

**输出**：
- 在 Central DB 创建 `jh_stores` 记录
- 自动创建租户数据库 `jerseyholic_store_{id}`
- 运行租户数据库迁移（products、orders、customers 等全套业务表）
- 异步生成 Nginx 配置和 SSL 证书

**创建流程**：
```
1. 验证域名唯一性
2. 验证商户站点数量未超过等级限制
3. 在 Central DB 创建站点记录
4. CREATE DATABASE jerseyholic_store_{id}
5. 运行 tenant migrations（初始化业务表）
6. 异步：生成 Nginx 配置 → 申请 SSL 证书
7. 站点状态设为 active
```

**业务规则**：
- BR-MCH-006: 站点数量受商户等级限制（入门≤2、标准≤5、高级≤10、VIP不限）
- 域名全局唯一，同一域名不可绑定到不同站点
- 数据库凭证加密存储（Crypt::encryptString）

---

#### F-MCH-021: 站点域名配置

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 配置和管理站点域名，支持通配符子域名和自定义域名 |

**支持的域名模式**：
- 子域名：`store-name.jerseyholic.com`（默认，平台通配符 SSL 覆盖）
- 自定义域名：`www.custom-store.com`（需商户配置 DNS CNAME 指向平台）

**业务规则**：
- 域名变更后需重新生成 Nginx 配置
- 自定义域名需验证 DNS 解析正确后才能激活
- 旧域名保留 301 重定向（可配置时长）

---

#### F-MCH-022: 站点状态管理

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | S |
| **描述** | 管理站点运行状态 |

**状态枚举**：

| 状态 | 值 | 说明 |
|------|-----|------|
| 运行中 | active | 正常接收流量和订单 |
| 维护中 | maintenance | 显示维护页面，不接受新订单，可处理已有订单 |
| 已关闭 | inactive | 完全关闭，域名不响应 |

---

#### F-MCH-023: 站点品类配置

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | S |
| **描述** | 配置站点经营的商品品类，限定同步到该站点的商品范围 |

**输入**：品类标签列表，如 `["jerseys", "shoes", "accessories"]`

**业务规则**：
- 商品同步时，可按站点品类过滤只同步匹配品类的商品
- 品类列表存储在 `jh_stores.product_categories`（JSON 字段）

---

#### F-MCH-024: 站点目标市场配置

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | S |
| **描述** | 配置站点面向的国家/地区市场 |

**输入**：ISO 3166 国家代码列表，如 `["US", "CA", "MX"]`

**业务规则**：
- 目标市场影响运费计算、税费规则、支付方式可用性
- 存储在 `jh_stores.target_markets`（JSON 字段）

---

#### F-MCH-025: 站点语言配置

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | S |
| **描述** | 配置站点支持的语言列表 |

**输入**：语言代码列表，如 `["en", "es", "fr"]`

**业务规则**：
- 商品同步时自动匹配对应语言的翻译数据
- 需在系统支持的 16 种语言范围内选择
- 包含 `ar`（阿拉伯语）时前端需启用 RTL 布局
- 存储在 `jh_stores.supported_languages`（JSON 字段）

---

#### F-MCH-026: 站点货币配置

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | S |
| **描述** | 配置站点支持的货币和主货币 |

**输入**：
- 支持货币列表：如 `["USD", "EUR", "GBP"]`
- 主货币：如 `"USD"`

**业务规则**：
- 主货币用于后台统计和结算基准
- 支付金额以买家选择的货币提交
- 存储在 `jh_stores.supported_currencies` 和 `jh_stores.primary_currency`

---

#### F-MCH-027: 站点支付方式偏好配置

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 配置站点可用的支付方式和支付账号分组映射 |

**输入**：
- 可用支付方式：如 `["paypal", "stripe", "credit_card"]`
- PayPal 收款分组 ID（`group_id`）
- 信用卡收款分组 ID（`cc_group_id`）

**业务规则**：
- **支付账号归平台所有**，商户不可查看/管理支付账号凭证
- 平台管理员通过 `jh_store_payment_accounts` 表为站点分配可用支付账号
- 站点域名→收款分组的映射关系是支付选号引擎（ElectionService）的核心输入
- 存储在 `jh_stores.settings.payment_methods` 及 `jh_store_payment_accounts` 关联表

---

#### F-MCH-028: 站点物流渠道配置

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 配置站点可用的物流服务商和运费规则 |

**输入**：
- 物流服务商列表：如 `["fedex", "usps", "dhl"]`
- 免运费门槛金额
- 地区限制（不可配送地区）
- 预估配送时间

**业务规则**：
- 物流面单商品名称必须使用安全映射名称（BR-MAP-003）
- 存储在 `jh_stores.settings.shipping_providers` 及相关 JSON 配置

---

#### F-MCH-029: 站点主题/外观配置

| 属性 | 值 |
|------|-----|
| **优先级** | P2 |
| **复杂度** | M |
| **描述** | 配置站点前台外观，包括配色、Logo、SEO 信息 |

**输入**：
- 主色调、Logo URL、Favicon
- 站点标题、描述（SEO）
- 社交媒体链接
- Facebook Pixel ID

**业务规则**：
- 存储在 `jh_stores.settings.theme` 和 `jh_stores.settings.seo`（JSON 字段）
- Pixel 配置影响 Facebook Pixel 模块的事件追踪初始化

---

### 4.3 商户后台

#### F-MCH-030: 商户登录

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 商户用户通过独立认证体系登录商户后台 |

**输入**：用户名/邮箱 + 密码

**输出**：Sanctum Token（guard: `merchant`）

**业务规则**：
- BR-MCH-007: 商户认证体系与平台管理员、买家三者完全独立（使用不同 guard）
- 商户用户存储在 `jh_merchant_users` 表（Central DB）
- 支持"记住我"（Token 有效期延长至 30 天）
- 登录失败 5 次锁定 15 分钟

---

#### F-MCH-031: 仪表盘

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | L |
| **描述** | 商户登录后首页，汇总展示所有站点核心数据 |

**展示内容**：
- 今日/本周/本月总销售额（所有站点聚合）
- 今日/本周/本月订单数
- 各站点销售额占比
- 待处理事项（待发货订单、待处理退款等）
- 最近订单列表
- 结算余额概览

**数据来源**：遍历商户名下所有 Store DB 聚合计算

---

#### F-MCH-032: 站点切换

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | S |
| **描述** | 商户在后台切换当前管理的站点上下文 |

**业务规则**：
- 默认进入"全部站点"汇总视图
- 选择特定站点后，后续操作限定在该站点范围内
- 操作员（operator 角色）只能看到 `allowed_store_ids` 中的站点

---

#### F-MCH-033: 订单管理（只读）

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | L |
| **描述** | 商户查看所有站点的订单，支持按站点/状态/日期筛选 |

**功能说明**：
- 订单列表：支持按站点、支付状态、发货状态、日期范围筛选
- 订单详情：查看订单信息、商品明细、收货地址、支付信息、物流轨迹
- **商户只能查看订单，不可修改订单状态**（订单状态由平台管理员和系统管理）
- 支持导出订单数据（CSV/Excel）

**数据来源**：查询各 Store DB 的 orders 表，聚合返回

---

#### F-MCH-034: 商品管理（主商品库）

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | L |
| **描述** | 商户在主商品库中管理商品数据，支持增删改查 |

**功能说明**：
- 商品 CRUD：名称、描述、价格、品类、品牌、图片、SKU/变体
- 多语言翻译：为每种语言配置独立的名称和描述
- 商品状态管理：草稿（draft）→ 上架（active）→ 下架（archived）

**数据来源**：Merchant DB（`jerseyholic_merchant_{id}`）的 `master_products` 表

---

#### F-MCH-035: 商品同步管理

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | XL |
| **描述** | 商户选择将主商品库中的商品同步到名下指定站点 |

**同步方式**：

| 方式 | 说明 | 实现 |
|------|------|------|
| 手动单品同步 | 选择商品 → 选择目标站点 → 确认同步 | API → dispatch Job |
| 手动批量同步 | 选择多个商品 → 选择目标站点 → 批量同步 | API → 批量 dispatch Job |
| 自动同步 | 商品保存时自动推送到已配置的站点 | Model Observer → dispatch Job |
| 定时全量同步 | 每日凌晨全量校验同步 | Laravel Scheduler |

**同步规则配置**（通过 `sync_rules` 表）：
- `target_store_ids`: 指定同步到哪些站点
- `excluded_store_ids`: 排除哪些站点
- `sync_fields`: 只同步哪些字段（如仅同步价格和库存）
- `price_strategy`: 价格策略（fixed/multiplier/market_based）
- `price_multiplier`: 价格倍率（如 1.15 = 加价 15%）
- `auto_sync`: 是否保存时自动同步

**同步冲突策略**：
- 默认主库优先：同步覆盖站点本地修改
- 没有 `sync_source_id` 的站点独有商品不受同步影响
- 使用 `updateOrInsert` 保证幂等性

**业务规则**：
- BR-MCH-008: 同步通过 Laravel Job Queue 异步执行，队列名 `product-sync`
- 同步失败自动重试 3 次，间隔 60 秒
- 每次同步记录日志到 `jh_product_sync_logs`

---

#### F-MCH-036: 销售数据统计

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 按站点/品类/时间维度展示销售数据 |

**统计维度**：
- 按站点：各站点销售额、订单数、客单价
- 按品类：各品类销售额占比
- 按时间：日/周/月趋势图
- 按市场：各目标市场的销售分布

---

#### F-MCH-037: 结算中心

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | L |
| **描述** | 商户查看佣金明细、结算记录和可结算金额 |

**展示内容**：
- 当前结算周期的销售额和预估佣金
- 各站点的收入明细
- 历史结算记录列表（周期、金额、状态）
- 退款/争议扣减明细
- 可结算金额（已确认但未付款）

**业务规则**：
- 商户只能查看结算数据，不可修改
- 结算金额以 USD 为基准，显示时可换算为本地货币

---

#### F-MCH-038: 商户用户管理

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 商户管理员管理子账号及其权限 |

**角色**：

| 角色 | 权限 |
|------|------|
| owner | 商户全部权限，管理子账号 |
| manager | 商品管理、订单查看、数据统计，不可管理账号 |
| operator | 限定站点的订单查看和商品操作 |

**业务规则**：
- owner 角色唯一（商户主账号），不可删除
- operator 通过 `allowed_store_ids` 字段限制可访问的站点
- 子账号的权限不可超过父账号

---

### 4.4 佣金与结算

#### F-MCH-040: 佣金规则配置

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | L |
| **描述** | 配置商户的佣金计算规则，支持按等级、品类和销量阶梯 |

**佣金计算**：
```
佣金金额 = 订单金额 × 佣金比例
佣金比例 = 基础佣金（由商户等级决定）
          - 成交量奖励（-1% ~ -5%，按月累计成交额阶梯）
          - 忠诚度奖励（-1% ~ -3%，按合作时长）
```

**佣金范围约束**：
- 下限：8%（VIP 大客户最低）
- 上限：35%（高风险商户最高）
- 默认：18%（新商户入驻默认值）

**业务规则**：
- BR-MCH-009: 佣金按订单维度实时计算，结算时聚合
- 退款订单的佣金同步扣回
- 争议（dispute）期间的订单暂不计入结算

---

#### F-MCH-041: 结算周期管理

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 按商户维度聚合所有站点数据，生成结算单 |

**结算周期**：
- 默认月结（每月 1 日生成上月结算单）
- VIP 商户可选周结
- 结算按商户维度聚合（非站点维度），details JSON 字段包含各站点明细

**结算流程**：
```
1. 系统自动聚合结算周期内所有站点的已完成订单
2. 计算总收入、总佣金、退款扣减、争议暂扣
3. 生成结算单（status: pending）
4. 平台管理员审核（status: confirmed）
5. 线下付款后标记（status: paid）
```

**业务规则**：
- BR-MCH-010: 结算聚合所有站点数据，按商户维度一笔结算
- 结算币种统一为 USD（多币种通过订单汇率换算）

---

#### F-MCH-042: 结算单生成与审核

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 结算单自动生成和平台审核流程 |

**结算单状态**：

| 状态 | 值 | 说明 |
|------|-----|------|
| 待审核 | pending | 系统自动生成，等待平台确认 |
| 已确认 | confirmed | 平台确认金额无误，等待付款 |
| 已付款 | paid | 线下已完成付款 |
| 已驳回 | rejected | 平台发现异常，驳回重算 |

---

#### F-MCH-043: 退款/争议对结算的影响

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 处理退款和争议对结算金额的影响 |

**规则**：
- 全额退款：订单金额和佣金均从结算中扣除
- 部分退款：按退款比例扣减
- 争议中的订单：金额暂时冻结，不计入当期结算
- 争议解决后：根据结果（商户胜/买家胜）补入或扣除

---

#### F-MCH-044: 结算报表导出

| 属性 | 值 |
|------|-----|
| **优先级** | P2 |
| **复杂度** | S |
| **描述** | 导出结算明细报表（Excel/PDF），包含各站点订单明细 |

---

### 4.5 API 安全认证管理

#### F-MCH-060: 密钥对生成

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 创建商户时自动生成 RSA-4096 密钥对，用于独立站与管理后台之间的 API 签名验证 |

**触发时机**：商户审核通过并创建首个站点时

**算法**：RSA-4096 + SHA256

**存储**：
- 公钥：存储在 Central DB `jh_merchant_api_keys` 表（PEM 格式）
- 私钥：加密后一次性展示给商户下载，系统不保留明文

**业务规则**：
- 每个商户/站点组合只能有一个 `active` 状态的密钥
- 密钥生成后默认有效期 365 天
- 密钥标识符（`key_id`）格式：`mk_` + 24位随机字符串

---

#### F-MCH-061: 密钥管理

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | L |
| **描述** | 查看、轮换、吊销商户 API 密钥 |

**功能说明**：
- 查看当前密钥状态（active / rotating / revoked / expired）
- 手动轮换密钥（生成新密钥对，旧密钥设置过渡期）
- 紧急吊销密钥（立即失效，阻断所有请求）
- 密钥过期提醒（到期前 30 天自动提醒商户）

**密钥轮换过渡期**：默认 24 小时（新旧密钥同时有效）

**轮换流程**：
```
1. 管理员/商户发起轮换请求
2. 系统生成新密钥对
3. 旧密钥状态变为 rotating
4. 新密钥状态为 active
5. 24 小时过渡期内新旧密钥均可验签
6. 过渡期结束后旧密钥自动变为 expired
```

**业务规则**：
- 轮换期间最多同时存在 2 个有效密钥（active + rotating）
- 紧急吊销无过渡期，立即生效
- 吊销需填写原因，记录审计日志

---

#### F-MCH-062: 密钥分发安全

| 属性 | 值 |
|------|-----|
| **优先级** | P0 |
| **复杂度** | M |
| **描述** | 安全地将私钥分发给商户（独立站持有私钥用于签名） |

**分发方案**：
- 生成后通过加密链接一次性下载（链接 24 小时有效，下载 1 次后失效）
- 私钥文件使用商户设置的密码加密（PKCS#8 + AES-256）
- 系统不保留私钥明文（仅保留公钥用于验签）

**安全约束**：
- 私钥下载链接通过安全通道（HTTPS）传递
- 下载链接包含一次性 Token，使用后立即失效
- 下载事件记录审计日志（时间、IP、User-Agent）
- 如商户未在 24 小时内下载，需重新生成密钥对

---

### 4.6 商户风控

#### F-MCH-050: 商户风险评分

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | L |
| **描述** | 基于商户所有站点数据综合评估风险分数 |

**评分维度**：

| 维度 | 权重 | 指标 |
|------|------|------|
| 退款率 | 30% | 所有站点退款订单占比（>5% 高风险） |
| 争议率 | 25% | PayPal 争议数量占比 |
| 销量异常 | 20% | 突增（>300%周环比）或突降 |
| 客诉率 | 15% | 客户投诉频率 |
| 合规性 | 10% | 商品映射完整度、物流时效 |

**风险等级**：
- 0-30 分：低风险（绿色）
- 31-60 分：中风险（黄色）— 增加监控频率
- 61-80 分：高风险（橙色）— 限制新站点创建、降低支付账号额度
- 81-100 分：极高风险（红色）— 触发自动暂停或人工审核

---

#### F-MCH-051: 商户等级自动调整

| 属性 | 值 |
|------|-----|
| **优先级** | P2 |
| **复杂度** | M |
| **描述** | 基于商户近 3 个月数据自动调整等级 |

**业务规则**：
- BR-MCH-011: 每月 1 日计算近 3 个月平均成交额，符合条件自动升级
- 连续 2 个月低于当前等级门槛则降级
- 等级调整通知商户（邮件+站内消息）
- 人工可覆盖自动调整结果

---

#### F-MCH-052: 异常行为监控与告警

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 监控商户异常行为并触发告警 |

**监控指标**：
- 退款率突增（单日>10%）
- 争议数量突增（单日>3笔）
- 销量异常波动（周环比>300%）
- 商品名称触发品牌关键词（合规风险）

**告警方式**：
- 钉钉群消息（实时）
- 平台管理后台告警面板
- 邮件通知（每日汇总）

---

#### F-MCH-053: 商户封禁/解封流程

| 属性 | 值 |
|------|-----|
| **优先级** | P1 |
| **复杂度** | M |
| **描述** | 商户封禁和解封的完整流程 |

**封禁流程**：
```
1. 平台管理员发起封禁 → 填写封禁原因
2. 商户状态变为 banned
3. 名下所有站点切换为 inactive
4. 冻结所有未结算金额
5. 通知商户（邮件）
```

**解封流程**：
```
1. 商户提交解封申请
2. 平台管理员审核
3. 通过：商户状态恢复 active，站点可逐个重新激活
4. 拒绝：维持封禁状态，通知商户
```

**业务规则**：
- BR-MCH-012: 封禁期间已有未完成订单正常处理完毕（不中断已付款订单的履约）
- 封禁商户的未结算金额冻结 180 天（与 PayPal 冻结周期对齐）

---

## 5. 非功能需求

### 性能要求

| 指标 | 要求 |
|------|------|
| 站点创建（含数据库初始化） | < 30 秒 |
| 商户仪表盘数据加载 | < 3 秒（≤10 站点聚合） |
| 订单聚合查询（跨站点） | < 5 秒 |
| 商品同步单品 | < 10 秒/站点 |
| 商品批量同步（100商品×5站点） | < 5 分钟（异步队列） |
| 结算单生成 | < 30 秒/商户 |

### 安全要求

| 要求 | 说明 |
|------|------|
| 数据隔离 | 每个站点独立数据库，完全隔离 |
| 权限隔离 | 商户只能访问自己名下的站点数据 |
| 凭证加密 | 数据库密码、银行信息使用 Laravel Crypt 加密存储 |
| 认证独立 | 平台管理员、商户用户、买家使用不同 guard |
| 审计日志 | 所有关键操作记录操作员和时间 |
| 支付隔离 | **商户不可查看/管理支付账号凭证，支付账号归平台所有** |

### 可扩展性

| 要求 | 说明 |
|------|------|
| 商户数量 | 初期支持 50 商户，设计支撑 500+ |
| 站点数量 | 初期支持 200 站点，设计支撑 2000+ |
| 数据库 | 通过 stancl/tenancy 自动管理租户数据库创建和切换 |
| 缓存隔离 | Redis 按租户自动添加前缀 `store_{id}:` |
| 队列隔离 | 按租户添加队列前缀，避免任务交叉 |

---

## 6. 数据模型

### 核心实体关系

```
Central DB (jerseyholic_central)
├── jh_merchants ──1:N──► jh_stores
│   ├── jh_merchant_users (1:N)
│   ├── jh_merchant_api_keys (1:N)
│   └── jh_settlement_records (1:N)
├── jh_stores ──N:M──► jh_payment_accounts
│   └── jh_store_payment_accounts (关联表)
├── jh_payment_accounts (平台所有)
└── jh_product_sync_logs

Merchant DB (jerseyholic_merchant_{id})
├── master_products
├── master_product_translations
└── sync_rules

Store DB (jerseyholic_store_{id})
├── products (sync_source_id → master_products.id)
├── orders / order_items / order_addresses
├── customers / customer_addresses
├── carts / cart_items
└── ... (完整业务表)
```

### 核心表字段

#### jh_merchants（商户主表 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | 商户 ID |
| name | VARCHAR(100) | 商户名称 |
| slug | VARCHAR(50) UNIQUE | 商户标识 |
| contact_name | VARCHAR(50) | 联系人 |
| contact_email | VARCHAR(100) UNIQUE | 联系邮箱 |
| contact_phone | VARCHAR(30) | 联系电话 |
| bank_info | TEXT (encrypted) | 银行结算信息（加密） |
| commission_rate | DECIMAL(5,2) | 当前佣金比例 |
| level | ENUM('starter','standard','advanced','vip') | 商户等级 |
| risk_score | INT DEFAULT 0 | 风险评分（0-100） |
| status | ENUM('pending','info_required','active','rejected','suspended','banned') | 状态 |
| settings | JSON | 扩展设置 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_stores（站点表 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | 站点 ID |
| merchant_id | BIGINT UNSIGNED FK | 所属商户 |
| name | VARCHAR(100) | 站点名称 |
| domain | VARCHAR(255) UNIQUE | 站点域名 |
| database_name | VARCHAR(100) | 租户数据库名 |
| db_host | VARCHAR(100) | 数据库主机 |
| db_port | INT DEFAULT 3306 | 数据库端口 |
| db_user | VARCHAR(100) | 数据库用户名（加密） |
| db_password | TEXT | 数据库密码（加密） |
| target_markets | JSON | 目标市场 |
| supported_languages | JSON | 支持语言 |
| supported_currencies | JSON | 支持货币 |
| primary_currency | VARCHAR(3) | 主货币 |
| product_categories | JSON | 品类 |
| timezone | VARCHAR(50) | 时区 |
| status | ENUM('active','maintenance','inactive') | 状态 |
| settings | JSON | 扩展设置（支付/物流/主题等） |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

#### jh_merchant_users（商户用户表 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | 用户 ID |
| merchant_id | BIGINT UNSIGNED FK | 所属商户 |
| username | VARCHAR(50) | 用户名 |
| email | VARCHAR(100) | 邮箱 |
| password | VARCHAR(255) | 密码（bcrypt） |
| name | VARCHAR(50) | 姓名 |
| role | ENUM('owner','manager','operator') | 角色 |
| allowed_store_ids | JSON NULL | 可访问站点（NULL=所有） |
| status | ENUM('active','disabled') | 状态 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

#### jh_settlement_records（结算记录表 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | 结算 ID |
| merchant_id | BIGINT UNSIGNED FK | 商户 ID |
| period_start | DATE | 周期开始 |
| period_end | DATE | 周期结束 |
| total_revenue | DECIMAL(12,2) | 总收入 |
| total_refunds | DECIMAL(12,2) | 退款总额 |
| commission_amount | DECIMAL(12,2) | 佣金金额 |
| settlement_amount | DECIMAL(12,2) | 应结算金额 |
| currency | VARCHAR(3) DEFAULT 'USD' | 结算币种 |
| status | ENUM('pending','confirmed','paid','rejected') | 状态 |
| details | JSON | 各站点明细 |
| reviewed_by | BIGINT UNSIGNED NULL | 审核人 |
| reviewed_at | TIMESTAMP NULL | 审核时间 |
| paid_at | TIMESTAMP NULL | 付款时间 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

#### jh_store_payment_accounts（站点-支付账号关联 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | ID |
| store_id | BIGINT UNSIGNED FK | 站点 ID |
| payment_account_id | BIGINT UNSIGNED FK | 支付账号 ID |
| is_active | TINYINT(1) DEFAULT 1 | 是否启用 |
| priority | INT DEFAULT 0 | 优先级 |

#### jh_merchant_api_keys（商户 API 密钥表 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | 主键 |
| merchant_id | BIGINT UNSIGNED FK | 商户 ID |
| store_id | BIGINT UNSIGNED NULL | 站点 ID（NULL = 商户级别） |
| key_id | VARCHAR(32) UNIQUE | 密钥标识符（公开的，用于请求头，格式 `mk_xxx`） |
| public_key | TEXT | RSA 公钥（PEM 格式，用于验签） |
| algorithm | VARCHAR(20) DEFAULT 'RSA-SHA256' | 签名算法 |
| key_size | INT DEFAULT 4096 | 密钥长度（2048/4096） |
| status | ENUM('active','rotating','revoked','expired') | 密钥状态 |
| activated_at | TIMESTAMP | 激活时间 |
| expires_at | TIMESTAMP NULL | 过期时间 |
| revoked_at | TIMESTAMP NULL | 吊销时间 |
| revoke_reason | VARCHAR(255) NULL | 吊销原因 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_product_sync_logs（商品同步日志 — Central DB）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK | 日志 ID |
| merchant_id | BIGINT UNSIGNED FK | 商户 |
| source_store_id | BIGINT UNSIGNED NULL | 来源站点（NULL=主库） |
| target_store_id | BIGINT UNSIGNED FK | 目标站点 |
| sync_type | ENUM('full','incremental') | 同步类型 |
| trigger_type | ENUM('manual','auto','scheduled') | 触发方式 |
| total_products | INT | 商品总数 |
| success_count | INT | 成功数 |
| fail_count | INT | 失败数 |
| status | ENUM('pending','running','completed','failed') | 状态 |
| error_log | JSON | 错误详情 |
| started_at | TIMESTAMP | 开始时间 |
| completed_at | TIMESTAMP NULL | 完成时间 |

---

## 7. 接口清单

### 7.1 平台管理端 API

| 接口 | 方法 | 说明 | 优先级 |
|------|------|------|--------|
| /api/admin/merchants | GET | 商户列表（分页、搜索、状态过滤） | P0 |
| /api/admin/merchants | POST | 创建商户（管理员直接创建） | P0 |
| /api/admin/merchants/{id} | GET | 商户详情 | P0 |
| /api/admin/merchants/{id} | PUT | 更新商户信息 | P0 |
| /api/admin/merchants/{id}/review | POST | 审核商户（通过/拒绝） | P0 |
| /api/admin/merchants/{id}/status | PUT | 变更商户状态（暂停/封禁/解封） | P0 |
| /api/admin/merchants/{id}/level | PUT | 调整商户等级 | P1 |
| /api/admin/merchants/{id}/commission | PUT | 调整佣金比例 | P0 |
| /api/admin/stores | GET | 全部站点列表 | P0 |
| /api/admin/stores | POST | 创建站点 | P0 |
| /api/admin/stores/{id} | GET | 站点详情 | P0 |
| /api/admin/stores/{id} | PUT | 更新站点配置 | P0 |
| /api/admin/stores/{id}/status | PUT | 变更站点状态 | P0 |
| /api/admin/stores/{id}/payment-accounts | PUT | 分配支付账号到站点 | P0 |
| /api/admin/settlements | GET | 结算单列表 | P0 |
| /api/admin/settlements/{id} | GET | 结算单详情 | P0 |
| /api/admin/settlements/{id}/review | POST | 审核结算单（确认/驳回） | P0 |
| /api/admin/settlements/{id}/paid | POST | 标记已付款 | P0 |
| /api/admin/sync-logs | GET | 商品同步日志 | P1 |
| /api/admin/merchants/{id}/api-keys | GET | 商户 API 密钥列表 | P0 |
| /api/admin/merchants/{id}/api-keys/generate | POST | 生成商户 API 密钥对 | P0 |
| /api/admin/merchants/{id}/api-keys/{keyId}/rotate | POST | 轮换商户 API 密钥 | P0 |
| /api/admin/merchants/{id}/api-keys/{keyId}/revoke | POST | 吊销商户 API 密钥 | P0 |

### 7.2 商户后台 API

| 接口 | 方法 | 说明 | 优先级 |
|------|------|------|--------|
| /api/merchant/login | POST | 商户登录 | P0 |
| /api/merchant/profile | GET | 当前商户信息 | P0 |
| /api/merchant/profile | PUT | 更新商户信息 | P0 |
| /api/merchant/dashboard | GET | 仪表盘数据汇总 | P1 |
| /api/merchant/stores | GET | 名下站点列表 | P0 |
| /api/merchant/stores/{id} | GET | 站点详情 | P0 |
| /api/merchant/products | GET | 主商品库列表 | P0 |
| /api/merchant/products | POST | 创建主商品 | P0 |
| /api/merchant/products/{id} | GET | 主商品详情 | P0 |
| /api/merchant/products/{id} | PUT | 更新主商品 | P0 |
| /api/merchant/products/{id} | DELETE | 删除主商品 | P0 |
| /api/merchant/products/{id}/sync | POST | 同步商品到站点 | P0 |
| /api/merchant/products/batch-sync | POST | 批量同步商品 | P0 |
| /api/merchant/sync-rules | GET | 同步规则列表 | P1 |
| /api/merchant/sync-rules | POST | 创建同步规则 | P1 |
| /api/merchant/sync-rules/{id} | PUT | 更新同步规则 | P1 |
| /api/merchant/orders | GET | 订单列表（聚合所有站点） | P0 |
| /api/merchant/orders/{storeId}/{orderId} | GET | 订单详情 | P0 |
| /api/merchant/settlements | GET | 结算记录列表 | P0 |
| /api/merchant/settlements/{id} | GET | 结算单详情 | P0 |
| /api/merchant/analytics/overview | GET | 销售数据概览 | P1 |
| /api/merchant/analytics/by-store | GET | 按站点统计 | P1 |
| /api/merchant/users | GET | 子账号列表 | P1 |
| /api/merchant/users | POST | 创建子账号 | P1 |
| /api/merchant/users/{id} | PUT | 更新子账号 | P1 |
| /api/merchant/users/{id} | DELETE | 删除子账号 | P1 |
| /api/merchant/api-keys | GET | 我的 API 密钥列表 | P0 |
| /api/merchant/api-keys/download/{token} | GET | 一次性下载私钥文件 | P0 |

### 7.3 内部服务 API

| 接口/方法 | 说明 | 调用方 |
|----------|------|--------|
| StoreProvisioningService::createStore() | 创建站点+数据库 | 平台管理 API |
| ProductSyncService::syncProduct() | 同步单品到站点 | 商户后台/Scheduler |
| ProductSyncService::batchSync() | 批量同步 | 商户后台/Scheduler |
| SettlementService::generateSettlement() | 生成结算单 | Scheduler（月度） |
| MerchantRiskService::calculateRiskScore() | 计算风险评分 | Scheduler（每日） |
| TranslationSyncService::syncTranslations() | 同步多语言翻译 | ProductSyncService |

---

## 8. 业务规则

### BR-MCH-001: 商户唯一性

商户邮箱（`contact_email`）全局唯一，不可重复注册。商户 `slug` 从名称自动生成（Str::slug），用于数据库命名等标识用途。

### BR-MCH-002: 商户-站点 1:N 关系

一个商户可以拥有多个站点。每个站点拥有独立域名和独立数据库（`jerseyholic_store_{id}`），实现完全数据隔离。站点数量受商户等级限制。

### BR-MCH-003: 商户库初始化

审核通过后，系统自动创建商户库 `jerseyholic_merchant_{id}`，包含 `master_products`、`master_product_translations`、`sync_rules` 等表。

### BR-MCH-004: 商户状态级联

商户状态变更自动级联影响名下站点：
- 商户 `suspended` → 所有站点切换为 `maintenance`
- 商户 `banned` → 所有站点切换为 `inactive`
- 商户恢复 `active` → 站点不自动恢复，需逐个手动激活

### BR-MCH-005: 商户等级与佣金

| 等级 | 基础佣金 | 月均成交额门槛 | 最大站点数 |
|------|---------|-------------|----------|
| starter | 20%-25% | — | 2 |
| standard | 15%-20% | ≥$3,000 | 5 |
| advanced | 10%-15% | ≥$15,000 | 10 |
| vip | 8%-12% | ≥$50,000 | 不限 |

佣金最终值 = 基础佣金 - 成交量奖励(0~5%) - 忠诚度奖励(0~3%)，范围 [8%, 35%]。

### BR-MCH-006: 站点创建限制

创建站点前校验：
1. 商户状态必须为 `active`
2. 当前站点数量未达到等级上限
3. 域名全局唯一（查询 `jh_stores.domain`）

### BR-MCH-007: 认证体系隔离

系统三套独立认证（使用不同 Sanctum guard）：
- `admin` — 平台管理员（`jh_admins` 表）
- `merchant` — 商户用户（`jh_merchant_users` 表）
- `customer` — 买家（各 Store DB 的 `customers` 表）

### BR-MCH-008: 商品同步机制

- 异步队列执行，队列名 `product-sync`
- 使用 `sync_source_id` 字段关联主库和站点商品，`updateOrInsert` 保证幂等
- 失败自动重试 3 次，间隔 60 秒
- 每次同步记录日志（`jh_product_sync_logs`）

### BR-MCH-009: 佣金实时计算

订单支付成功后，系统根据当时的佣金比例实时计算该订单的佣金，记录在订单扩展数据中。退款时同步扣回佣金。

### BR-MCH-010: 结算按商户聚合

结算以商户为单位（非站点）。一个结算周期内，聚合商户名下所有站点的已完成订单，统一生成一笔结算单。`details` JSON 字段包含各站点的收入明细。

### BR-MCH-011: 等级自动调整

每月 1 日系统自动评估：
- 近 3 个月平均成交额达到上一等级门槛 → 自动升级
- 连续 2 个月低于当前等级门槛 → 降级
- 等级变更通知商户
- 平台管理员可手动覆盖

### BR-MCH-012: 封禁资金处理

商户封禁后：
- 未结算金额冻结 180 天
- 已付款未完成的订单继续履约
- 180 天后如无争议，冻结金额可释放

---

## 9. 页面原型描述

### 9.1 平台管理端

**商户列表页**：
- 顶部：搜索框（名称/邮箱）+ 状态筛选（全部/待审核/正常/暂停/封禁）+ 等级筛选
- 表格列：商户名称、等级（标签色彩）、佣金比例、站点数、本月成交额、风险分、状态、操作
- 操作：查看详情、编辑、审核、暂停/封禁
- 分页组件

**商户详情页**：
- Tab 1 — 基本信息：商户信息表单 + 银行信息
- Tab 2 — 站点列表：名下所有站点卡片/列表，支持创建新站点
- Tab 3 — 结算记录：结算单列表 + 审核操作
- Tab 4 — 风控数据：风险评分雷达图 + 各维度指标 + 异常事件时间线
- Tab 5 — 操作日志：所有管理操作历史

**站点创建弹窗**：
- Step 1：基本信息（名称、域名）
- Step 2：市场配置（目标市场、语言、货币）
- Step 3：业务配置（品类、物流渠道）
- Step 4：确认并创建

**结算审核页**：
- 结算单信息：商户、周期、金额
- 各站点明细表格：站点名、订单数、总收入、退款、佣金、净收入
- 操作按钮：确认 / 驳回（需填写原因）

### 9.2 商户后台

**登录页**：
- 简洁表单：邮箱/用户名 + 密码 + 记住我
- Logo + 平台名称

**仪表盘页**：
- 顶部切换：全部站点 / 具体站点下拉选择
- 数据卡片：今日销售额、今日订单数、待处理订单、可结算金额
- 图表区：近 30 天销售趋势（折线图）、各站点占比（饼图）
- 快捷入口：待发货订单、最新订单、商品同步状态

**商品管理页**：
- 列表：商品图片缩略图、名称、品类、基础价格、状态、已同步站点数
- 操作：编辑、同步到站点、删除
- 批量操作：批量同步、批量状态变更
- 商品编辑表单：基本信息 + SKU/变体 + 多语言翻译 + 图片上传

**商品同步弹窗**：
- 左侧：待同步商品列表（支持多选）
- 右侧：目标站点复选框列表（显示站点名+域名+状态）
- 同步选项：全量/增量、价格策略
- 确认按钮 + 同步进度条

**订单列表页**：
- 筛选栏：站点选择、订单状态、日期范围、订单号搜索
- 表格列：订单号、站点名、客户邮箱、金额、支付状态、发货状态、下单时间
- 详情按钮（弹窗或跳转）

**结算中心页**：
- 顶部：可结算余额（大字）、本月预估佣金、本月总收入
- 结算记录表格：周期、总收入、佣金、结算金额、状态
- 点击展开：各站点明细

---

## 10. 验收标准

### 功能验收

#### 商户入驻
- [ ] 商户可通过表单提交注册申请，状态为 `pending`
- [ ] 平台管理员可审核商户（通过/拒绝/补充信息）
- [ ] 审核通过后自动创建商户库（`jerseyholic_merchant_{id}`）
- [ ] 商户信息（基本信息、银行信息）可正常编辑保存
- [ ] 商户状态流转正确（pending → active/rejected/suspended/banned）
- [ ] 商户暂停/封禁时名下站点状态自动级联变更

#### 站点管理
- [ ] 可为商户创建站点，自动创建独立数据库和初始化表结构
- [ ] 站点数量受商户等级限制，超出时拒绝创建并提示
- [ ] 站点域名全局唯一校验有效
- [ ] 站点各项配置（市场/语言/货币/品类/物流/主题）可独立保存和读取
- [ ] 站点状态变更（active/maintenance/inactive）生效正确

#### 商户后台
- [ ] 商户用户可通过独立认证体系登录
- [ ] 仪表盘正确聚合所有站点的销售数据
- [ ] 站点切换功能正常，operator 角色只能看到授权站点
- [ ] 订单列表正确聚合所有站点订单，支持按站点筛选
- [ ] 商户对订单只有查看权限，无法修改状态

#### 商品同步
- [ ] 可在主商品库创建/编辑/删除商品
- [ ] 手动同步商品到指定站点，站点 DB 中正确写入商品数据
- [ ] 批量同步功能正常
- [ ] 同步规则（目标站点/排除站点/同步字段/价格策略）正确执行
- [ ] 同步日志正确记录（总数/成功数/失败数/状态）
- [ ] 同步失败自动重试 3 次

#### 佣金与结算
- [ ] 结算单按商户维度聚合所有站点数据
- [ ] 佣金按商户当前比例正确计算
- [ ] 退款订单的佣金正确扣回
- [ ] 结算单审核流程（pending → confirmed → paid）正常
- [ ] 结算明细包含各站点的收入分项

### 安全验收
- [ ] 商户 A 无法访问商户 B 的任何数据
- [ ] 商户用户无法访问 `allowed_store_ids` 之外的站点数据
- [ ] 数据库凭证和银行信息加密存储
- [ ] 商户后台无法查看/管理支付账号凭证
- [ ] 平台管理员、商户、买家三套认证体系完全隔离
- [ ] 商户 API 密钥对正确生成（RSA-4096）
- [ ] 私钥一次性安全下载机制有效（链接 24 小时过期，仅可下载 1 次）
- [ ] 密钥轮换过渡期正确（新旧密钥同时有效 24 小时）
- [ ] 紧急吊销立即生效，吊销后所有请求被拒绝
- [ ] 系统不保留私钥明文，仅存储公钥

### 边界场景
- [ ] 商户达到站点数量上限时，创建站点返回明确错误提示
- [ ] 站点数据库创建失败时，事务回滚，不留脏数据
- [ ] 商品同步目标站点数据库不可用时，正确记录失败日志
- [ ] 商户封禁后，已付款订单仍可正常完成履约
- [ ] 结算周期内无订单的商户，不生成结算单

---

## 11. 优先级与排期建议

### Phase 1：基础多租户架构（2-3 周）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| stancl/tenancy 安装配置 | P0 | 1d |
| Central DB 迁移（merchants/stores/admins 等） | P0 | 2d |
| ResolveTenant 中间件（域名→站点→切换DB） | P0 | 2d |
| StoreProvisioningService（站点创建+DB初始化） | P0 | 2d |
| Tenant DB 迁移文件（products/orders/customers） | P0 | 2d |
| Nginx 通配符域名 + SSL | P0 | 1d |
| 基础集成测试 | P0 | 2d |

**里程碑**：通过域名自动识别站点并切换数据库。

### Phase 2：商户体系（2-3 周）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| 商户 CRUD API（F-MCH-010~013） | P0 | 2d |
| 商户审核流程（F-MCH-011） | P0 | 1d |
| 商户用户认证（F-MCH-030） | P0 | 2d |
| 站点创建/管理 API（F-MCH-020~022） | P0 | 2d |
| 站点配置 API（F-MCH-023~028） | P1 | 2d |
| 权限隔离中间件 | P0 | 1d |
| 结算记录模块（F-MCH-040~042） | P0 | 3d |
| 商户后台前端（Vue 3） | P1 | 3d |

**里程碑**：商户可登录后台查看站点和订单。

### Phase 3：商品同步（2-3 周）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| Merchant DB 迁移（master_products/sync_rules） | P0 | 1d |
| 主商品 CRUD API（F-MCH-034） | P0 | 2d |
| ProductSyncService 核心（F-MCH-035） | P0 | 3d |
| 异步队列同步任务 | P0 | 2d |
| 选择性同步规则管理 | P1 | 2d |
| 同步日志与监控 | P1 | 1d |
| 集成测试 | P0 | 2d |

**里程碑**：商品可从主库同步到各站点。

### Phase 4：市场配置与风控（2 周）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| 多语言翻译同步 | P1 | 2d |
| 多货币价格策略 | P1 | 2d |
| 站点级物流配置 | P1 | 2d |
| 商户风险评分（F-MCH-050） | P1 | 2d |
| 异常监控与告警（F-MCH-052） | P1 | 1d |
| 商户等级管理（F-MCH-014, F-MCH-051） | P1 | 1d |
| Nuxt 3 前端多租户适配 | P1 | 3d |

**里程碑**：站点差异化配置完成，风控体系上线。

### 总体时间线

```
Week 1-3:   Phase 1 — 基础多租户架构
Week 4-6:   Phase 2 — 商户体系
Week 7-9:   Phase 3 — 商品同步
Week 10-11: Phase 4 — 市场配置与风控
Week 12:    整体集成测试 + 上线准备
```

**总预估工期**：10-12 周
