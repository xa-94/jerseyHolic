---
title: 特货/普货分类与商品显示策略调研报告
date: 2026-04-16
version: v1.0
author: Research Agent
---

# 特货/普货分类与商品显示策略调研报告

**项目**：JerseyHolic 跨境电商统一系统重构  
**任务**：Task #58 - 特货/普货分类与商品显示策略调研  
**报告日期**：2026-04-16 | **版本**：v1.0

## 执行摘要

本报告对 JerseyHolic 跨境电商平台的商品分类、安全映射、支付物流、展示策略进行了深入调研，并融入了多商户多站点的新需求。主要发现：

1. **SKU 前缀体系需扩展**：当前仅支持球衣，需支持多品类和敏感商品
2. **特货定义需完善**：品牌仿制、成人用品、药品、烟草、酒精等需分别处理
3. **混合商户策略复杂**：同订单内混合商品需统一映射策略
4. **多品类物流差异大**：需建立品类级别物流配置
5. **多站点商品同步**：一商户多站点时需支持差异化配置和自动同步
6. **应用层配合**：支付物流必须使用安全映射，前台展示使用真实信息

## 核心建议

### 短期（1-2周）：品类与映射完善
- 建立一级品类（6大）+ 二级细类（15个）体系
- 扩展安全映射库，支持品类  语言  SKU前缀组合
- 实现BR-PAY-MAP-001普货风险检测、BR-SHIP-003品类级安全名称

### 中期（2-3周）：多站点支持
- 创建商户-站点关系表、产品同步配置表
- 实现BR-MULTI-STORE-001商品同步、BR-MULTI-STORE-002展示名称选择、BR-MULTI-STORE-003价格转换
- 支持站点级覆盖配置（价格、安全映射、可用性）

### 长期（1-2周）：应用层集成
- ProductService支持store_id参数
- PaymentService/ShipmentService集成新规则
- 后台管理界面完善

## 关键表设计

| 表名 | 用途 | 关键字段 |
|-----|------|---------|
| jh_product_categories_l1 | 一级品类 | code, name_* (16语言) |
| jh_product_categories_l2 | 二级细类 | category_l1_id, code, name_* |
| jh_category_safe_names | 品类安全名称库 | category_l1/l2_id, sku_prefix, safe_name_* |
| jh_merchant_stores | 商户-站点关系 | merchant_id, store_id |
| jh_product_store_sync_config | 站点级覆盖 | product_id, store_id, price_override, safe_name_override_* |
| jh_store_market_configs | 站点市场配置 | store_id, market_code |
| jh_market_language_configs | 市场-语言配置 | store_id, market_code, locale |
| jh_market_payment_configs | 市场-支付配置 | store_id, market_code, payment_method |

## 核心业务规则

| 规则ID | 描述 | 优先级 |
|--------|------|--------|
| BR-MAP-001 | SKU前缀分类 | P0 |
| BR-MIX-001 | 混合订单识别 | P0 |
| BR-PAY-MAP-001 | 普货风险检测 | P1 |
| BR-MULTI-STORE-001 | 商品同步规则 | P1 |
| BR-MULTI-STORE-002 | 展示名称选择（优先级：覆盖>精确映射>品类映射>兜底） | P1 |
| BR-MULTI-STORE-003 | 价格转换（覆盖>汇率转换） | P1 |

## 后端服务建议

- MerchantProductService：主商品库CRUD
- StoreProductSyncService：商品同步至站点（支持手动和定时）
- ProductDisplayService：展示名称/价格查询（支持多站点+多语言）
- CategoryMappingService：品类与安全名称映射

---

*详细分析见本报告完整版本*
