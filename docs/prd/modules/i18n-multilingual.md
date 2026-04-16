# 多语言系统 PRD

> 优先级：**P0（核心语言）+ P1/P2（扩展语言）** | 版本：v1.0 | 更新日期：2026-04-16
> 关联业务规则：BR-I18N-001 ~ BR-I18N-003

## 1. 概述

### 功能简述
多语言系统从 OpenCart 继承 16 种语言支持，在新系统（Nuxt 3 + Vue 3）中重新实现，包括界面翻译（@nuxtjs/i18n）、商品描述多语言存储、URL 语言前缀路由、RTL 布局支持和 hreflang SEO 标签。

### 业务价值
- 覆盖全球主要市场，提升国际化用户体验
- SEO 友好的多语言 URL 和 hreflang 标签
- 阿拉伯语 RTL 布局支持中东市场

## 2. 语言清单

| 语言 | Locale | 方向 | 优先级 | 上线阶段 |
|------|--------|------|--------|---------|
| English | en | LTR | P0 | 第一期 |
| German | de | LTR | P0 | 第一期 |
| French | fr | LTR | P0 | 第一期 |
| Spanish | es | LTR | P0 | 第一期 |
| Italian | it | LTR | P1 | 第二期 |
| Japanese | ja | LTR | P1 | 第二期 |
| Korean | ko | LTR | P1 | 第二期 |
| Portuguese (BR) | pt-BR | LTR | P1 | 第二期 |
| Portuguese (PT) | pt-PT | LTR | P1 | 第二期 |
| Dutch | nl | LTR | P1 | 第二期 |
| Arabic | ar | **RTL** | P1 | 第二期 |
| Polish | pl | LTR | P2 | 第三期 |
| Swedish | sv | LTR | P2 | 第三期 |
| Danish | da | LTR | P2 | 第三期 |
| Turkish | tr | LTR | P2 | 第三期 |
| Greek | el | LTR | P2 | 第三期 |

## 3. 用户故事

#### US-I18N-001: 语言切换

**作为** 买家，
**我希望** 切换网站语言后，页面内容（导航/按钮/商品描述）全部切换为对应语言，
**以便** 使用我熟悉的语言浏览和购物。

**验收标准：**
- Given 当前为英语页面，When 切换为德语，Then URL 变为 /de/...，页面内容全部变为德语
- Given 商品有德语描述，When 切换为德语，Then 显示德语商品名/描述
- Given 商品无德语描述，When 切换为德语，Then 回退显示英语内容
- Given 切换语言后，When 刷新页面，Then 保持选择的语言（Cookie 保存）

**优先级**: P0

---

#### US-I18N-002: URL 语言前缀

**作为** SEO 优化人员，
**我希望** 每种语言有独立的 URL 前缀（如 /de/、/fr/），
**以便** 搜索引擎正确索引各语言版本。

**验收标准：**
- Given 英语为默认语言，When 访问 /product/xxx，Then 显示英语版本
- Given 切换到德语，When URL 变为 /de/product/xxx，Then 显示德语版本
- Given 所有页面，When 渲染 HTML，Then 包含 hreflang alternate 标签指向所有语言版本
- Given 搜索引擎爬取，When 解析 hreflang，Then 能正确识别语言关系

**优先级**: P0 | **复杂度**: M

---

#### US-I18N-003: RTL 布局支持

**作为** 阿拉伯语用户，
**我希望** 切换到阿拉伯语时，页面布局自动切换为从右到左（RTL），
**以便** 获得符合阅读习惯的浏览体验。

**验收标准：**
- Given 切换到阿拉伯语(ar)，When 页面渲染，Then `<html dir="rtl">` 且所有元素镜像翻转
- Given RTL 布局，When 导航菜单，Then 从右侧开始排列
- Given RTL 布局，When 商品网格，Then 从右到左排列
- Given RTL 布局，When 结账表单，Then 标签和输入框右对齐

**优先级**: P1 | **复杂度**: L

---

#### US-I18N-004: 语言自动检测

**作为** 首次访问的买家，
**我希望** 系统自动检测我的语言偏好并显示对应语言，
**以便** 无需手动切换。

**验收标准：**
- Given 浏览器 Accept-Language 为 de-DE，When 首次访问，Then 自动显示德语版本
- Given 用户之前选择过法语（Cookie），When 再次访问，Then 显示法语版本
- Given 浏览器语言为不支持的语言（如中文），When 首次访问，Then 回退到英语

**业务规则：** BR-I18N-001（URL前缀 > Cookie > 浏览器 > 默认 > 英语）

**优先级**: P0

---

#### US-I18N-005: 后台翻译管理

**作为** 管理员，
**我希望** 在后台管理界面文本翻译，
**以便** 快速修改翻译内容而无需改代码。

**验收标准：**
- Given 查看翻译列表，When 按语言/模块筛选，Then 显示对应翻译键值
- Given 修改某翻译值，When 保存，Then 前台立即生效

**优先级**: P1

## 4. 数据需求

**jh_languages** — 语言配置表
- id, code(locale), name, direction(ltr/rtl), status, sort_order, is_default

**jh_translations** — 自定义翻译覆盖
- id, locale, group(模块), key, value

> 商品/分类多语言数据存储在各自的 descriptions 表中（见商品管理 PRD）。

## 5. 验收标准

### 功能验收
- [ ] 16 种语言正确加载和显示
- [ ] URL 前缀路由正确 /{locale}/...
- [ ] 语言切换后 Cookie 保存偏好
- [ ] 商品描述按语言正确展示
- [ ] 缺失翻译回退到英语

### RTL 验收
- [ ] 阿拉伯语页面 `dir="rtl"` 正确设置
- [ ] 导航、网格、表单布局正确镜像
- [ ] RTL 下 CSS 无错位

### SEO 验收
- [ ] 每个页面包含正确的 hreflang 标签
- [ ] hreflang 标签包含 x-default 指向英语版本
- [ ] 各语言版本有独立的 canonical URL
