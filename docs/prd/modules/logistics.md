# 物流管理 PRD

> 优先级：**P1** | 版本：v1.0 | 更新日期：2026-04-16
> 关联业务规则：BR-SHIP-001 ~ BR-SHIP-003, BR-MAP-003

## 1. 概述

### 功能简述
物流管理模块负责订单发货全流程：运费计算、面单生成、物流轨迹同步、PayPal 卖家保护上传。从 OpenCart 继承运费计算规则，从 ThinkPHP 继承物流供应商对接和异步发货任务。

### ⚠️ 安全约束
物流面单和 PayPal 卖家保护上传中的商品名称**必须使用安全映射名称**（参见 BR-MAP-003）。

## 2. 用户故事

#### US-LOG-001: 运费计算

**作为** 买家，
**我希望** 结账时根据我的地址自动计算运费，
**以便** 在付款前了解总费用。

**验收标准：**
- Given 买家输入美国地址，When 计算运费，Then 根据配置返回可用配送方式和费用
- Given 订单满 $99，When 计算运费，Then 显示"Free Shipping"选项
- Given 目的地不在配送范围，When 计算运费，Then 提示"该地区暂不支持配送"
- Given 按重量计费商品，When 计算运费，Then 运费=总重量×费率+基础费

**优先级**: P0 | **复杂度**: L

---

#### US-LOG-002: 物流面单生成

**作为** 仓库管理员，
**我希望** 为已支付订单生成物流面单，
**以便** 安排发货。

**验收标准：**
- Given 订单已支付，When 生成面单，Then 面单中商品名称为**安全映射名称**
- Given 面单生成成功，When 更新订单，Then 发货状态→配货完成
- Given 批量选择多个订单，When 批量生成面单，Then 按顺序生成

**优先级**: P1 | **复杂度**: L

---

#### US-LOG-003: PayPal 卖家保护

**作为** 系统，
**我希望** 发货后自动将物流单号上传到 PayPal，
**以便** 获得 PayPal 卖家保护权益。

**验收标准：**
- Given 订单已发货，When 上传物流信息到 PayPal，Then 使用**安全商品名称**
- Given 内部物流渠道名，When 上传到 PayPal，Then 映射为 PayPal 识别的标准物流公司名
- Given 上传成功，When PayPal 确认，Then 记录上传状态

**优先级**: P1 | **复杂度**: L

---

#### US-LOG-004: 物流轨迹同步

**作为** 买家/管理员，
**我希望** 查看订单的物流轨迹信息，
**以便** 跟踪包裹配送进度。

**验收标准：**
- Given 订单已发货且有物流单号，When 定时同步轨迹，Then 从 AfterShip 等平台获取最新轨迹
- Given 物流已签收，When 同步到系统，Then 发货状态更新
- Given 买家查看订单详情，When 展示物流轨迹，Then 显示时间线式轨迹列表

**优先级**: P1 | **复杂度**: L

## 3. 物流供应商列表（从 ThinkPHP 继承）

| 供应商 | 类型 | 说明 |
|--------|------|------|
| 全球专线小包-T(服装快线) | 中国到全球 | 按目的国映射 DHL/Royal Mail 等 |
| USPS标准服装专线 | 美国 | 映射为 USPS |
| 德国专线小包-T | 德国 | 映射为 DHL |
| 欧洲专线小包-TZ球衣 | 欧洲 | 按国家映射 |
| 澳洲服装-ZH | 澳洲 | 映射为 Australia Post |
| China Post | 中国邮政 | 直接使用 |
| AfterShip | 轨迹追踪平台 | API 对接 |

## 4. 数据需求

**jh_shipments** — 发货记录
- order_id, tracking_number, carrier(物流公司)
- carrier_mapped(PayPal标准名), status
- shipped_at, delivered_at
- paypal_uploaded(是否已上传PayPal)

**jh_shipping_methods** — 运费方式配置
- id, name, type(flat/weight/item/free), status
- cost, geo_zone_id, sort_order
- weight_rate, min_weight, max_weight
- free_threshold(免运费阈值)

**jh_tracking_events** — 物流轨迹事件
- shipment_id, event_time, description, location

## 5. 验收标准

### 功能验收
- [ ] 运费按固定/重量/件数/免运费正确计算
- [ ] 地理区域限制正确生效
- [ ] 面单生成流程正常
- [ ] 物流轨迹定时同步
- [ ] PayPal 卖家保护上传成功

### 安全验收
- [ ] **面单中商品名称为安全映射名称**
- [ ] **PayPal 卖家保护上传使用安全商品名称**
- [ ] 物流公司名正确映射为标准名称

### 边界场景
- [ ] 目的地不可达时正确提示
- [ ] 物流 API 超时时有重试机制
- [ ] 批量发货任务异常时不影响其他订单
