# Phase 4：营销增强与完善（第 8-9 周）

> 目标：完成营销功能、多语言完善、通知服务、风控系统、系统管理  
> 里程碑：M4 — 全功能就绪，营销和管理功能完善，准备进入迁移阶段

---

## 📊 Phase 4 进度看板

### 待开始 (To Do)
- [ ] TASK-P4-001: 优惠券/积分/促销系统 API (@backend-dev) [P1]
- [ ] TASK-P4-002: 商户管理系统 API (@backend-dev) [P1]
- [ ] TASK-P4-003: Facebook Pixel 后台管理页面 (@frontend-dev) [P1]
- [ ] TASK-P4-004: 多语言商品描述完善 + RTL (@frontend-dev) [P1]
- [ ] TASK-P4-005: 邮件通知服务 (@integration-specialist) [P1]
- [ ] TASK-P4-006: 钉钉告警通知 (@integration-specialist) [P2]
- [ ] TASK-P4-007: 风控系统（黑名单 + 风险检测） (@backend-dev) [P1]
- [ ] TASK-P4-008: 系统设置（日志/配置管理） (@backend-dev) [P2]
- [ ] TASK-P4-009: Dashboard 仪表盘页面 (@frontend-dev) [P2]
- [ ] TASK-P4-010: 买家账户中心页面 (@frontend-dev) [P1]

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

### TASK-P4-001: 优惠券/积分/促销系统 API

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P2-003 |
| **输出物** | `app/Services/CouponService.php`、`app/Http/Controllers/Admin/CouponController.php`、`app/Models/Coupon.php` |

**验收标准：**
1. 优惠券 CRUD 正常（固定金额/百分比折扣、有效期、使用次数限制）
2. 结账时输入优惠券码享受折扣
3. 优惠券使用验证（过期/已用完/不满足条件 等错误提示）
4. 客户组折扣：不同客户组享受不同折扣比例
5. 商品特殊价格（促销价）按时间段自动切换
6. 订单金额计算正确（小计 + 税费 + 运费 - 优惠 = 总计）

**技术要点：**
- 优惠券类型：固定金额（fixed）、百分比（percent）、免运费（free_shipping）
- 使用条件：最低消费金额、指定商品/分类、指定客户组
- 优惠计算使用 Pipeline 模式，可扩展

---

### TASK-P4-002: 商户管理系统 API

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P1-003 |
| **输出物** | `app/Http/Controllers/Admin/MerchantController.php`、`app/Services/MerchantService.php` |

**验收标准：**
1. 站点（域名/Website）CRUD 正常
2. 域名 → 收款分组映射配置正常（PayPal 分组 / 信用卡分组）
3. 商户 API 密钥管理（merchantId、HMAC 密钥生成/重置）
4. 按商户统计销售数据
5. 商户列表和搜索

**技术要点：**
- 参考 F-MCH-001 ~ F-MCH-004 功能需求
- Website 表：domain, paypal_group_id, cc_group_id, merchant_id, status
- HMAC 密钥使用安全随机生成

---

### TASK-P4-003: Facebook Pixel 后台管理页面

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-010 |
| **输出物** | `jerseyholic-admin/src/views/pixel/` 目录下页面组件 |

**验收标准：**
1. Pixel 配置 CRUD 页面（按店铺/语言配置 Pixel ID）
2. 多像素 ID 输入支持（逗号分隔）
3. 事件开关管理（可单独启用/禁用每种事件类型的 Toggle）
4. 配置预览（模拟前台加载 Pixel 脚本的效果）
5. 配置状态管理（启用/禁用）

**技术要点：**
- 事件开关使用 Switch 组件，12+ 种事件逐个控制
- Pixel ID 输入使用 Tag 输入组件

---

### TASK-P4-004: 多语言商品描述完善 + RTL

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-008 |
| **输出物** | 更新 `jerseyholic-storefront/` 多语言相关组件和样式 |

**验收标准：**
1. 16 语言切换正常，URL 前缀路由正确
2. P0 语言（en/de/fr/es）翻译完整可用
3. P1 语言（it/ja/ko/pt-BR/pt-PT/nl/ar）基本可用
4. 阿拉伯语（ar）RTL 布局正确：`dir="rtl"` 设置
5. RTL 下导航、网格、表单布局正确镜像
6. RTL 下 CSS 无错位
7. 缺失翻译自动回退到英语
8. hreflang SEO 标签自动生成（含 x-default）

**技术要点：**
- TailwindCSS RTL 插件配置（`rtl:` 修饰符）
- 动态 `dir` 属性切换（根据当前语言）
- hreflang 标签在 `useHead` 中动态生成

---

### TASK-P4-005: 邮件通知服务

| 属性 | 值 |
|------|-----|
| **负责人** | @integration-specialist |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-003 |
| **输出物** | `app/Mail/` 目录下邮件模板、`app/Services/NotificationService.php` |

**验收标准：**
1. 订单确认邮件发送正常（含订单详情摘要）
2. 发货通知邮件发送正常（含物流单号和追踪链接）
3. 退款完成邮件发送正常
4. 密码重置邮件发送正常
5. 邮件模板支持多语言（根据买家语言偏好发送对应语言）
6. 邮件发送失败自动重试（队列化发送）

**技术要点：**
- 使用 Laravel Mail + Queue 异步发送
- 邮件模板使用 Blade 模板
- 邮件 Provider 可配置（SMTP/SES/Mailgun 等）

---

### TASK-P4-006: 钉钉告警通知

| 属性 | 值 |
|------|-----|
| **负责人** | @integration-specialist |
| **优先级** | P2 |
| **预估工时** | 1d |
| **前置依赖** | 无 |
| **输出物** | `app/Services/DingTalkService.php` |

**验收标准：**
1. 支付账号异常自动禁用时推送钉钉消息
2. 支付账号耗尽时推送告警
3. 异常订单（高风险/大额）推送告警
4. 系统错误（队列堆积/API 超时）推送告警
5. 消息格式清晰（包含关键信息：账号/域名/错误详情）

**技术要点：**
- 钉钉 Webhook 机器人 API
- 消息格式：Markdown 类型
- 告警频率限制（同类告警 5 分钟内不重复发送）

---

### TASK-P4-007: 风控系统（黑名单 + 风险检测）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-003 |
| **输出物** | `app/Services/RiskService.php`、`app/Models/Blacklist.php` |

**验收标准：**
1. 黑名单 CRUD 管理正常（邮箱/IP/地址 黑名单）
2. 黑名单用户下单时自动路由到 groupId=2 黑名单专用分组
3. 黑名单订单标记 is_blacklist=1
4. 风险等级评估（低/中/高 三级）
5. 高风险订单自动标记并告警
6. 黑名单同步更新（定时任务）

**技术要点：**
- 参考 BR-RISK-001、BR-RISK-002 业务规则
- 黑名单检查使用 Redis 缓存（高频查询优化）
- 风险评分规则可配置

---

### TASK-P4-008: 系统设置（日志/配置管理）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P2 |
| **预估工时** | 1d |
| **前置依赖** | TASK-P1-004 |
| **输出物** | `app/Http/Controllers/Admin/SettingController.php`、`app/Services/SettingService.php` |

**验收标准：**
1. 系统全局配置管理（键值对形式，支持分组）
2. 操作日志查询（管理员操作审计日志）
3. 货币管理（支持的货币列表、汇率查看/手动修改）
4. 国家/地区管理
5. 配置变更记录日志

**技术要点：**
- 系统配置存 `jh_settings` 表，缓存到 Redis
- 操作日志使用 Laravel Event + Listener 自动记录
- 货币汇率由 SyncCurrencyJob 自动更新，也支持手动修改

---

### TASK-P4-009: Dashboard 仪表盘页面

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P2 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P3-009 |
| **输出物** | `jerseyholic-admin/src/views/dashboard/` 目录下页面组件 |

**验收标准：**
1. 核心指标卡片：今日/本周/本月销售额、订单数、支付成功率
2. 销售趋势图表（折线图/柱状图）
3. 支付账号健康度概览
4. 近期订单列表（快捷入口）
5. 告警信息展示

**技术要点：**
- 使用 ECharts 或 Chart.js 图表库
- 数据聚合 API（后端提供 Dashboard 专用统计接口）
- 自动刷新（定时拉取或 WebSocket）

---

### TASK-P4-010: 买家账户中心页面

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-005 |
| **输出物** | `jerseyholic-storefront/pages/account/` 目录下页面组件 |

**验收标准：**
1. 个人信息页面（查看/编辑姓名、邮箱、密码）
2. 地址簿管理页面（增删改查、设置默认地址）
3. 订单历史页面（订单列表、订单详情、物流追踪链接）
4. 密码修改页面
5. 多语言支持
6. 响应式布局

**技术要点：**
- 账户中心使用侧边栏导航布局
- 订单历史支持分页和状态筛选
- 地址表单复用结账页的地址组件

---

## 并行策略

| 时间段 | @backend-dev | @frontend-dev | @integration-specialist |
|--------|-------------|--------------|------------------------|
| Week 8 | P4-001 优惠券 + P4-002 商户管理 + P4-007 风控 | P4-003 Pixel 管理 + P4-004 多语言+RTL + P4-010 买家账户 | P4-005 邮件通知 + P4-006 钉钉告警 |
| Week 9 | P4-008 系统设置 + 联调修复 + 全面回归测试 | P4-009 Dashboard + 联调修复 + UI 优化 | 联调支持 + 通知服务优化 |

> ⚠️ **M4 检查点**：Week 9 结束时执行全功能验收，确认所有 Phase 1-4 功能就绪后才可进入 Phase 5。
