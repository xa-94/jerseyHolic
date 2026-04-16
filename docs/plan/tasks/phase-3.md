# Phase 3：支付与物流（第 6-7 周）

> 目标：完成支付渠道集成、物流发货系统、异步队列整合  
> 里程碑：M3 — 完整购买流程跑通（浏览→下单→支付→发货→轨迹），支付使用安全商品名  
> ⚠️ **安全前置依赖**：本阶段所有支付/物流任务均依赖 TASK-P1-005（商品映射服务）已完成验收

---

## 📊 Phase 3 进度看板

### 待开始 (To Do)
- [ ] TASK-P3-001: PayPal 支付集成 (@backend-dev) [P0]
- [ ] TASK-P3-002: Stripe 支付集成 (@backend-dev) [P0]
- [ ] TASK-P3-003: 支付账号池与路由选择（ElectionService） (@backend-dev) [P0]
- [ ] TASK-P3-004: 信用卡/Payssion/Antom 支付 (@backend-dev) [P1]
- [ ] TASK-P3-005: 物流 Provider 框架与实现 (@integration-specialist) [P0]
- [ ] TASK-P3-006: 运费规则引擎 (@integration-specialist) [P1]
- [ ] TASK-P3-007: 物流轨迹同步定时任务 (@integration-specialist) [P1]
- [ ] TASK-P3-008: PayPal 卖家保护上传 (@integration-specialist) [P1]
- [ ] TASK-P3-009: 支付管理后台页面 (@frontend-dev) [P1]
- [ ] TASK-P3-010: 物流管理后台页面 (@frontend-dev) [P1]
- [ ] TASK-P3-011: 异步队列任务整合（20 个 Job） (@backend-dev) [P1]

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

### TASK-P3-001: PayPal 支付集成

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | **TASK-P1-005（商品映射服务）**, TASK-P2-003 |
| **输出物** | `app/Services/Payment/PaypalService.php`、`app/Http/Controllers/Webhook/PaypalWebhookController.php` |

**验收标准：**
1. PayPal 标准授权支付流程完整（创建→授权→确认→成功）
2. PayPal 信用卡直付流程完整（Advanced Card + 3DS 验证）
3. **⚠️ 创建订单时商品名称使用 ProductMappingService 获取的安全名称**
4. **⚠️ 价格字段使用真实价格，不被映射修改**
5. Webhook 回调处理正确（CHECKOUT.ORDER.COMPLETED/APPROVED、DISPUTE.*、PAYMENT.CAPTURE.*）
6. Webhook 验签正确（OpenSSL RSA-SHA256）
7. 重复回调防重机制有效（Redis 缓存 1 小时）
8. 退款 API 正常（全额/部分退款）
9. 非 USD 货币正确转汇

**技术要点：**
- 参考 BR-PAY-006、BR-PAY-007 业务规则
- PayPal REST API v2（Orders API + Payments API）
- 创建订单时调用 `ProductMappingService::getSafeName($product, 'PAYMENT')` 获取安全商品名
- 3DS 验证后回跳处理
- Webhook 路由跳过 Sanctum 认证，使用独立验签中间件

---

### TASK-P3-002: Stripe 支付集成

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 2d |
| **前置依赖** | **TASK-P1-005（商品映射服务）**, TASK-P2-003 |
| **输出物** | `app/Services/Payment/StripeService.php`、`app/Http/Controllers/Webhook/StripeWebhookController.php` |

**验收标准：**
1. Stripe Checkout Session 创建正常
2. **⚠️ line_items 中商品名称使用安全映射名称**
3. **⚠️ 价格字段使用真实价格**
4. 支付成功回调处理正确
5. Webhook 验签正确（MD5 签名）
6. 退款 API 正常
7. 重复回调防重机制有效

**技术要点：**
- Stripe Checkout Session 模式
- 创建 Session 时调用 `ProductMappingService::getSafeName($product, 'PAYMENT')`
- Webhook 事件处理：checkout.session.completed、payment_intent.succeeded

---

### TASK-P3-003: 支付账号池与路由选择（ElectionService）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P0 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P3-001, TASK-P3-002 |
| **输出物** | `app/Services/Payment/ElectionService.php`、`app/Services/Payment/PayAccountService.php` |

**验收标准：**
1. 8 层筛选逻辑正确（参考 BR-PAY-001）：风控→域名→分组→币种→状态→金额范围→限额→优先级
2. 账号异常 3 分钟内自动禁用
3. 禁用后同分组备用账号自动启用
4. 账号耗尽时推送钉钉告警并关闭前台支付
5. 黑名单用户路由到 groupId=2 黑名单专用分组
6. 多 PayPal/Stripe 账号管理（分组、限额、优先级）
7. 日限额、总限额检查正确
8. 新账号限制逻辑正确

**技术要点：**
- 参考 BR-PAY-001 ~ BR-PAY-005 业务规则
- 账号选择结果缓存（短 TTL，避免并发选中同一账号）
- 限额计数使用 Redis 原子操作
- 账号健康度评分算法

---

### TASK-P3-004: 信用卡/Payssion/Antom 支付

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P3-003 |
| **输出物** | `app/Services/Payment/AntomService.php`、`app/Services/Payment/PayssionService.php` |

**验收标准：**
1. Antom Card 支付流程完整
2. Antom 本地支付（iDEAL/Bancontact/Blik/Kakao）流程完整
3. Payssion 本地支付流程完整
4. **所有渠道商品名称使用安全映射名称**
5. 各渠道 Webhook 验签正确
6. Antom 验签：通过 paymentRequestId 反查订单核对金额

**技术要点：**
- 使用 Payment Provider 抽象接口，统一 create/callback/refund 方法
- 参考 BR-PAY-005 支付方式路由规则
- 支持的支付类型枚举参考 BR-ORD-006

---

### TASK-P3-005: 物流 Provider 框架与实现

| 属性 | 值 |
|------|-----|
| **负责人** | @integration-specialist |
| **优先级** | P0 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P1-001, **TASK-P1-005（商品映射服务）** |
| **输出物** | `app/Services/Logistics/LogisticsProviderInterface.php`、具体 Provider 实现类 |

**验收标准：**
1. 物流 Provider 抽象接口定义完成（createShipment/getTracking/cancelShipment）
2. 至少 1 个物流供应商 Provider 实现可用
3. **⚠️ 面单生成时商品名称使用 ProductMappingService 获取的安全名称**
4. 物流公司映射正确（内部渠道名 → PayPal/AfterShip 标准名）
5. 批量发货功能正常
6. 物流揽收状态自动更新

**技术要点：**
- Provider 模式：`LogisticsProviderInterface` + 具体供应商实现
- 面单生成调用 `ProductMappingService::getSafeName($product, 'LOGISTICS')`
- 参考 BR-SHIP-002 物流公司映射规则
- 参考 BR-SHIP-003 面单商品名称安全规则

---

### TASK-P3-006: 运费规则引擎

| 属性 | 值 |
|------|-----|
| **负责人** | @integration-specialist |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P3-005 |
| **输出物** | `app/Services/Logistics/ShippingCalculator.php` |

**验收标准：**
1. 固定运费计算正确
2. 按重量运费计算正确（总重量 × 费率 + 基础费用）
3. 按件数运费计算正确
4. 免运费条件判断正确（满额免运费）
5. 地理区域限制生效（不可达地区提示）
6. 多运费规则优先级正确

**技术要点：**
- 参考 BR-SHIP-001 运费计算方式
- 运费规则引擎：规则匹配 → 计算 → 排序 → 返回可用方式列表
- 地理区域（geo_zones）关联运费规则

---

### TASK-P3-007: 物流轨迹同步定时任务

| 属性 | 值 |
|------|-----|
| **负责人** | @integration-specialist |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P3-005 |
| **输出物** | `app/Jobs/SyncTrackingJob.php`、`app/Console/Commands/SyncTrackingCommand.php` |

**验收标准：**
1. 定时批量拉取物流轨迹信息
2. 轨迹状态标准化（不同物流商状态映射为统一状态）
3. 轨迹更新后自动更新订单发货状态
4. 物流揽收状态自动检测和更新
5. 异常轨迹（长时间无更新）告警

**技术要点：**
- 对接 AfterShip 或物流商直接 API
- 批量处理优化（分页查询待同步订单）
- 使用 Laravel Scheduler 定时执行（每 4 小时同步一次）

---

### TASK-P3-008: PayPal 卖家保护上传

| 属性 | 值 |
|------|-----|
| **负责人** | @integration-specialist |
| **优先级** | P1 |
| **预估工时** | 1d |
| **前置依赖** | TASK-P3-001, TASK-P3-005 |
| **输出物** | `app/Jobs/UploadPaypalTrackingJob.php` |

**验收标准：**
1. 物流信息上传 PayPal Tracking API 正常
2. **⚠️ 上传时商品信息使用安全映射名称**
3. 物流公司名正确映射为 PayPal 识别的标准名称
4. 上传失败自动重试（最多 3 次）
5. 上传成功后标记订单已获得卖家保护

**技术要点：**
- PayPal Tracking API：POST /v1/shipping/trackers-batch
- 调用 `ProductMappingService::getSafeName($product, 'LOGISTICS')`
- 物流公司映射参考 BR-SHIP-002

---

### TASK-P3-009: 支付管理后台页面

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P2-007 |
| **输出物** | `jerseyholic-admin/src/views/payment/` 目录下页面组件 |

**验收标准：**
1. 支付账号管理页面：账号列表、添加/编辑/禁用、分组管理
2. 账号限额配置：日限额、总限额、金额范围
3. 交易记录查询页面：多条件搜索、状态筛选
4. 账号健康度展示（收款额/限额进度条）
5. 支付渠道配置页面

**技术要点：**
- 账号列表支持按分组/状态/类型筛选
- 限额进度可视化（进度条 + 颜色预警）
- 交易记录支持导出

---

### TASK-P3-010: 物流管理后台页面

| 属性 | 值 |
|------|-----|
| **负责人** | @frontend-dev |
| **优先级** | P1 |
| **预估工时** | 2d |
| **前置依赖** | TASK-P3-009 |
| **输出物** | `jerseyholic-admin/src/views/logistics/` 目录下页面组件 |

**验收标准：**
1. 发货操作页面：选择物流商、填写单号、批量发货
2. 物流轨迹查看页面：时间线展示轨迹节点
3. 物流供应商管理页面
4. 运费规则配置页面
5. 物流公司映射配置页面

**技术要点：**
- 批量发货使用 Excel 导入模式
- 轨迹时间线组件

---

### TASK-P3-011: 异步队列任务整合（20 个 Job）

| 属性 | 值 |
|------|-----|
| **负责人** | @backend-dev |
| **优先级** | P1 |
| **预估工时** | 3d |
| **前置依赖** | TASK-P3-001, TASK-P3-002, TASK-P3-003, TASK-P3-004 |
| **输出物** | `app/Jobs/` 目录下 20 个 Job 类、`config/horizon.php` 队列配置 |

**验收标准：**
1. 6 个队列优先级配置正确：payment-critical > payment > logistics > notification > sync > default
2. 以下 Job 正常执行：
   - 支付类：ProcessPaypalWebhook、ProcessStripeWebhook、ProcessAntomWebhook、AutoDisableAccount、RefundJob
   - 物流类：CreateShipmentJob、SyncTrackingJob、UploadPaypalTrackingJob、ShipCollectionJob
   - 订单类：OrderTimeoutCancelJob、OrderSyncJob、DisputeCheckJob
   - 通知类：SendOrderEmailJob、DingTalkAlertJob
   - 同步类：SyncCurrencyJob、SyncProductJob、InitAccountJob
   - 统计类：SalesStatisticsJob
3. 失败重试机制配置正确（最大重试 3 次、退避策略）
4. Laravel Horizon 监控配置就绪

**技术要点：**
- 参考 PRD 中 F-TASK-001 ~ F-TASK-012 功能列表
- 队列优先级影响任务执行顺序，支付类 Job 必须最高优先级
- 使用 Laravel Horizon 监控队列健康状态
- 死信队列处理：失败 3 次后进入 failed_jobs 表，人工处理

---

## 并行策略

| 时间段 | @backend-dev | @frontend-dev | @integration-specialist |
|--------|-------------|--------------|------------------------|
| Week 6 | P3-001 PayPal + P3-002 Stripe | P3-009 支付管理页面 | P3-005 物流 Provider + P3-006 运费引擎 |
| Week 7 | P3-003 账号池 + P3-004 其他支付 + P3-011 队列整合 | P3-010 物流管理页面 | P3-007 轨迹同步 + P3-008 卖家保护 |

> ⚠️ **安全检查点**：Week 6 开始前必须确认 TASK-P1-005 已通过验收，所有支付/物流代码中已正确调用 ProductMappingService。
