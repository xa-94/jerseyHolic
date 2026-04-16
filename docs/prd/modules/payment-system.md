# 支付系统 PRD

> 优先级：**P0** | 版本：v2.0 | 更新日期：2026-04-16
> 关联业务规则：BR-PAY-001 ~ BR-PAY-007, BR-PAY-POOL-*, BR-PAY-SETTLE-*, BR-PAY-RISK-*, BR-PAY-SEC-*, BR-SEC-*, BR-MAP-003

## 1. 概述

### 功能简述
统一支付系统是 JerseyHolic 的核心交易模块，整合 PayPal、Stripe、Antom（蚂蚁国际）、Payssion 等支付渠道，通过支付账号池和智能选号引擎（ElectionService）实现多账号轮询、自动路由、异常熔断，确保收款连续性。v2.0 新增资金池分组管理、账号生命周期、商户结算引擎与增强风控体系，支持多商户多站点的资金归集与佣金结算。

### 业务价值
- 支持全球多种支付方式，覆盖主流市场
- 多账号池分散风险，避免单一账号冻结导致业务中断
- 智能选号按域名/金额/健康度路由，最大化收款成功率
- 异常账号 3 分钟内自动禁用，备用账号自动启用
- **（v2.0）** 账号分组精细化管理（VIP独占/Standard共享/黑名单隔离），降低交叉风险
- **（v2.0）** 商户维度结算聚合，支持多种佣金模型和结算周期
- **（v2.0）** 商户风险评分与动态限额，提前预防支付冻结

### 影响范围
- 买家前台结账流程
- 后台支付账号管理
- 订单状态更新
- 物流模块（PayPal 卖家保护）
- **（v2.0）** 商户管理模块（结算、佣金、风险评分）
- **（v2.0）** 财务对账与报表
- **⚠️ 安全约束**：支付接口中的商品名称必须经过 ProductMappingService 映射

## 2. 用户角色

| 角色 | 权限 |
|------|------|
| Admin | 管理支付账号、配置分组、查看收款报表、手动启用/禁用账号、管理结算规则、审核结算单 |
| Merchant | **(v2.0)** 查看自己的结算单与佣金明细、查看站点支付成功率（不可直接管理支付账号） |
| Buyer | 选择支付方式、完成支付、查看支付结果 |
| 系统 | 自动选号、Webhook 处理、异常熔断、退款处理、结算单自动生成、风险评分计算 |

## 3. 功能清单

| 功能ID | 功能名称 | 优先级 | 复杂度 | 描述 |
|--------|---------|--------|--------|------|
| F-PAY-001 | PayPal 标准支付 | P0 | XL | 授权支付+确认扣款，Webhook 验签 |
| F-PAY-002 | PayPal 信用卡直付 | P0 | XL | Advanced Card Payment，含 3DS |
| F-PAY-003 | Stripe 支付 | P0 | L | Checkout Session 模式 |
| F-PAY-004 | Antom 支付 | P1 | L | Card/iDEAL/Bancontact/Blik/Kakao |
| F-PAY-005 | Payssion 本地支付 | P1 | M | 新兴市场本地化支付 |
| F-PAY-006 | 支付账号池管理 | P0 | XL | 多账号 CRUD、分组、限额、优先级 |
| F-PAY-007 | 智能选号引擎 | P0 | XL | 8 层筛选逻辑自动选号 |
| F-PAY-008 | 异常自动禁用 | P0 | L | 3 分钟异常→自动禁用+启用备用 |
| F-PAY-009 | Webhook 统一处理 | P0 | L | 多渠道异步回调验签+订单更新 |
| F-PAY-010 | 退款 API | P0 | M | 全额/部分退款 |
| F-PAY-011 | 风控检查 | P1 | M | 黑名单路由 |
| F-PAY-012 | 货币转汇 | P0 | M | 非 USD 自动转汇 |
| F-PAY-013 | 钉钉告警 | P1 | S | 账号异常/耗尽告警 |
| F-PAY-014 | 支付通道自动开关 | P1 | M | 账号耗尽自动下架前台支付 |
| F-PAY-015 | 3D Secure 验证 | P1 | M | PayPal 3DS 回跳处理 |
| F-PAY-016 | 支付配置查询 | P0 | S | 前台获取支付配置 |
| | **▼ v2.0 新增：资金池管理增强** | | | |
| F-PAY-030 | 支付账号分组管理 | P0 | L | VIP独占/Standard共享/Lite共享/黑名单隔离四类分组 |
| F-PAY-031 | 账号生命周期管理 | P1 | M | 新建→成长→成熟→老化阶段自动流转 |
| F-PAY-032 | 账号健康度评分 | P0 | L | 综合错误率/冻结信号/限制信号的实时评分 |
| F-PAY-033 | 分组-商户-站点映射 | P0 | L | Domain→merchant_id→group_id 三层映射 |
| F-PAY-034 | 多通道自动降级 | P1 | L | PayPal→Stripe→Antom 逐级降级切换 |
| F-PAY-035 | 支付成功率监控仪表盘 | P2 | M | 按账号/分组/商户/通道多维度实时监控 |
| | **▼ v2.0 新增：结算系统** | | | |
| F-PAY-040 | 佣金规则引擎 | P1 | L | 支持比例/固定/阶梯佣金，按品类差异化 |
| F-PAY-041 | 结算单自动生成 | P1 | XL | 按商户维度聚合所有站点交易，自动生成结算单 |
| F-PAY-042 | 结算周期管理 | P1 | M | 月结/周结/T+14，按商户等级差异化 |
| F-PAY-043 | 退款/争议结算影响 | P1 | L | 结算前扣除未结退款，结算后生成调整单 |
| F-PAY-044 | 结算对账 | P1 | L | 系统流水 vs 支付网关对账，差异标记 |
| F-PAY-045 | 结算报表 | P2 | M | 按商户/站点/品类/时间维度的结算汇总 |
| | **▼ v2.0 新增：风控增强** | | | |
| F-PAY-050 | 商户风险评分 | P1 | L | 聚合所有站点的争议率/退款率/拒付金额 |
| F-PAY-051 | 动态限额调整 | P1 | M | 基于风险评分自动调整日/月限额 |
| F-PAY-052 | 买家行为风控 | P2 | L | 重复下单检测、大额预警、设备指纹 |
| F-PAY-053 | 平台级黑名单管理 | P1 | M | IP/邮箱/设备/支付账号维度的全局黑名单 |
| F-PAY-054 | 商户级黑名单管理 | P2 | S | 商户可维护自己站点范围的黑名单 |
| | **▼ v2.0 新增：API 签名验证** | | | |
| F-PAY-060 | 请求签名验证中间件 | P0 | L | 验证独立站发来的支付请求的 RSA-SHA256 数字签名 |
| F-PAY-061 | 签名 SDK（独立站端） | P0 | M | 提供给独立站使用的 PHP 签名 SDK，支持 Guzzle 中间件自动签名 |

## 4. 用户故事

#### US-PAY-001: 买家 PayPal 支付

**作为** 买家，
**我希望** 在结账时选择 PayPal 支付并完成付款，
**以便** 使用我熟悉的支付方式购买商品。

**验收标准：**
- Given 买家选择 PayPal 支付并点击付款，When 系统处理请求，Then 系统自动选择可用 PayPal 账号、创建订单、返回 PayPal 授权 URL
- Given PayPal 授权成功后用户返回，When 系统确认订单，Then 执行扣款并更新订单状态为已支付
- Given 用户在 PayPal 页面取消，When 返回商城，Then 订单状态更新为已取消
- Given 支付创建过程中，When 传递商品信息到 PayPal，Then 商品名称使用安全映射名称（**非真实品牌名**）

**优先级**: P0 | **复杂度**: XL

---

#### US-PAY-002: 支付账号智能选择

**作为** 系统，
**我希望** 每次支付请求时，自动根据域名、金额、账号健康度选择最优收款账号，
**以便** 分散收款风险并最大化成功率。

**验收标准：**
- Given 域名 A 配置了分组 G1，When 支付请求来自域名 A，Then 从分组 G1 中选择账号
- Given 分组 G1 中有 3 个账号，When 一个累计收款达到限额，Then 自动跳过选择下一个
- Given 账号日收款达到日限额，When 新支付请求，Then 跳过该账号
- Given 黑名单用户请求支付，When 选择账号，Then 路由到黑名单专用分组（groupId=2）
- Given 订单金额为 EUR 50，When 选择账号，Then 先转汇为 USD 再匹配金额范围
- Given 所有账号均不可用，When 选择账号，Then 推送钉钉告警 + 下架前台支付 + 返回错误

**业务规则：** BR-PAY-001, BR-PAY-003, BR-PAY-004

**优先级**: P0 | **复杂度**: XL

---

#### US-PAY-003: 异常账号自动熔断

**作为** 系统管理员，
**我希望** 异常支付账号能在 3 分钟内自动禁用，并启用备用账号，
**以便** 减少支付失败对收入的影响。

**验收标准：**
- Given 账号首次报错（无 errorMsg），When 记录异常时间，Then 仅记录 error_time，不禁用
- Given 账号持续报错超过 180 秒，When 新请求携带异常信息，Then 自动禁用（status=0, permission=3）
- Given 账号报错且携带明确 errorMsg，When 处理异常，Then 立即禁用
- Given 账号被禁用，When 同分组存在可收款备用账号（status=0, permission=1），Then 自动启用备用账号
- Given 任何账号禁用事件，When 触发，Then 推送钉钉告警

**业务规则：** BR-PAY-002

**优先级**: P0 | **复杂度**: L

---

#### US-PAY-004: Webhook 异步回调处理

**作为** 系统，
**我希望** 正确处理各支付渠道的异步回调通知，
**以便** 及时更新订单支付状态。

**验收标准：**
- Given 收到 PayPal CHECKOUT.ORDER.COMPLETED 事件，When 验签通过，Then 更新订单为已支付
- Given 收到 PayPal CUSTOMER.DISPUTE.CREATED 事件，When 验签通过，Then 创建争议记录、更新纠纷状态
- Given 收到 Stripe checkout.session.completed 事件，When 验签通过且 livemode=true，Then 更新订单为已支付
- Given 重复收到同一 transactionId 的回调，When 检查 Redis 缓存，Then 直接返回成功（防重）
- Given 验签失败，When 处理回调，Then 返回错误，不更新订单

**业务规则：** BR-PAY-006, BR-PAY-007, BR-ORD-008

**优先级**: P0 | **复杂度**: L

---

#### US-PAY-005: 退款处理

**作为** 管理员，
**我希望** 对已支付订单发起全额或部分退款，
**以便** 处理客户退货或争议。

**验收标准：**
- Given 订单已支付且有交易号，When 发起全额退款，Then 调用 PayPal 退款 API 并更新退款状态为"退款中"
- Given 退款 API 返回成功，When 更新状态，Then 订单退款状态变为"已退款"
- Given 发起部分退款 $20（订单 $50），When 退款成功，Then 退款状态为"部分退款"
- Given 订单无交易号，When 尝试退款，Then 返回错误"查无此订单"

**优先级**: P0 | **复杂度**: M

---

### v2.0 新增用户故事

#### US-PAY-006: 支付账号分组管理

**作为** 系统管理员，
**我希望** 将支付账号按业务类型分为 VIP独占、Standard共享、Lite共享、黑名单隔离四类分组，
**以便** 不同等级商户的收款账号互相隔离，降低交叉风险。

**验收标准：**
- Given VIP商户的站点发起支付，When 选号引擎处理，Then 仅从该商户独占分组中选择账号，不与其他商户共享
- Given Standard商户的站点发起支付，When 选号引擎处理，Then 从共享分组中选择账号，多个商户可共用同一分组
- Given 黑名单用户发起支付，When 风控检查命中，Then 路由到黑名单隔离分组（低额度/可牺牲账号）
- Given 管理员创建新分组，When 指定分组类型为 VIP，Then 必须关联一个商户 ID，且该分组不可被其他商户使用

**业务规则：**
1. 分组类型枚举：VIP_EXCLUSIVE（独占）、STANDARD_SHARED（标准共享）、LITE_SHARED（轻量共享）、BLACKLIST_ISOLATED（黑名单隔离）
2. 每个分组可包含多个支付账号，同一账号不可同时属于多个分组
3. VIP 分组与商户 1:1 绑定；Shared 分组可 1:N 关联多个商户
4. 分组可配置最大日收款额、最大月收款额、单笔限额范围

**优先级**: P0 | **复杂度**: L

---

#### US-PAY-007: 账号生命周期与健康度监控

**作为** 系统管理员，
**我希望** 系统自动跟踪每个支付账号的生命周期阶段和健康度评分，
**以便** 及时发现账号风险并主动调整策略，避免被冻结。

**验收标准：**
- Given 新创建的支付账号，When 系统初始化，Then 生命周期标记为“新建”，日限额自动设为保守值（如 $500/日）
- Given 账号累计收款超过成长阈值（如 $3000）且无异常，When 系统评估，Then 自动升级为“成长”阶段，适当提升限额
- Given 账号连续 30 天无异常且收款超过成熟阈值，When 系统评估，Then 升级为“成熟”阶段
- Given 账号健康度评分低于 60 分，When 系统检测，Then 自动降低该账号优先级，推送钉钉告警
- Given 账号健康度低于 30 分，When 系统检测，Then 自动禁用该账号，标记为“老化”阶段

**健康度评分维度（满分 100）：**
| 指标 | 权重 | 说明 |
|------|------|------|
| 支付成功率 | 30% | 7 天滑动窗口成功率 |
| 退款/争议率 | 25% | 30 天退款+争议占比 |
| 冻结/限制信号 | 25% | 是否收到过账号限制通知 |
| 账号年龄 | 10% | 账号开设时长 |
| 收款均匀度 | 10% | 每日收款是否平稳（避免突然飙升） |

**优先级**: P0 | **复杂度**: L

---

#### US-PAY-008: 多通道自动降级

**作为** 系统，
**我希望** 当主支付通道不可用时，自动降级到备用通道继续收款，
**以便** 不因单一通道故障导致业务中断。

**验收标准：**
- Given PayPal 通道所有账号均不可用，When 新支付请求到达，Then 自动降级到 Stripe 通道
- Given Stripe 通道也不可用，When 新支付请求到达，Then 继续降级到 Antom 通道
- Given 所有通道均不可用，When 新支付请求到达，Then 推送紧急钉钉告警 + 关闭前台支付 + 返回错误
- Given 降级发生，When 主通道恢复可用，Then 自动回切主通道，降级状态解除

**业务规则：**
1. 降级优先级：PayPal → Stripe → Antom → Payssion
2. 降级切换对买家透明，前台仅展示可用的支付方式
3. 降级事件必须记录日志并发送钉钉告警
4. 主通道恢复检测周期：每 5 分钟探测一次

**优先级**: P1 | **复杂度**: L

---

#### US-PAY-009: 商户结算单自动生成

**作为** 系统，
**我希望** 在结算周期到期时，自动聚合商户下所有站点的已完成交易，扮除佣金和退款后生成结算单，
**以便** 平台运营可以审核确认后线下打款。

**验收标准：**
- Given 商户 A 结算周期为月结，当月有 3 个站点共 150 笔交易，When 月末触发结算，Then 生成一张结算单，聚合 3 个站点的全部交易
- Given 结算单生成时，When 计算应结金额，Then 应结 = 总收款 - 佣金 - 未结退款 - 争议冻结金额
- Given 结算单状态为“待审核”，When 管理员审核通过，Then 状态变为“待打款”
- Given 结算单状态为“待打款”，When 管理员确认已线下打款，Then 状态变为“已结算”，记录打款时间和备注
- Given 结算周期内有退款/争议订单，When 生成结算单，Then 退款金额在结算单中单独列示并从应结金额中扣除

**结算单状态流转：**
```
pending(待生成) → generated(待审核) → approved(待打款) → paid(已结算)
                                → rejected(已驳回) → generated(重新生成)
paid → adjusted(有调整单)
```

**优先级**: P1 | **复杂度**: XL

---

#### US-PAY-010: 佣金规则配置

**作为** 系统管理员，
**我希望** 为不同商户和品类配置灵活的佣金规则，
**以便** 实现差异化定价和营收最大化。

**验收标准：**
- Given 商户配置了 18% 比例佣金，When 完成 $100 订单，Then 佣金 = $18
- Given 商户配置了阶梯佣金（0-$5000收15%，$5000+收12%），When 当月累计 $8000，Then 佣金 = $5000×15% + $3000×12% = $1110
- Given 某品类配置了特殊佣金率 25%，When 该品类订单结算，Then 使用品类佣金率而非商户默认佣金率
- Given 商户未配置佣金规则，When 结算计算，Then 使用系统默认佣金率（20%）

**佣金模型：**
| 类型 | 公式 | 适用场景 |
|------|------|----------|
| 比例佣金 | 佣金 = 订单金额 × 佣金率 | 标准商户 |
| 固定佣金 | 佣金 = 固定金额/笔 | 低单价商品 |
| 阶梯佣金 | 按累计金额分段计算 | VIP/大商户 |

**佣金率范围约束：**
- 最低佣金率：8%（系统下限，不可突破）
- 最高佣金率：35%（系统上限）
- 默认佣金率：20%（新商户默认）

**优先级**: P1 | **复杂度**: L

---

#### US-PAY-011: 商户风险评分与动态限额

**作为** 系统，
**我希望** 基于商户所有站点的交易数据自动计算风险评分，并根据评分动态调整交易限额，
**以便** 提前预防高风险商户导致支付账号被冻结。

**验收标准：**
- Given 商户 A 下所有站点 30 天争议率 > 5%，When 系统计算风险评分，Then 风险等级标记为“高风险”
- Given 商户风险等级为“高风险”，When 系统调整限额，Then 日限额自动下调 50%，推送钉钉告警
- Given 商户风险等级为“低风险”且累计成交额达到阈值，When 系统评估，Then 可自动提升日限额
- Given 商户风险评分更新，When 查看商户详情，Then 可看到评分历史曲线和各维度得分

**风险评分维度：**
| 指标 | 权重 | 高风险阈值 |
|------|------|----------|
| 争议率 | 30% | > 5% |
| 退款率 | 25% | > 10% |
| 拒付金额占比 | 20% | > 3% |
| 账号违规记录 | 15% | 有记录 |
| 商户合作时长 | 10% | < 30 天 |

**优先级**: P1 | **复杂度**: L

---

#### US-PAY-012: 商户查看结算信息

**作为** 商户，
**我希望** 能查看我的结算单列表、佣金明细和各站点支付数据，
**以便** 了解我的营收情况和平台扣除明细。

**验收标准：**
- Given 商户登录后台，When 访问结算列表页，Then 只能看到自己的结算单，不能看到其他商户的
- Given 结算单状态为“已结算”，When 商户查看详情，Then 可看到各站点交易汇总、佣金扣除、退款扣除、实际结算金额
- Given 商户查看结算单，When 数据展示，Then **不展示具体支付账号信息**（账号归平台所有）

**优先级**: P1 | **复杂度**: M

---

### v2.0 新增：API 签名验证

#### US-PAY-013: 支付请求签名验证

**作为** 系统，
**我希望** 验证独立站发来的支付请求的数字签名，
**以便** 确保请求来自合法的独立站且未被篡改。

**验收标准：**
- Given 独立站发起支付请求，When 请求携带有效的 X-Merchant-Key-Id 和 X-Signature 头，Then 管理后台使用对应公钥验证签名，通过后继续处理
- Given 请求缺少签名头，When 中间件检查，Then 返回 401001 错误码
- Given 签名验证失败，When 公钥验签不通过，Then 返回 401003 错误码，记录安全日志
- Given 请求时间戳偏差超过 ±5 分钟，When 验证时间窗口，Then 返回 401004 错误码（防重放攻击）
- Given 同一 Nonce 重复使用，When Redis 检测到重复，Then 返回 401005 错误码
- Given 密钥已吊销或过期，When 查询密钥状态，Then 返回 401002 错误码
- Given 连续签名失败超过阈值，When 触发安全告警，Then 推送钉钉告警 + 记录安全事件

**签名数据构造**：
```
待签名字符串 = HTTP Method + "\n" + Request Path + "\n" + Timestamp + "\n" + Request Body (JSON)
签名 = Base64(RSA-SHA256(private_key, signing_string))
```

**示例请求头**：
```
X-Merchant-Key-Id: mk_abc123def456
X-Signature: Base64(RSA-SHA256(signing_string))
X-Timestamp: 1713283200
X-Nonce: uuid-v4
```

**错误码定义**：
| 错误码 | 说明 |
|--------|------|
| 401001 | 缺少签名头（X-Merchant-Key-Id / X-Signature / X-Timestamp / X-Nonce） |
| 401002 | 密钥不存在或已吊销/过期 |
| 401003 | 签名验证失败 |
| 401004 | 请求已过期（时间窗口外） |
| 401005 | 重复请求（Nonce 重复） |

**业务规则：** BR-SEC-001, BR-SEC-002

**优先级**: P0 | **复杂度**: L

## 5. 业务规则

详见 `business-rules.md` BR-PAY-001 ~ BR-PAY-007、BR-SEC-001 ~ BR-SEC-002。

**⚠️ 安全提醒**：所有支付渠道创建订单时，必须通过 ProductMappingService 获取安全商品名称。参见 BR-MAP-003。

### v2.0 新增业务规则

以下规则需同步更新到 `business-rules.md`。

#### BR-PAY-SEC-001: 支付请求必须签名

- **触发条件**：独立站向管理后台发起支付相关请求
- **规则内容**：
  1. 所有支付相关 API 请求必须携带有效的数字签名
  2. 签名使用 RSA-SHA256 算法
  3. 时间戳偏差不超过 ±5 分钟
  4. 每个 Nonce 只能使用一次（Redis 缓存，TTL=10 分钟）
- **安全等级**：最高
- **违规处理**：拒绝请求，记录安全日志，连续失败超过阈值触发钉钉告警

#### BR-PAY-SEC-002: 密钥轮换规则

- **触发条件**：密钥接近过期或手动触发轮换
- **规则内容**：
  1. 密钥默认有效期 365 天
  2. 到期前 30 天系统自动提醒商户
  3. 轮换时新旧密钥并存 24 小时过渡期
  4. 过渡期结束后旧密钥自动失效
  5. 紧急吊销立即生效，无过渡期
- **安全等级**：高

#### BR-PAY-POOL-001: 账号分组类型

| 分组类型 | 编码 | 说明 |
|---------|------|------|
| VIP 独占 | VIP_EXCLUSIVE | 绑定单一商户，不与其他商户共享账号 |
| 标准共享 | STANDARD_SHARED | 多个标准商户共用同一批账号 |
| 轻量共享 | LITE_SHARED | 多个小商户共用，账号额度较低 |
| 黑名单隔离 | BLACKLIST_ISOLATED | 专用于黑名单用户，低额度可牺牲账号 |

#### BR-PAY-POOL-002: 账号生命周期阶段

```
new(新建) → growing(成长) → mature(成熟) → aging(老化)
                                              → suspended(暂停) → growing/aging
```

| 阶段 | 触发条件 | 限额策略 |
|------|----------|----------|
| 新建 | 刚创建的账号 | 日限 $500，月限 $3000 |
| 成长 | 累计收款 > $3000 且无异常 | 日限 $2000，月限 $15000 |
| 成熟 | 连续 30 天无异常且收款 > $15000 | 日限 $5000，月限 $50000 |
| 老化 | 健康度 < 30 分或被冻结 | 自动禁用，不再分配新订单 |

#### BR-PAY-POOL-003: 三层映射关系

```
Domain(jerseyholic-a.com)
  └→ merchant_id(merchant_001)
       └→ group_id(VIP_GROUP_1)
            └→ [PayPal Account A, PayPal Account B, Stripe Account C]

Domain(jerseyholic-b.com)
  └→ merchant_id(merchant_001)  // 同一商户可拥有多个域名
       └→ group_id(VIP_GROUP_1)  // 共用同一分组
```

**规则：**
- 一个域名必须且只能属于一个商户
- 一个商户可管理多个域名（多站点矩阵）
- 一个商户可关联一个或多个支付分组（PayPal 分组 + 信用卡分组可不同）
- 分组不可跨商户共享（VIP类型）或可跨商户共享（Shared类型）

#### BR-PAY-SETTLE-001: 结算周期规则

| 商户等级 | 结算周期 | 说明 |
|---------|---------|------|
| 入门级 | 月结（T+30） | 新商户默认 |
| 中级 | 双周结（T+14） | 月均成交 $3000-$15000 |
| 高级 | 周结（T+7） | 月均成交 $15000+ |
| VIP | 周结（T+7） | 月均成交 $50000+ |

#### BR-PAY-SETTLE-002: 结算金额计算公式

```
应结金额 = 结算周期内总收款
           - 佣金总额
           - 未结退款金额
           - 争议冻结金额
           - 历史调整单金额（如有）
```

**特殊场景：**
- 应结金额为负数：结算单状态为“待回收”，在下期结算中扣除
- 结算单已打款后发生退款：生成调整单，在下期结算中扮除
- 争议未解决订单：冻结该金额，争议解决后释放或扮除

#### BR-PAY-SETTLE-003: 支付费用承担

- PayPal 费用（4.4% + $0.30/笔）：**平台承担**，不从商户结算中扮除
- Stripe 费用（2.9% + $0.30/笔）：**平台承担**
- 支付费用已包含在佣金中，无需单独扮除

#### BR-PAY-RISK-001: 商户风险等级

| 风险等级 | 评分范围 | 影响 |
|---------|---------|------|
| 低风险 | 80-100 | 正常运营，可申请提升限额 |
| 中风险 | 50-79 | 增加监控，限额不变 |
| 高风险 | 20-49 | 限额下调 50%，触发告警 |
| 极高风险 | 0-19 | 暂停新交易，人工审核 |

#### BR-PAY-RISK-002: 黑名单类型与维度

| 类型 | 维度 | 说明 |
|------|------|------|
| 平台级黑名单 | IP、邮箱、设备指纹、支付账号 | Admin 管理，全局生效 |
| 商户级黑名单 | IP、邮箱 | 商户自行维护，仅对其站点生效 |

**匹配优先级：** 平台级黑名单 > 商户级黑名单

## 6. 数据需求

### 核心数据表（传递给 @architect）

**jh_payment_accounts** — 支付账号表
- id, account(标识), email, client(client_id), secret(secret_key)
- merchant_id(PayPal商户ID), domain(关联域名)
- pay_method(支付方式), category_id(PayPal分组), cc_category_id(信用卡分组)
- status(启用/禁用), permission(1可收款/3已封禁)
- min_money, max_money(金额范围)
- limit_money(总限额), daily_limit_money(日限额)
- money_total(累计收款), daily_money_total(日累计)
- priority(优先级), max_num(最大成交单数)
- is_new(新账号标记), is_force(强制启用)
- error_time(首次异常时间), error_msg(异常信息)
- webhook_id, access_token, access_token_expires_in
- success_url, cancel_url, pay_url, skrpay_url/key/secret
- **(v2.0 新增)** lifecycle_stage ENUM('new','growing','mature','aging') DEFAULT 'new' — 生命周期阶段
- **(v2.0 新增)** health_score TINYINT DEFAULT 100 — 健康度评分(0-100)
- **(v2.0 新增)** health_updated_at DATETIME — 健康度最后计算时间
- **(v2.0 新增)** monthly_limit_money DECIMAL — 月限额
- **(v2.0 新增)** monthly_money_total DECIMAL — 月累计收款
- **(v2.0 新增)** group_type ENUM — 分组类型冗余
- **(v2.0 新增)** frozen_signal_count INT DEFAULT 0 — 冻结/限制信号累计
- delete_time, create_time, update_time

**jh_payment_account_logs** — 账号收款日志
- id, account_id, order_id, amount, currency, created_at

**jh_payment_cards** — 信用卡信息（临时存储）
- id, order_id, name, number(加密), expiry, security_code(加密)

**jh_websites** — 站点配置
- id, website(域名), group_id(PayPal分组), cc_group_id(信用卡分组)
- user_id(商户ID), token(API token)
- **(v2.0 新增)** merchant_id — 关联商户表 ID（明确三层映射）

### v2.0 新增数据表

**jh_settlements** — 结算单表
- id
- settlement_no VARCHAR(32) UNIQUE — 结算单号
- merchant_id BIGINT — 商户 ID
- period_start DATE — 结算周期开始
- period_end DATE — 结算周期结束
- total_amount DECIMAL(12,2) — 总收款金额 (USD)
- commission_amount DECIMAL(12,2) — 佣金总额
- refund_amount DECIMAL(12,2) — 退款扮除
- dispute_frozen_amount DECIMAL(12,2) — 争议冻结金额
- adjustment_amount DECIMAL(12,2) DEFAULT 0 — 调整金额
- net_amount DECIMAL(12,2) — 实际应结金额
- status ENUM('pending','generated','approved','rejected','paid','adjusted') — 状态
- approved_by BIGINT NULL — 审核人
- approved_at DATETIME NULL — 审核时间
- paid_at DATETIME NULL — 打款时间
- paid_remark TEXT NULL — 打款备注（线下打款参考）
- created_at, updated_at

**jh_settlement_details** — 结算明细表
- id
- settlement_id BIGINT — 关联结算单
- order_id BIGINT — 订单 ID
- website_id BIGINT — 站点 ID
- order_amount DECIMAL(10,2) — 订单金额 (USD)
- commission_rate DECIMAL(5,4) — 佣金率
- commission_amount DECIMAL(10,2) — 佣金金额
- refund_amount DECIMAL(10,2) DEFAULT 0 — 退款金额
- net_amount DECIMAL(10,2) — 应结金额
- type ENUM('normal','refund','dispute','adjustment') — 明细类型
- created_at

**jh_merchant_risk_scores** — 商户风险评分表
- id
- merchant_id BIGINT — 商户 ID
- risk_score INT — 风险评分 (0-100)
- risk_level ENUM('low','medium','high','critical') — 风险等级
- dispute_rate DECIMAL(5,4) — 争议率
- refund_rate DECIMAL(5,4) — 退款率
- chargeback_amount DECIMAL(12,2) — 拒付金额
- calculated_at DATETIME — 计算时间
- created_at, updated_at

**jh_merchant_transaction_limits** — 商户交易限额表
- id
- merchant_id BIGINT — 商户 ID
- daily_limit DECIMAL(12,2) — 日限额
- monthly_limit DECIMAL(12,2) — 月限额
- daily_used DECIMAL(12,2) DEFAULT 0 — 日已用
- monthly_used DECIMAL(12,2) DEFAULT 0 — 月已用
- auto_adjusted TINYINT DEFAULT 0 — 是否自动调整
- last_adjusted_at DATETIME NULL — 最后调整时间
- created_at, updated_at

**jh_fund_flow_logs** — 资金流水日志表
- id
- merchant_id BIGINT — 商户 ID
- order_id BIGINT NULL — 关联订单
- settlement_id BIGINT NULL — 关联结算单
- type ENUM('income','commission','refund','dispute','settlement','adjustment') — 流水类型
- amount DECIMAL(12,2) — 金额
- balance_after DECIMAL(12,2) — 操作后余额
- description VARCHAR(255) — 描述
- created_at

**jh_blacklist** — 平台级黑名单表
- id
- type ENUM('ip','email','device','payment_account') — 黑名单维度
- value VARCHAR(255) — 黑名单值
- reason VARCHAR(500) — 加入原因
- expires_at DATETIME NULL — 过期时间（NULL 表示永久）
- created_by BIGINT — 创建人
- created_at, updated_at

**jh_merchant_blacklist** — 商户级黑名单表
- id
- merchant_id BIGINT — 商户 ID
- type ENUM('ip','email') — 黑名单维度
- value VARCHAR(255) — 黑名单值
- reason VARCHAR(500) — 加入原因
- created_at, updated_at

## 7. 页面/交互说明

### 买家前台
1. **支付方式选择** — 展示可用支付方式（PayPal/信用卡/本地支付）
2. **支付处理中** — 跳转到支付网关/等待页面
3. **支付成功页** — 显示订单确认信息
4. **支付失败页** — 显示错误并提供重试选项

### 管理后台
1. **账号列表页** — 查看所有支付账号、状态、收款额、限额使用率
2. **账号编辑页** — 配置账号信息、限额、分组、优先级
3. **站点支付配置** — 域名→收款分组映射
4. **支付报表** — 各账号收款统计、健康度图表
5. **（v2.0）账号分组管理** — 分组 CRUD、类型配置、商户关联
6. **（v2.0）账号健康度仪表盘** — 健康度评分、生命周期阶段、异常趋势图
7. **（v2.0）结算单列表页** — 查看所有商户结算单、状态筛选、审核操作
8. **（v2.0）结算单详情页** — 商户交易汇总、佣金明细、退款扮除、审核/确认打款
9. **（v2.0）佣金规则配置** — 按商户/品类配置佣金模型和率
10. **（v2.0）商户风险监控** — 风险评分、限额状态、争议/退款率趋势
11. **（v2.0）黑名单管理** — 平台级黑名单 CRUD

### 商户后台（v2.0 新增）
1. **我的结算** — 结算单列表、结算详情、佣金明细
2. **支付概览** — 各站点支付成功率、收款趋势（不展示支付账号）
3. **我的黑名单** — 商户级黑名单 CRUD

### 支付流程图

```mermaid
sequenceDiagram
    participant B as 买家浏览器
    participant F as 前台(Nuxt3)
    participant API as 后端API(Laravel)
    participant ES as ElectionService
    participant PAS as PayAccountService
    participant PS as PaypalService
    participant PP as PayPal API

    B->>F: 点击"立即支付"
    F->>API: POST /api/pay/create
    API->>ES: getPayAccount(data)
    ES->>ES: 风控检查(RiskService)
    ES->>ES: 创建系统订单(OrderService)
    ES->>PAS: 选择收款账号
    PAS->>PAS: 域名→分组→金额→限额筛选
    PAS-->>ES: 返回最优账号
    ES->>PS: 调用PayPal创建订单
    Note over PS: 商品名使用安全映射名称
    PS->>PP: Create Order API
    PP-->>PS: 返回授权URL
    PS-->>API: 返回支付URL
    API-->>B: 302 跳转到 PayPal

    B->>PP: 用户授权支付
    PP->>B: 302 跳转回成功URL
    B->>API: GET /success?token=xxx
    API->>ES: confirmOrder()
    ES->>PS: 确认扣款(capture)
    PS->>PP: Capture Order API
    PP-->>PS: 扣款成功
    PS-->>ES: 返回交易信息
    ES->>ES: 更新订单状态为已支付
    ES-->>B: 302 跳转到支付成功页
```

### 结算流程图（v2.0 新增）

```mermaid
flowchart TD
    A[结算周期到期] --> B[查询商户所有站点已完成订单]
    B --> C[计算佣金]
    C --> D[扮除退款/争议冻结]
    D --> E[生成结算单 status=generated]
    E --> F{Admin 审核}
    F -->|通过| G[status=approved 待打款]
    F -->|驳回| H[status=rejected 重新生成]
    G --> I[Admin 确认线下打款]
    I --> J[status=paid 已结算]
    J --> K{打款后有退款?}
    K -->|是| L[生成调整单 status=adjusted]
    K -->|否| M[结算完成]
```

## 8. API 需求

### 买家端 API
| 接口 | 方法 | 说明 |
|------|------|------|
| POST /api/pay/create | POST | 创建支付（PayPal/信用卡/Stripe等） |
| GET /api/pay/config/{order_no} | GET | 获取订单支付配置 |
| POST /api/pay/stripe/create | POST | 创建 Stripe 支付 |
| POST /api/pay/antom/create | POST | 创建 Antom 支付 |
| POST /api/pay/payssion/create | POST | 创建 Payssion 支付 |

### Webhook 回调
| 接口 | 方法 | 说明 |
|------|------|------|
| POST /api/webhook/paypal | POST | PayPal 异步通知 |
| POST /api/webhook/stripe | POST | Stripe 异步通知 |
| POST /api/webhook/antom | POST | Antom 异步通知 |
| POST /api/webhook/payssion | POST | Payssion 异步通知 |

### 管理端 API
| 接口 | 方法 | 说明 |
|------|------|------|
| GET /api/admin/payment-accounts | GET | 账号列表 |
| POST /api/admin/payment-accounts | POST | 创建账号 |
| PUT /api/admin/payment-accounts/{id} | PUT | 更新账号 |
| POST /api/admin/payment-accounts/{id}/toggle | POST | 启用/禁用 |
| POST /api/admin/refund | POST | 发起退款 |

### v2.0 新增管理端 API
| 接口 | 方法 | 说明 |
|------|------|------|
| GET /api/admin/payment-groups | GET | 支付分组列表 |
| POST /api/admin/payment-groups | POST | 创建分组 |
| PUT /api/admin/payment-groups/{id} | PUT | 更新分组 |
| DELETE /api/admin/payment-groups/{id} | DELETE | 删除分组 |
| GET /api/admin/payment-accounts/{id}/health | GET | 账号健康度详情 |
| POST /api/admin/payment-accounts/{id}/lifecycle | POST | 手动调整生命周期阶段 |
| GET /api/admin/settlements | GET | 结算单列表（筛选商户/状态/时间） |
| GET /api/admin/settlements/{id} | GET | 结算单详情（含明细） |
| POST /api/admin/settlements/{id}/approve | POST | 审核通过结算单 |
| POST /api/admin/settlements/{id}/reject | POST | 驳回结算单 |
| POST /api/admin/settlements/{id}/confirm-paid | POST | 确认已打款 |
| GET /api/admin/commission-rules | GET | 佣金规则列表 |
| POST /api/admin/commission-rules | POST | 创建佣金规则 |
| PUT /api/admin/commission-rules/{id} | PUT | 更新佣金规则 |
| GET /api/admin/merchant-risk/{merchant_id} | GET | 商户风险评分详情 |
| GET /api/admin/merchant-limits/{merchant_id} | GET | 商户限额详情 |
| PUT /api/admin/merchant-limits/{merchant_id} | PUT | 手动调整商户限额 |
| GET /api/admin/blacklist | GET | 平台黑名单列表 |
| POST /api/admin/blacklist | POST | 添加黑名单 |
| DELETE /api/admin/blacklist/{id} | DELETE | 移除黑名单 |
| GET /api/admin/payment-dashboard | GET | 支付成功率监控仪表盘数据 |
| GET /api/admin/fund-flow-logs | GET | 资金流水日志 |

### v2.0 新增商户端 API
| 接口 | 方法 | 说明 |
|------|------|------|
| GET /api/merchant/settlements | GET | 我的结算单列表 |
| GET /api/merchant/settlements/{id} | GET | 结算单详情 |
| GET /api/merchant/payment-overview | GET | 支付概览（成功率/趋势） |
| GET /api/merchant/blacklist | GET | 我的黑名单 |
| POST /api/merchant/blacklist | POST | 添加商户级黑名单 |
| DELETE /api/merchant/blacklist/{id} | DELETE | 移除商户级黑名单 |

## 9. 验收标准

### 功能验收
- [ ] PayPal 标准支付流程完整（创建→授权→确认→成功）
- [ ] PayPal 信用卡直付流程完整（含 3DS 验证）
- [ ] Stripe Checkout 支付流程完整
- [ ] 支付账号按 8 层筛选逻辑正确选择
- [ ] 异常账号 3 分钟内自动禁用
- [ ] 禁用后同分组备用账号自动启用
- [ ] Webhook 验签正确（PayPal RSA-SHA256 / Stripe MD5 / Antom 金额校验）
- [ ] 重复回调防重机制有效
- [ ] 退款全额/部分均正常

### 安全验收
- [ ] **支付接口中商品名称为安全映射名称**
- [ ] **价格字段未被映射修改**
- [ ] PayPal Webhook 使用 openssl_verify 验签
- [ ] 信用卡信息加密存储
- [ ] API 认证（HMAC-SHA256 或 Sanctum Token）
- [ ] 独立站支付请求签名验证中间件正确拒绝无签名/无效签名请求
- [ ] 时间戳防重放机制有效（±5 分钟窗口）
- [ ] Nonce 去重检查有效（Redis TTL=10 分钟）

### 边界场景
- [ ] 所有账号均不可用时，正确告警并关闭前台支付
- [ ] 货币转汇找不到汇率时，默认按 USD 处理
- [ ] PayPal 返回 PENDING 状态时，订单标记为“交易中”
- [ ] 同步回调和异步回调竞争时，只处理一次

### v2.0 新增验收标准

#### 资金池管理验收
- [ ] 支付账号分组 CRUD 功能完整，四种分组类型均可配置
- [ ] VIP 分组与商户 1:1 绑定，不可被其他商户使用
- [ ] 账号生命周期自动流转（new→growing→mature→aging）
- [ ] 健康度评分实时更新，低于 60 分自动降优先级，低于 30 分自动禁用
- [ ] Domain→merchant_id→group_id 三层映射正确解析
- [ ] 多通道降级逻辑正确（PayPal→Stripe→Antom）
- [ ] 主通道恢复后自动回切

#### 结算系统验收
- [ ] 结算单按商户维度聚合（非站点维度）
- [ ] 佣金计算正确（比例/固定/阶梯三种模型）
- [ ] 佣金率不可低于 8% 或超过 35%
- [ ] 退款金额在结算单中正确扮除
- [ ] 争议未解决订单金额被冻结不参与结算
- [ ] 结算单状态流转正确（pending→generated→approved→paid）
- [ ] 结算后发生退款时正确生成调整单
- [ ] 应结金额为负时结算单状态为“待回收”
- [ ] 商户只能查看自己的结算单，不可看到其他商户
- [ ] 商户结算视图中不展示具体支付账号信息

#### 风控增强验收
- [ ] 商户风险评分自动计算，聚合所有站点数据
- [ ] 高风险商户限额自动下调 50%
- [ ] 限额调整触发钉钉告警
- [ ] 平台级黑名单全局生效
- [ ] 商户级黑名单仅对其站点生效
- [ ] 黑名单匹配优先级正确（平台级 > 商户级）

## 10. 非功能需求

- **性能**：支付创建响应 < 3s（含账号选择）
- **可靠性**：Webhook 处理幂等，支持至少 1 次重试
- **安全**：PCI DSS 合规要求（信用卡信息不落地/加密存储）
- **监控**：账号异常/耗尽实时告警（钉钉/邮件）
- **（v2.0）健康度计算**：账号健康度评分每 10 分钟更新一次，不影响支付主流程
- **（v2.0）风险评分**：商户风险评分每小时计算一次，异步执行
- **（v2.0）结算**：结算单生成支持大批量订单聚合，单次生成耗时 < 30s
- **（v2.0）审计**：所有资金相关操作必须记录到 jh_fund_flow_logs，不可删除

## 11. 依赖与风险

| 依赖模块 | 依赖内容 |
|---------|---------|
| 商品映射模块 | ProductMappingService 获取安全商品名称 |
| 订单管理模块 | 订单创建和状态更新 |
| 商户管理模块 | 域名→分组映射配置、商户等级、商户基础信息 |
| 多语言模块 | 支付页面多语言 |
| **（v2.0）商品分类模块** | 品类信息用于佣金规则差异化 |
| **（v2.0）异步任务中心** | 结算单定时生成、健康度/风险评分定时计算 |

| 风险 | 缓解措施 |
|------|---------|
| PayPal 账号被批量冻结 | 多分组隔离 + 自动切换备用账号 + 多通道降级 |
| Webhook 延迟导致订单状态不一致 | 同步回调+异步回调双保险，Redis 防重 |
| 3DS 验证流失率高 | 提供非 3DS 信用卡支付通道作为后备 |
| **（v2.0）结算金额计算错误** | 引入对账机制（F-PAY-044），系统流水 vs 支付网关对账 |
| **（v2.0）商户风险评分误判** | 评分只影响限额，不自动封停商户，极端情况人工介入 |
| **（v2.0）线下打款未实际到账** | 结算单状态区分“待打款”和“已结算”，必须 Admin 手动确认 |
