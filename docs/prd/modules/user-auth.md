# 用户与认证 PRD

> 优先级：**P0** | 版本：v1.0 | 更新日期：2026-04-16

## 1. 概述

### 功能简述
用户与认证模块管理三类用户（Admin 管理员、Merchant 商户、Buyer 买家）的注册、登录、权限控制和账户管理。管理后台使用 Laravel Sanctum Token 认证，API 层使用 HMAC-SHA256 签名认证，买家前台支持邮箱注册/登录和游客模式。

## 2. 用户角色定义

| 角色 | 认证方式 | 系统入口 | 说明 |
|------|---------|---------|------|
| Admin | Sanctum Token | Vue 3 管理后台 | 系统管理员，全部权限 |
| Merchant | HMAC-SHA256 / Sanctum | 管理后台（受限） | 商户运营，管理本站数据 |
| Buyer | Session/JWT | Nuxt 3 买家商城 | 终端消费者 |
| Guest | 无认证 | Nuxt 3 买家商城 | 游客，可浏览和下单 |

## 3. 用户故事

#### US-AUTH-001: 买家注册

**作为** 新访客，
**我希望** 通过邮箱注册账户，
**以便** 保存我的收货地址和订单历史。

**验收标准：**
- Given 填写邮箱+密码，When 提交注册，Then 账户创建成功
- Given 邮箱已注册，When 提交注册，Then 提示"邮箱已被使用"
- Given 注册成功，When 完成，Then 触发 CompleteRegistration Pixel 事件
- Given 密码不符合强度要求，When 提交，Then 提示密码规则

**优先级**: P0

---

#### US-AUTH-002: 买家登录

**作为** 已注册买家，
**我希望** 通过邮箱和密码登录，
**以便** 访问我的账户和历史订单。

**验收标准：**
- Given 正确的邮箱+密码，When 登录，Then 登录成功、建立会话
- Given 错误密码连续 5 次，When 再次尝试，Then 暂时锁定账号 15 分钟
- Given 结账时未登录，When 系统提示，Then 支持在结账页内登录或以游客继续

**优先级**: P0

---

#### US-AUTH-003: 密码重置

**作为** 买家，
**我希望** 通过邮箱重置密码，
**以便** 忘记密码时恢复账号访问。

**验收标准：**
- Given 输入已注册邮箱，When 申请重置，Then 发送重置链接邮件（有效期 24 小时）
- Given 点击重置链接，When 设置新密码，Then 密码更新成功
- Given 重置链接过期，When 点击，Then 提示"链接已过期，请重新申请"

**优先级**: P0

---

#### US-AUTH-004: 买家地址簿

**作为** 买家，
**我希望** 管理多个收货地址，
**以便** 下单时快速选择。

**验收标准：**
- Given 添加地址（姓名/电话/国家/州/城市/邮编/街道），When 保存，Then 地址入库
- Given 多个地址，When 设置默认地址，Then 下单时自动填充
- Given 地址管理，When 删除地址，Then 地址软删除

**优先级**: P0

---

#### US-AUTH-005: RBAC 权限管理

**作为** 超级管理员，
**我希望** 为不同管理员角色分配不同权限，
**以便** 控制管理后台的访问范围。

**验收标准：**
- Given 创建角色"订单管理员"，When 分配"订单查看/编辑"权限，Then 该角色只能访问订单模块
- Given 商户角色，When 登录后台，Then 只能看到本商户数据
- Given Admin 角色，When 登录后台，Then 可访问全部功能

**优先级**: P0 | **复杂度**: L

---

#### US-AUTH-006: API 认证（HMAC-SHA256）

**作为** 前台系统（独立站），
**我希望** 通过 HMAC-SHA256 签名认证调用支付 API，
**以便** 确保 API 调用的安全性。

**验收标准：**
- Given 携带正确签名的请求，When 调用 API，Then 认证通过、正常处理
- Given 签名错误或过期，When 调用 API，Then 返回 401 认证失败
- Given Webhook 回调（PayPal/Stripe），When 调用，Then 跳过 HMAC 认证，使用各自验签机制

**优先级**: P0 | **复杂度**: M

## 4. 数据需求

**jh_admins** — 管理员表
- id, name, email, password, role_id, status, last_login

**jh_roles** — 角色表
- id, name, slug, permissions(JSON)

**jh_customers** — 买家表
- id, email, password, first_name, last_name, phone
- customer_group_id, status, ip, last_login

**jh_customer_groups** — 客户组
- id, name, discount_percent, sort_order

**jh_addresses** — 地址簿
- id, customer_id, first_name, last_name, phone
- country, country_name, state, state_name, city, zip
- address1, address2, is_default

## 5. API 需求

### 买家端
| 接口 | 说明 |
|------|------|
| POST /api/auth/register | 注册 |
| POST /api/auth/login | 登录 |
| POST /api/auth/forgot-password | 发送重置邮件 |
| POST /api/auth/reset-password | 重置密码 |
| GET /api/account/profile | 获取个人信息 |
| PUT /api/account/profile | 更新个人信息 |
| GET/POST/PUT/DELETE /api/account/addresses | 地址簿 CRUD |
| GET /api/account/orders | 订单历史 |

### 管理端
| 接口 | 说明 |
|------|------|
| POST /api/admin/auth/login | 管理员登录 |
| GET/POST/PUT/DELETE /api/admin/roles | 角色 CRUD |
| GET/POST/PUT/DELETE /api/admin/admins | 管理员 CRUD |
| GET /api/admin/customers | 客户列表 |

## 6. 验收标准

### 功能验收
- [ ] 买家注册/登录/登出流程正常
- [ ] 密码重置邮件发送和链接处理正常
- [ ] 地址簿 CRUD 正常
- [ ] 管理员 RBAC 权限控制正确
- [ ] API HMAC-SHA256 认证正确
- [ ] Webhook 回调正确跳过 HMAC 认证

### 安全验收
- [ ] 密码 bcrypt 加密存储
- [ ] 连续登录失败锁定机制
- [ ] 重置链接一次性有效
- [ ] Sanctum Token 支持过期和撤销

### 多语言验收
- [ ] 注册/登录页面支持 16 种语言
- [ ] 错误提示信息多语言
