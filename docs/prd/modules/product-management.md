---
title: 商品管理模块 PRD
module: PROD (Product Management)
version: v2.0
updated: 2026-04-16
status: Draft
priority: P0
---

# 商品管理模块 PRD

## 1. 模块概述

### 1.1 功能简述

商品管理模块是 JerseyHolic 跨境电商统一系统的核心模块，负责商品全生命周期管理。v2.0 版本在原有基础商品 CRUD 之上，新增**特货/普货分类**、**多品类体系**、**多站点商品同步**和**按目标市场展示策略**四大能力。

### 1.2 业务价值

- **安全合规**：通过特货/普货分类 + 品类级安全映射，确保支付/物流接口不暴露敏感商品信息
- **品类扩展**：从单一球衣品类扩展至鞋类、服装、配饰、电子产品等多品类运营
- **多站点效率**：商户在主商品库统一管理商品，一键同步到名下所有独立站，减少重复操作
- **市场差异化**：不同站点面向不同国家/市场，支持差异化价格、语言、展示策略

### 1.3 影响范围

| 关联模块 | 影响说明 |
|---------|---------|
| 商品映射系统（MAP） | 品类级安全名称库扩展映射优先级为 4 级 |
| 支付系统（PAY） | 支付接口使用品类级安全名称（非统一 "Sports Jersey"） |
| 物流管理（LOG） | 品类级物流规则（HS 编码、重量、申报策略） |
| Facebook Pixel（PIX） | Pixel 事件使用真实商品名称（不受映射影响） |
| 多语言系统（I18N） | 商品多语言 + 安全名称多语言 |
| 斗篷系统（CLK） | 应用层配合 Nginx 层展示控制（A 库/B 库） |
| 商户管理（MCH） | 多站点商品同步依赖商户-站点关系 |

### 1.4 与商品映射系统的关系

商品管理模块与商品映射系统（MAP）紧密协作：
- **商品管理**负责商品数据本身的 CRUD 和品类管理
- **商品映射系统**负责在支付/物流场景下将敏感商品名称替换为安全名称
- 本次升级**将映射能力从 SKU 前缀级提升到品类级**，解决"所有商品都映射为 Sports Jersey"的问题

---

## 2. 术语定义

| 术语 | 英文 | 定义 |
|------|------|------|
| 特货 | Sensitive Goods | 品牌仿制品或其他需要安全映射处理的敏感商品 |
| 普货 | Regular Goods | 正规商品，无需安全映射或品牌风险较低的商品 |
| 品类 | Category | 商品分类体系，分一级品类（L1）和二级品类（L2） |
| SKU 前缀 | SKU Prefix | SKU 编码前 3 位字符，标识商品类型（hic/WPZ/DIY/NBL/SHO/APP/ACC/ELC） |
| 安全映射 | Safe Mapping | 将真实品牌商品名替换为安全普货名称，用于支付/物流接口 |
| 安全名称 | Safe Name | 映射后的通用商品描述，如 "Athletic Training Jersey" |
| 主商品库 | Master Product Database | 商户级商品主数据存储（Merchant DB），用于统一管理和同步 |
| 站点商品库 | Store Product Database | 站点级商品存储（Store DB / Tenant DB），买家直接访问 |
| 商品同步 | Product Sync | 将主商品库数据推送到站点商品库的过程 |
| 站点级覆盖 | Store Override | 站点对主商品库数据的局部修改（如价格、可用性） |
| 混合订单 | Mixed Order | 同一订单中同时包含特货和普货的订单 |
| 斗篷 | Cloak | MagicChecker 系统在 Nginx 层进行审核/消费者流量分离 |

---

## 3. 品类体系设计

### 3.1 一级品类（L1）

| 编码 | 名称 | SKU 前缀 | 特货比例 | 默认安全名称（en） | 说明 |
|------|------|---------|---------|-------------------|------|
| JERSEY | 球衣 | hic / NBL | 高（~90%） | Athletic Training Jersey | 核心品类，足球/篮球/橄榄球球衣 |
| FOOTWEAR | 鞋类 | SHO | 高（~80%） | Sports Training Shoes | 运动鞋/休闲鞋/篮球鞋 |
| APPAREL | 服装 | APP | 中（~50%） | Casual Sports Wear | T恤/帽子/围巾/外套 |
| ACCESSORY | 配饰 | ACC | 低（~20%） | Fashion Accessories | 手表/眼镜/包袋/腕带 |
| ELECTRONICS | 电子 | ELC | 低（~10%） | Electronic Accessories | 耳机/手机壳/充电器 |
| DIY | 定制 | DIY | 中（~40%） | Custom Print Shirt | 来图定制商品 |

### 3.2 二级品类（L2）

| L1 编码 | L2 编码 | 名称 | 默认安全名称（en） |
|---------|---------|------|-------------------|
| JERSEY | JERSEY-FB | 足球球衣 | Athletic Training Jersey |
| JERSEY | JERSEY-BB | 篮球球衣 | Sports Basketball Wear |
| JERSEY | JERSEY-NFL | 橄榄球球衣 | Athletic Football Jersey |
| JERSEY | JERSEY-HOC | 冰球球衣 | Athletic Hockey Jersey |
| FOOTWEAR | FTWR-SNK | 运动鞋 | Sports Training Shoes |
| FOOTWEAR | FTWR-CSL | 休闲鞋 | Casual Walking Shoes |
| APPAREL | APRL-TSH | T恤 | Cotton T-Shirt |
| APPAREL | APRL-HAT | 帽子 | Sports Cap |
| APPAREL | APRL-JKT | 外套 | Sports Jacket |
| ACCESSORY | ACCS-WAT | 手表 | Fashion Watch |
| ACCESSORY | ACCS-BAG | 包袋 | Sports Bag |
| ACCESSORY | ACCS-GLS | 眼镜 | Fashion Sunglasses |
| ELECTRONICS | ELEC-AUD | 音频 | Wireless Earbuds |
| ELECTRONICS | ELEC-CAS | 手机壳 | Phone Protective Case |
| DIY | DIY-CUS | 定制印刷 | Custom Print Shirt |

### 3.3 品类管理功能

#### F-PROD-010: 品类创建与管理

**描述**：管理一级品类（L1）和二级品类（L2）的增删改查，支持 16 种语言的品类名称。

**输入**：品类编码、名称（多语言）、排序权重、状态、图标/图片  
**输出**：品类列表、品类树  
**业务规则**：
- 品类编码创建后不可修改（作为系统标识）
- 删除品类前需检查是否有关联商品，有则禁止删除
- 品类层级限制为 2 级（L1 → L2）

**优先级**：P0 | **复杂度**：M

#### F-PROD-011: 品类-SKU 前缀规则配置

**描述**：配置品类与 SKU 前缀的对应关系，用于自动识别新商品的品类归属。

**输入**：品类 ID、SKU 前缀列表  
**输出**：前缀-品类映射规则  
**业务规则**：
- 一个 SKU 前缀只能关联一个一级品类
- 系统预置映射：hic/NBL→JERSEY, SHO→FOOTWEAR, APP→APPAREL, ACC→ACCESSORY, ELC→ELECTRONICS, DIY→DIY
- WPZ（外贸正品）可关联任意品类，需通过二级分类进一步判定

**优先级**：P0 | **复杂度**：S

#### F-PROD-012: 品类特货标识

**描述**：在品类维度标记该品类下商品是否默认为特货，支持品类级特货比例参考值。

**输入**：品类 ID、is_sensitive 布尔标记、sensitive_ratio 参考比例  
**输出**：品类特货标识  
**业务规则**：
- `is_sensitive=true` 的品类下新建商品默认标记为特货
- 品牌和 SKU 前缀可覆盖品类级标记（见 F-PROD-020）
- 标记仅为默认值，可在商品级手动覆盖

**优先级**：P1 | **复杂度**：S

---

## 4. 特货与普货管理

### 4.1 特货识别

#### F-PROD-020: 基于品类和品牌的自动特货识别

**描述**：系统根据商品的品类归属、SKU 前缀和品牌信息自动判定商品是否为特货。

**输入**：商品 SKU、品类、品牌名称  
**输出**：`is_sensitive` 布尔标记  

**判定逻辑（优先级从高到低）**：
1. **SKU 前缀判定**（继承现有 BR-MAP-001 规则）：
   - `hic` → 特货（必定）
   - `WPZ` → 普货（必定）
   - `DIY` → 根据品牌判定
   - `NBL` → 根据品类配置判定
   - 其他新前缀 → 根据品类配置判定
2. **品牌黑名单判定**：品牌名命中特货品牌库 → 特货
3. **品类默认判定**：品类 `is_sensitive=true` → 特货

**业务规则**：
- BR-PROD-001: SKU 前缀判定优先于品牌判定，品牌判定优先于品类默认判定
- BR-PROD-002: `hic` 和 `WPZ` 前缀的判定结果不可被品牌或品类覆盖
- BR-PROD-003: 新建商品时自动执行特货识别，结果写入 `is_sensitive` 字段

**优先级**：P0 | **复杂度**：M

#### F-PROD-021: 手动特货标记

**描述**：管理员可手动将任何商品标记为特货或普货，覆盖自动识别结果。

**输入**：商品 ID、is_sensitive 布尔值、标记原因  
**输出**：更新后的特货标记  
**业务规则**：
- 手动标记优先级高于自动识别
- 手动标记需记录操作人和原因（审计追溯）
- 批量标记支持：按品类/品牌/SKU 前缀批量设置

**优先级**：P0 | **复杂度**：S

#### F-PROD-022: 混合订单处理规则

**描述**：当同一订单中包含特货和普货时的处理策略。

**业务规则**：
- BR-MIX-001: **有任何特货则全订单使用安全映射** — 订单中只要包含一件特货商品，该订单所有商品在支付/物流接口中均使用安全映射名称
- BR-MIX-002: 普货在混合订单中使用其品类对应的安全名称（而非真实名称）
- BR-MIX-003: 价格字段永远不受映射影响，始终使用真实价格
- BR-MIX-004: 前台展示（真实买家）和 Pixel 追踪不受混合订单规则影响，使用真实信息；检查人员（safe 模式）访问时展示安全映射信息

**优先级**：P0 | **复杂度**：M

### 4.2 安全映射增强

#### F-PROD-030: 品类级安全名称库

**描述**：建立基于品类的安全名称库，解决当前所有商品都映射为单一 "Sports Jersey" 的问题。不同品类使用不同的安全名称，更自然、更不易引起支付平台注意。

**输入**：品类 ID（L1/L2）、SKU 前缀、安全名称（多语言）  
**输出**：品类级安全名称记录  

**示例**：

| 品类 | SKU 前缀 | 安全名称（en） | 安全名称（de） |
|------|---------|--------------|--------------|
| JERSEY-FB | hic | Athletic Training Jersey | Sportliches Trainingstrikot |
| FOOTWEAR-SNK | SHO | Sports Training Shoes | Sportschuhe |
| APPAREL-HAT | APP | Sports Cap | Sportmütze |
| ACCESSORY-WAT | ACC | Fashion Watch | Modeuhr |
| ELECTRONICS-AUD | ELC | Wireless Earbuds | Kabellose Ohrhörer |

**业务规则**：
- 每个品类 L1+L2+SKU 前缀组合可配置独立的安全名称
- 安全名称支持 16 种语言版本
- 安全名称池应有足够多样性，避免大量订单使用完全相同的名称

**安全名称使用场景**：
| 场景 | 使用名称 | 说明 |
|------|---------|------|
| 前台展示（真实买家） | 真实品牌名称 | 斗篷判定为消费者流量，展示真实商品信息 |
| 前台展示（检查人员） | 安全映射名称 | 斗篷判定为审核流量（safe 模式），展示安全内容 |
| 支付描述（PayPal/Stripe 等） | **始终**使用安全映射名称 | 支付后台会审查商品描述 |
| 物流申报（面单/海关） | **始终**使用安全映射名称 | 海关会审查申报品名 |
| 后台管理 | 真实品牌名称 | 管理员需看到真实商品信息 |
| Facebook Pixel 追踪 | 真实品牌名称 | Pixel 事件不经过支付/物流审查 |

**优先级**：P0 | **复杂度**：L

#### F-PROD-031: 多语言安全名称

**描述**：安全名称支持多语言，在支付/物流接口中根据目标市场语言选择对应语言版本的安全名称。

**输入**：安全名称记录 ID、locale、翻译文本  
**输出**：多语言安全名称  
**业务规则**：
- 优先使用目标站点主语言对应的安全名称
- 无对应语言版本时回退到英语（en）
- 英语为必填，其他语言可选

**优先级**：P1 | **复杂度**：M

#### F-PROD-032: 安全映射查询优先级（4 级）

**描述**：扩展现有 BR-MAP-002 的 3 级优先级为 4 级，新增品类级映射。

**查询优先级**：
1. **精确映射**：`jh_product_safe_mapping` 表中该商品的专属映射
2. **SKU 前缀映射**：基于 SKU 前缀的通用名（hic→"Athletic Training Jersey", WPZ→原名, DIY→"Custom Print Shirt"）
3. **品类映射（新增）**：查询 `jh_category_safe_names` 表，按 L2→L1 层级查找品类安全名称
4. **兜底默认名**：`"Sports Training Jersey"`（可在系统设置中配置）

**业务规则**：
- BR-MAP-002-v2: 映射优先级为 精确 > SKU前缀 > 品类 > 兜底，严格按序查找，命中即停止
- 品类映射支持 SKU 前缀细分：同一品类不同 SKU 前缀可有不同安全名称

**优先级**：P0 | **复杂度**：M

#### F-PROD-033: 安全映射名称库管理

**描述**：后台管理界面管理安全名称库，包括品类级名称、精确映射和兜底名称。

**功能**：
- 安全名称列表（按品类筛选、按语言筛选）
- 安全名称新增/编辑/删除
- 批量导入安全名称（CSV）
- 安全名称使用统计（哪些名称被使用最多，避免过度集中）

**优先级**：P1 | **复杂度**：M

### 4.3 前台展示与斗篷系统配合

#### F-PROD-040: 应用层配合斗篷

**描述**：斗篷系统（MagicChecker）在 Nginx 层进行流量分离，应用层需配合提供两套展示数据。

**工作模式**：
```
Nginx 层 → 判定审核/消费者流量
  → 审核流量 → 请求 API 时带 X-Cloak-Mode: safe 头
  → 消费者流量 → 正常请求（无特殊头）
应用层 → 检查 X-Cloak-Mode 头
  → safe → 返回安全名称 + 通用化图片 + 合规描述
  → 无/normal → 返回真实商品信息
```

**业务规则**：
- BR-CLK-003: 应用层通过 HTTP 头判断是否需要返回安全内容，**不实现流量判定逻辑**
- 安全模式下：商品名称使用安全映射名、图片使用通用化图片、描述使用合规描述
- 价格在两种模式下均为真实价格

**优先级**：P0 | **复杂度**：M

#### F-PROD-041: 特货前台展示规则

**描述**：特货商品根据斗篷系统的流量判定结果，对不同访客展示不同内容。

**正常模式（真实买家）** — 无 `X-Cloak-Mode` 头或值为 `normal`：
- 商品名称：**真实品牌名称**（如 "Nike Air Max 90"）
- 商品图片：**真实商品图片**（含品牌 Logo）
- 商品描述：**真实商品描述**
- 价格：真实价格
- 品牌信息：正常展示

**安全模式（检查人员）** — `X-Cloak-Mode: safe`（品牌方/PayPal/Facebook 审核人员）：
- 商品名称：品类级安全名称（如 "Athletic Training Jersey"）
- 商品图片：预配置的品类通用图片（无品牌 Logo）
- 商品描述：通用化合规描述模板
- 价格：真实价格（不变）
- 品牌信息：隐藏

> **核心原则**：独立站默认展示真实商品信息（吸引买家下单），仅在斗篷检测到检查人员时切换为安全内容。

**优先级**：P0 | **复杂度**：M

#### F-PROD-042: 普货前台展示规则

**描述**：普货在所有模式下均使用真实商品信息展示。

**展示内容**：
- 所有字段使用真实信息
- 不受斗篷模式影响

**优先级**：P1 | **复杂度**：S

---

## 5. 多站点商品同步

### 5.1 主商品库

#### F-PROD-050: 商户主商品库管理

**描述**：每个商户拥有独立的主商品库（Merchant Master DB），作为商品数据的唯一真实来源（Single Source of Truth）。

**数据库位置**：`jerseyholic_merchant_{id}` 数据库中的 `master_products` 表  
**核心概念**：
- 主商品库存储商品主数据（名称、描述、基础价格、SKU、图片、属性等）
- 商户在主商品库中统一编辑商品，通过同步机制分发到各站点
- 站点商品库（Store DB）中的商品数据由同步写入

**优先级**：P0 | **复杂度**：L

#### F-PROD-051: 主商品 CRUD

**描述**：商户后台对主商品库的完整增删改查操作。

**功能清单**：
- 创建商品：填写基础信息、多语言描述、SKU/变体、图片、属性
- 编辑商品：修改任意字段，保存后可触发同步
- 删除商品：逻辑删除（归档），关联站点商品可选择同步删除或保留
- 列表查询：按品类/状态/关键词/SKU 前缀筛选
- 商品详情：查看完整商品信息及其在各站点的同步状态

**业务规则**：
- 主商品创建时自动执行特货识别（F-PROD-020）
- 主商品保存时根据同步规则自动触发同步（如开启了 auto_sync）

**优先级**：P0 | **复杂度**：L

### 5.2 同步机制

#### F-PROD-060: 商品同步到站点

**描述**：将主商品库数据同步到商户名下的站点数据库。

**数据流**：`Merchant DB.master_products → 同步引擎 → Store DB.products`

**同步内容**：
- 商品基础信息（名称、描述、价格、状态）
- SKU/变体数据
- 图片资源
- 属性信息
- 多语言翻译

**业务规则**：
- BR-MULTI-STORE-001: 同步采用异步队列（Laravel Job Queue），不阻塞用户操作
- 同步使用 `sync_source_id` 字段关联主商品和站点商品，保证幂等性
- 站点中没有 `sync_source_id` 的商品为站点独有商品，不参与同步

**优先级**：P0 | **复杂度**：XL

#### F-PROD-061: 同步触发方式

**描述**：支持多种同步触发方式，满足不同使用场景。

| 触发方式 | 场景 | 实现方式 |
|---------|------|---------|
| 手动触发 | 商户在后台点击"同步到站点" | API 调用 → dispatch Job |
| 保存时自动同步 | 商品保存后自动推送（需开启 auto_sync） | Model Observer → dispatch Job |
| 定时批量同步 | 每日凌晨全量校验同步 | Laravel Scheduler → dispatch Job |

**优先级**：P0 | **复杂度**：M

#### F-PROD-062: 选择性同步

**描述**：商户可精确控制商品同步到哪些站点。

**配置维度**：
- `target_store_ids`：指定目标站点列表（白名单模式）
- `excluded_store_ids`：指定排除站点列表（黑名单模式）
- 同步时取两者交集结果

**使用场景**：
- 某些商品只适合特定市场的站点
- 新站点上线前先排除，调试完毕后再加入同步

**优先级**：P1 | **复杂度**：M

#### F-PROD-063: 增量同步 vs 全量同步

**描述**：支持两种同步模式。

| 模式 | 说明 | 适用场景 |
|------|------|---------|
| 增量同步 (incremental) | 只同步 `sync_fields` 中指定的字段 | 日常商品编辑后的更新 |
| 全量同步 (full) | 覆盖站点商品的所有字段 | 首次同步、数据修复、定时校验 |

**业务规则**：
- `sync_fields` 可在同步规则中配置，如 `["name","description","base_price","images","skus"]`
- 全量同步使用 `updateOrInsert`（有则更新，无则创建）

**优先级**：P1 | **复杂度**：M

#### F-PROD-064: 同步状态监控与日志

**描述**：记录每次同步的详细日志，支持状态查询和错误排查。

**日志记录**（存储在 Central DB 的 `jh_product_sync_logs` 表）：
- 商户 ID、目标站点 ID、同步类型（全量/增量）、触发方式（手动/自动/定时）
- 商品总数、成功数、失败数
- 同步状态（pending / running / completed / failed）
- 错误详情（JSON 格式）
- 开始时间、完成时间

**管理功能**：
- 同步日志列表查询（按商户/站点/状态/时间筛选）
- 同步概览仪表盘（今日同步数、成功率、失败告警）
- 失败任务重试

**优先级**：P1 | **复杂度**：M

#### F-PROD-065: 同步冲突处理

**描述**：处理站点本地修改与主库更新的冲突。

| 冲突场景 | 处理策略 | 说明 |
|---------|---------|------|
| 站点本地修改 vs 主库更新 | 主库优先（默认，可配置） | 同步时覆盖站点本地修改 |
| 站点独有商品 | 不受影响 | 无 sync_source_id 的商品不参与同步 |
| 同步字段冲突 | 按 sync_fields 决定 | 只覆盖 sync_fields 中的字段 |
| 并发同步 | 幂等设计 | updateOrInsert by sync_source_id |

**优先级**：P2 | **复杂度**：M

### 5.3 站点级商品差异化

#### F-PROD-070: 站点级价格覆盖

**描述**：不同站点可对同一主商品设置不同的价格和货币。

**配置方式**：
- 在 `jh_product_store_sync_config` 表中设置 `price_override`
- 支持三种定价策略：fixed（固定价格）、multiplier（基础价 × 倍率）、market_based（按市场定价规则）

**示例**：
- 主商品基础价格：$29.99 USD
- 美国站：$29.99 USD（fixed）
- 欧洲站：€34.99 EUR（multiplier=1.15 + 汇率）
- 日本站：¥4,500 JPY（market_based）

**优先级**：P1 | **复杂度**：M

#### F-PROD-071: 站点级安全映射名称覆盖

**描述**：不同站点可覆盖品类级安全名称，以适应不同市场/支付渠道的审核要求。

**配置方式**：在 `jh_product_store_sync_config` 表中设置 `safe_name_override_*`（多语言字段）

**业务规则**：
- 站点级覆盖优先于品类级安全名称
- 完整的展示名称优先级（BR-MULTI-STORE-002）：站点覆盖 > 精确映射 > SKU 前缀映射 > 品类映射 > 兜底

**优先级**：P1 | **复杂度**：M

#### F-PROD-072: 站点级商品可用性控制

**描述**：控制某些商品只在特定站点上架/下架。

**配置方式**：在 `jh_product_store_sync_config` 表中设置 `is_available` 布尔值

**使用场景**：
- 某品牌在特定国家有严格版权执行，需在该国站点下架
- 某品类只适合特定市场
- 新品测试期间只在部分站点上架

**优先级**：P1 | **复杂度**：S

#### F-PROD-073: 站点级商品描述覆盖

**描述**：不同站点可覆盖商品描述，支持多语言。

**配置方式**：在 `jh_product_store_sync_config` 表中设置多语言描述覆盖字段

**使用场景**：
- 不同市场的营销文案差异
- 合规要求不同导致描述差异

**优先级**：P2 | **复杂度**：M

---

## 6. 多市场展示策略

#### F-PROD-080: 按目标市场的展示名称选择

**描述**：当买家访问站点时，系统根据站点配置和买家语言选择合适的商品展示名称。

**展示名称优先级（4 级）**：
1. 站点级名称覆盖（该站点专属名称）
2. 主商品翻译（master_product_translations 对应语言）
3. 主商品默认名称（master_products.name）
4. 英语兜底名称

**安全名称优先级（支付/物流场景，5 级）**（BR-MULTI-STORE-002）：
1. 站点级安全名称覆盖
2. 精确商品映射（jh_product_safe_mapping）
3. SKU 前缀通用名
4. 品类级安全名称（jh_category_safe_names + 对应语言）
5. 兜底默认名

**优先级**：P1 | **复杂度**：M

#### F-PROD-081: 按市场的价格转换策略

**描述**：根据站点目标市场配置的货币和定价策略展示价格。

**业务规则（BR-MULTI-STORE-003）**：
1. 站点级价格覆盖优先（如有 price_override，直接使用）
2. 否则按同步规则的 price_strategy 计算：
   - `fixed`：使用基础价格
   - `multiplier`：基础价格 × price_multiplier
   - `market_based`：根据目标市场定价规则计算
3. 最终以站点主货币（primary_currency）展示

**优先级**：P1 | **复杂度**：M

#### F-PROD-082: 按市场的物流限制

**描述**：根据站点目标市场配置物流限制和可达性。

**功能**：
- 某些商品在特定国家不可配送（如品牌执法严格的国家）
- 按站点配置 `restricted_regions`
- 不可达商品在该站点自动标记为"不可购买"

**优先级**：P2 | **复杂度**：M

---

## 7. 品类-物流映射

#### F-PROD-090: 品类级物流规则

**描述**：不同品类有不同的物流属性和规则。

| 品类 | 默认重量(g) | HS 编码 | 推荐物流渠道 | 包装要求 |
|------|-----------|---------|------------|---------|
| JERSEY | 300-500 | 6211.33 | 服装快线 / DHL | 标准塑料袋 |
| FOOTWEAR | 800-1500 | 6404.11 | 鞋类专线 | 加固纸箱 |
| APPAREL | 200-400 | 6214.90 | 服装快线 | 标准塑料袋 |
| ACCESSORY | 100-500 | 各异 | 小包专线 | 气泡袋 |
| ELECTRONICS | 100-300 | 各异 | 电子专线 | 防静电包装 |
| DIY | 300-500 | 6211.33 | 服装快线 | 标准塑料袋 |

**业务规则**：
- BR-PROD-010: 品类级物流规则为默认值，可在商品级覆盖
- BR-PROD-011: 物流面单商品名称使用安全映射名称（参见 BR-MAP-003 / BR-SHIP-003）

**优先级**：P1 | **复杂度**：M

#### F-PROD-091: 品类级申报策略

**描述**：根据品类配置海关申报策略。

**申报规则**：
- 特货统一低申报（lowball declaration），金额为实际价格的 10%-30%
- 普货可选真实申报或适度低申报
- 申报品名使用安全映射名称
- 申报重量使用品类默认重量（可在商品级覆盖）

**优先级**：P2 | **复杂度**：M

---

## 8. 数据模型

### 8.1 新增/扩展表

#### Central DB 新增表

| 表名 | 用途 | 库位置 |
|------|------|--------|
| jh_product_categories_l1 | 一级品类 | Central DB |
| jh_product_categories_l2 | 二级品类 | Central DB |
| jh_category_safe_names | 品类安全名称库 | Central DB |
| jh_category_sku_prefix_rules | 品类-SKU前缀映射规则 | Central DB |
| jh_sensitive_brands | 特货品牌黑名单 | Central DB |

#### jh_product_categories_l1 — 一级品类表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 品类 ID |
| code | VARCHAR(20) UNIQUE | 品类编码（如 JERSEY） |
| name_en | VARCHAR(100) | 英文名称（必填） |
| name_de, name_fr, ... | VARCHAR(100) | 其他 15 种语言名称 |
| icon | VARCHAR(255) NULL | 品类图标 |
| is_sensitive | TINYINT(1) DEFAULT 0 | 是否默认特货品类 |
| sort_order | INT DEFAULT 0 | 排序权重 |
| status | ENUM('active','inactive') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_product_categories_l2 — 二级品类表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 品类 ID |
| category_l1_id | BIGINT UNSIGNED FK | 关联一级品类 |
| code | VARCHAR(30) UNIQUE | 品类编码（如 JERSEY-FB） |
| name_en | VARCHAR(100) | 英文名称（必填） |
| name_de, name_fr, ... | VARCHAR(100) | 其他语言名称 |
| is_sensitive | TINYINT(1) NULL | 特货标记（NULL 继承 L1） |
| sort_order | INT DEFAULT 0 | 排序权重 |
| status | ENUM('active','inactive') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### jh_category_safe_names — 品类安全名称库

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 记录 ID |
| category_l1_id | BIGINT UNSIGNED FK NULL | 一级品类 |
| category_l2_id | BIGINT UNSIGNED FK NULL | 二级品类（更精确） |
| sku_prefix | VARCHAR(10) NULL | SKU 前缀（可选，进一步细分） |
| safe_name_en | VARCHAR(255) | 英文安全名称（必填） |
| safe_name_de, safe_name_fr, ... | VARCHAR(255) | 其他语言安全名称 |
| usage_count | INT DEFAULT 0 | 使用次数统计 |
| status | ENUM('active','inactive') | 状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

**索引**：`UNIQUE(category_l1_id, category_l2_id, sku_prefix)` — 同一品类+前缀组合唯一

#### Merchant DB 扩展

`master_products` 表新增字段：

| 字段名 | 类型 | 说明 |
|--------|------|------|
| category_l1_id | BIGINT UNSIGNED NULL | 一级品类 ID |
| category_l2_id | BIGINT UNSIGNED NULL | 二级品类 ID |
| is_sensitive | TINYINT(1) DEFAULT 0 | 是否特货 |
| sensitive_source | ENUM('auto','manual') NULL | 特货标记来源 |
| safe_name_override | VARCHAR(255) NULL | 商品级安全名称覆盖 |

#### jh_product_store_sync_config — 站点级覆盖配置表

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT UNSIGNED PK | 记录 ID |
| master_product_id | BIGINT UNSIGNED FK | 主商品 ID |
| store_id | BIGINT UNSIGNED FK | 站点 ID |
| price_override | DECIMAL(10,2) NULL | 价格覆盖 |
| currency_override | VARCHAR(3) NULL | 货币覆盖 |
| is_available | TINYINT(1) DEFAULT 1 | 站点可用性 |
| safe_name_override_en | VARCHAR(255) NULL | 安全名称覆盖（英文） |
| safe_name_override_de | VARCHAR(255) NULL | 安全名称覆盖（德文） |
| description_override | TEXT NULL | 描述覆盖 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

**索引**：`UNIQUE(master_product_id, store_id)` — 每个商品每个站点唯一配置

### 8.2 实体关系

```
jh_product_categories_l1 (Central)
  └─ 1:N → jh_product_categories_l2 (Central)
  └─ 1:N → jh_category_safe_names (Central)

master_products (Merchant DB)
  └─ N:1 → jh_product_categories_l1 (Central, 跨库关联)
  └─ N:1 → jh_product_categories_l2 (Central, 跨库关联)
  └─ 1:N → master_product_translations (Merchant DB)
  └─ 1:N → sync_rules (Merchant DB)
  └─ 1:N → jh_product_store_sync_config (Merchant DB)
  └─ 1:N → products (Store DB, 通过 sync_source_id)

jh_merchants (Central)
  └─ 1:N → jh_stores (Central)

jh_stores (Central)
  └─ 1:1 → Store DB (jerseyholic_store_{id})
```

---

## 9. 接口清单

### 9.1 商品管理 API（商户后台）

| 方法 | 路径 | 描述 | 优先级 |
|------|------|------|--------|
| GET | /api/v1/merchant/products | 主商品列表（支持筛选/分页） | P0 |
| POST | /api/v1/merchant/products | 创建主商品 | P0 |
| GET | /api/v1/merchant/products/{id} | 主商品详情 | P0 |
| PUT | /api/v1/merchant/products/{id} | 编辑主商品 | P0 |
| DELETE | /api/v1/merchant/products/{id} | 删除（归档）主商品 | P0 |
| GET | /api/v1/merchant/products/{id}/sync-status | 查看商品在各站点的同步状态 | P1 |

### 9.2 品类管理 API（平台管理后台）

| 方法 | 路径 | 描述 | 优先级 |
|------|------|------|--------|
| GET | /api/v1/admin/categories/l1 | 一级品类列表 | P0 |
| POST | /api/v1/admin/categories/l1 | 创建一级品类 | P0 |
| PUT | /api/v1/admin/categories/l1/{id} | 编辑一级品类 | P0 |
| DELETE | /api/v1/admin/categories/l1/{id} | 删除一级品类 | P0 |
| GET | /api/v1/admin/categories/l2 | 二级品类列表（可按 L1 筛选） | P0 |
| POST | /api/v1/admin/categories/l2 | 创建二级品类 | P0 |
| PUT | /api/v1/admin/categories/l2/{id} | 编辑二级品类 | P0 |
| DELETE | /api/v1/admin/categories/l2/{id} | 删除二级品类 | P0 |
| GET | /api/v1/admin/categories/tree | 品类树（L1+L2 嵌套） | P0 |

### 9.3 同步管理 API（商户后台）

| 方法 | 路径 | 描述 | 优先级 |
|------|------|------|--------|
| POST | /api/v1/merchant/products/{id}/sync | 同步单个商品到站点 | P0 |
| POST | /api/v1/merchant/products/batch-sync | 批量同步商品 | P1 |
| GET | /api/v1/merchant/sync/logs | 同步日志列表 | P1 |
| GET | /api/v1/merchant/sync/overview | 同步概览统计 | P1 |
| POST | /api/v1/merchant/sync/logs/{id}/retry | 重试失败的同步任务 | P1 |

### 9.4 安全映射管理 API（平台管理后台）

| 方法 | 路径 | 描述 | 优先级 |
|------|------|------|--------|
| GET | /api/v1/admin/safe-names | 安全名称列表 | P0 |
| POST | /api/v1/admin/safe-names | 创建安全名称 | P0 |
| PUT | /api/v1/admin/safe-names/{id} | 编辑安全名称 | P0 |
| DELETE | /api/v1/admin/safe-names/{id} | 删除安全名称 | P0 |
| POST | /api/v1/admin/safe-names/import | 批量导入安全名称 | P1 |
| GET | /api/v1/admin/safe-names/stats | 安全名称使用统计 | P2 |

### 9.5 站点级覆盖 API（商户后台）

| 方法 | 路径 | 描述 | 优先级 |
|------|------|------|--------|
| GET | /api/v1/merchant/products/{id}/store-configs | 查看商品的站点级配置 | P1 |
| PUT | /api/v1/merchant/products/{id}/store-configs/{storeId} | 设置/更新站点级配置 | P1 |
| DELETE | /api/v1/merchant/products/{id}/store-configs/{storeId} | 删除站点级配置（恢复默认） | P1 |

### 9.6 买家前台 API（Storefront）

| 方法 | 路径 | 描述 | 优先级 |
|------|------|------|--------|
| GET | /api/v1/store/products | 站点商品列表（分页/筛选/排序） | P0 |
| GET | /api/v1/store/products/{slug} | 商品详情 | P0 |
| GET | /api/v1/store/categories | 站点品类列表 | P0 |
| GET | /api/v1/store/products/search | 商品搜索 | P1 |

---

## 10. 业务规则

### BR-PROD: 商品管理规则

| 规则 ID | 描述 | 优先级 |
|---------|------|--------|
| BR-PROD-001 | 特货识别优先级：SKU 前缀 > 品牌黑名单 > 品类默认 | P0 |
| BR-PROD-002 | hic 前缀必定为特货，WPZ 前缀必定为普货，不可被覆盖 | P0 |
| BR-PROD-003 | 新建商品时自动执行特货识别，结果写入 is_sensitive 字段 | P0 |
| BR-PROD-004 | 手动特货标记优先级高于自动识别 | P0 |
| BR-PROD-005 | 品类编码创建后不可修改 | P0 |
| BR-PROD-006 | 删除品类前需检查是否有关联商品 | P0 |
| BR-PROD-010 | 品类级物流规则为默认值，可在商品级覆盖 | P1 |
| BR-PROD-011 | 物流面单商品名称使用安全映射名称 | P0 |

### BR-MAP: 映射规则（扩展）

| 规则 ID | 描述 | 优先级 |
|---------|------|--------|
| BR-MAP-001 | SKU 前缀分类（继承现有，扩展新前缀 SHO/APP/ACC/ELC） | P0 |
| BR-MAP-002-v2 | 映射优先级 4 级：精确 > SKU前缀 > **品类** > 兜底 | P0 |
| BR-MAP-003 | 场景使用规则（继承现有，不变） | P0 |
| BR-MAP-005 | 安全名称多样性：同一品类应配置 3+ 个安全名称轮换使用 | P1 |

### BR-MIX: 混合商户规则

| 规则 ID | 描述 | 优先级 |
|---------|------|--------|
| BR-MIX-001 | 混合订单识别：订单中任一商品为特货，则标记整个订单为特货订单 | P0 |
| BR-MIX-002 | 混合订单中普货也使用品类安全名称（支付/物流场景） | P0 |
| BR-MIX-003 | 价格字段永不受映射影响 | P0 |
| BR-MIX-004 | 前台展示（真实买家）和 Pixel 追踪使用真实商品信息；前台展示（检查人员/safe 模式）使用安全映射信息 | P0 |

### BR-MULTI-STORE: 多站点规则

| 规则 ID | 描述 | 优先级 |
|---------|------|--------|
| BR-MULTI-STORE-001 | 商品同步采用异步队列，sync_source_id 保证幂等 | P0 |
| BR-MULTI-STORE-002 | 展示/安全名称优先级：站点覆盖 > 精确映射 > SKU 前缀 > 品类映射 > 兜底 | P1 |
| BR-MULTI-STORE-003 | 价格优先级：站点覆盖 > 同步规则策略 > 基础价格 | P1 |
| BR-MULTI-STORE-004 | 主库优先：同步时默认以主商品库数据覆盖站点数据（可配置） | P1 |
| BR-MULTI-STORE-005 | 站点独有商品（无 sync_source_id）不参与同步 | P0 |

---

## 11. 验收标准

### 功能验收 — 品类管理

- [ ] 可创建一级品类，包含编码、名称（16 种语言）、特货标识
- [ ] 可在一级品类下创建二级品类
- [ ] 品类编码创建后不可修改
- [ ] 删除有关联商品的品类时系统返回错误提示
- [ ] 品类列表可按状态筛选，支持树形展示

### 功能验收 — 特货/普货识别

- [ ] 创建 hic 前缀 SKU 的商品时自动标记为特货
- [ ] 创建 WPZ 前缀 SKU 的商品时自动标记为普货
- [ ] 创建其他前缀 SKU 的商品时根据品牌和品类自动判定
- [ ] 管理员可手动覆盖特货标记，操作日志记录完整
- [ ] 批量特货标记功能正常

### 功能验收 — 安全映射增强

- [ ] 品类级安全名称库可增删改查
- [ ] 安全名称支持 16 种语言
- [ ] 映射优先级 4 级查询正确：精确 > SKU 前缀 > 品类 > 兜底
- [ ] 不同品类的商品在支付接口使用不同安全名称（非统一 "Sports Jersey"）
- [ ] 安全名称使用统计可查

### 功能验收 — 多站点同步

- [ ] 商户可创建主商品并手动同步到指定站点
- [ ] 保存时自动同步功能正常（开启 auto_sync 后）
- [ ] 选择性同步（目标站点/排除站点）正确执行
- [ ] 增量同步只更新指定字段
- [ ] 全量同步正确创建/更新站点商品
- [ ] 同步日志完整记录，可查询
- [ ] 同步失败可重试

### 功能验收 — 站点级差异化

- [ ] 站点级价格覆盖正确生效
- [ ] 站点级安全名称覆盖正确生效
- [ ] 站点级可用性控制正确（下架的商品在该站点不展示）
- [ ] 站点级描述覆盖正确展示

### 安全验收

- [ ] 支付接口（PayPal/Stripe/Antom 等）中的商品名称为映射后的安全名称
- [ ] 物流面单中的商品名称为映射后的安全名称
- [ ] 价格字段在所有场景下未被映射修改
- [ ] Facebook Pixel 追踪使用真实商品名称和价格
- [ ] 真实买家（正常模式）前台展示真实品牌名称、真实图片、真实描述
- [ ] 检查人员（safe 模式）前台展示安全映射名称、通用化图片、合规描述
- [ ] 支付接口中商品描述**始终**使用安全映射名称（不区分访客类型）
- [ ] 物流面单/海关申报**始终**使用安全映射名称（不区分访客类型）
- [ ] 混合订单中所有商品在支付/物流接口均使用安全名称
- [ ] 斗篷安全模式（X-Cloak-Mode: safe）下返回安全内容

### 多语言验收

- [ ] 品类名称切换语言后正确显示对应翻译
- [ ] 安全名称在不同语言站点使用对应语言版本
- [ ] 缺失翻译时正确回退到英语
- [ ] 阿拉伯语(ar)页面 RTL 布局正确

### 边界场景

- [ ] 商品无任何映射记录时正确使用兜底默认名
- [ ] 同步目标站点不可用时任务进入失败状态，不影响其他站点
- [ ] 并发同步同一商品到同一站点时不产生重复数据
- [ ] 大批量同步（1000+ 商品）时队列正常消费，不超时
- [ ] 站点数据库连接失败时同步任务记录错误并可重试

---

## 12. 优先级与排期建议

### 分阶段实施

#### Phase 1: 品类体系 + 特货识别（1-2 周，P0）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| F-PROD-010 品类创建与管理 | P0 | 3 天 |
| F-PROD-011 品类-SKU 前缀规则 | P0 | 1 天 |
| F-PROD-020 自动特货识别 | P0 | 2 天 |
| F-PROD-021 手动特货标记 | P0 | 1 天 |
| F-PROD-022 混合订单规则 | P0 | 2 天 |
| F-PROD-030 品类级安全名称库 | P0 | 3 天 |
| F-PROD-032 映射优先级 4 级 | P0 | 2 天 |

#### Phase 2: 多站点同步（2-3 周，P0/P1）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| F-PROD-050 主商品库管理 | P0 | 2 天 |
| F-PROD-051 主商品 CRUD | P0 | 3 天 |
| F-PROD-060 商品同步到站点 | P0 | 4 天 |
| F-PROD-061 同步触发方式 | P0 | 2 天 |
| F-PROD-062 选择性同步 | P1 | 2 天 |
| F-PROD-064 同步日志 | P1 | 2 天 |

#### Phase 3: 站点差异化 + 市场策略（1-2 周，P1）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| F-PROD-070 站点级价格覆盖 | P1 | 2 天 |
| F-PROD-071 站点级安全名称覆盖 | P1 | 1 天 |
| F-PROD-072 站点级可用性控制 | P1 | 1 天 |
| F-PROD-080 展示名称选择 | P1 | 2 天 |
| F-PROD-081 价格转换策略 | P1 | 2 天 |
| F-PROD-040 应用层配合斗篷 | P0 | 2 天 |

#### Phase 4: 增强功能（1 周，P1/P2）

| 功能 | 优先级 | 预估 |
|------|--------|------|
| F-PROD-012 品类特货标识 | P1 | 1 天 |
| F-PROD-031 多语言安全名称 | P1 | 2 天 |
| F-PROD-033 安全映射名称库管理 | P1 | 2 天 |
| F-PROD-063 增量/全量同步 | P1 | 1 天 |
| F-PROD-065 同步冲突处理 | P2 | 1 天 |
| F-PROD-073 站点描述覆盖 | P2 | 1 天 |
| F-PROD-090 品类级物流规则 | P1 | 2 天 |
| F-PROD-091 品类级申报策略 | P2 | 1 天 |
| F-PROD-082 市场物流限制 | P2 | 1 天 |

### 总预估工期

| 阶段 | 工期 | 核心产出 |
|------|------|---------|
| Phase 1 | 1-2 周 | 品类体系 + 特货/普货 + 安全映射增强 |
| Phase 2 | 2-3 周 | 主商品库 + 多站点同步引擎 |
| Phase 3 | 1-2 周 | 站点级差异化 + 市场展示策略 |
| Phase 4 | 1 周 | 增强功能 + 物流映射 |
| **合计** | **5-8 周** | 完整商品管理模块 |

---

## 附录：与现有功能清单的映射

本 PRD 新增功能与 `feature-list.md` 中已有功能的关系：

| 现有功能 ID | 现有功能 | 本 PRD 扩展 |
|------------|---------|------------|
| F-PROD-001 | 商品 CRUD | → F-PROD-051 升级为主商品 CRUD |
| F-PROD-002 | 商品分类管理 | → F-PROD-010/011/012 升级为品类体系 |
| F-PROD-003 | 商品变体/SKU 管理 | SKU 前缀扩展新品类 |
| F-MAP-001 | SKU 前缀分类识别 | → F-PROD-020 扩展为多维度特货识别 |
| F-MAP-002~005 | 映射管理 | → F-PROD-030~032 扩展为品类级映射 |
| F-MAP-006 | 安全名称库管理 | → F-PROD-033 扩展为品类级名称库 |
| F-MAP-007 | 映射使用场景控制 | → F-PROD-040~042 增加斗篷配合 |

**新增功能编号范围**：F-PROD-010 ~ F-PROD-091（本 PRD 新增 28 个功能点）
