# JerseyHolic New System

Cross-border e-commerce unified system built with Laravel 10+, Vue 3, and Nuxt 3.

## Project Structure

```
jerseyholic-new/
├── api/              # Laravel 10+ Backend API
│   ├── app/          # Application code (Models, Services, Controllers, Enums)
│   ├── bootstrap/    # Laravel bootstrap
│   ├── config/       # Configuration files
│   ├── database/     # Migrations, Seeders, Factories
│   ├── routes/       # API routes (admin, buyer, merchant, webhook)
│   ├── tests/        # Unit and Feature tests
│   ├── storage/      # Logs, cache, sessions
│   ├── public/       # Web entry point
│   ├── artisan       # Laravel CLI
│   ├── composer.json # PHP dependencies
│   └── .env          # Environment configuration
├── admin-ui/         # Vue 3 + Element Plus + Vite Admin Panel
│   ├── src/          # Source code (pages, layouts, stores, api)
│   ├── package.json  # Node dependencies
│   └── vite.config.ts
├── storefront/       # Nuxt 3 + TailwindCSS Buyer Storefront (SSR)
│   ├── pages/        # SSR pages
│   ├── i18n/         # 16 languages
│   ├── package.json  # Node dependencies
│   └── nuxt.config.ts
└── docs/             # Project documentation
    ├── DOCUMENTATION-GUIDE.md  # 文档体系指南
    ├── plan/         # 战略规划（项目计划、阶段任务、进度报告）
    ├── prd/          # 需求业务（功能清单、业务规则、模块 PRD）
    ├── architecture/ # 设计架构（系统架构、数据库、API、安全）
    ├── standards/    # 编码规范（开发规范、后端/前端/数据库指南）
    ├── testing/      # 测试质量（测试策略、测试用例）
    ├── deployment/   # 运维部署（环境搭建、部署、故障排查）
    ├── user-manual/  # 用户手册（管理员、买家）
    ├── api-spec.json # OpenAPI 规范（自动生成）
    └── migration/    # 数据迁移（预留）
```

## Quick Start

### Prerequisites
- PHP >= 8.1
- Composer
- Node.js >= 18
- MySQL 8.0+
- Redis

### Backend (Laravel API)
```bash
cd api
composer install
cp .env.example .env   # or edit .env directly
php artisan key:generate
php artisan migrate
php artisan serve --port=8000
```

### Admin Panel (Vue 3)
```bash
cd admin-ui
npm install
npm run dev    # http://localhost:3100
```

### Storefront (Nuxt 3)
```bash
cd storefront
npm install
npm run dev    # http://localhost:3000
```

## Related Systems

- **Old OpenCart**: `../a.jerseyholic.xyz/` (read-only reference)
- **Old ThinkPHP**: `../jerseyholic.xyz/` (read-only reference)

## Tech Stack

- **Backend**: Laravel 10+, PHP 8.1+, Sanctum (Auth), Redis (Cache/Queue)
- **Admin UI**: Vue 3.4, TypeScript, Element Plus 2.6, Vite 5, Pinia
- **Storefront**: Nuxt 3.11, TailwindCSS 3.4, @nuxtjs/i18n (16 languages + RTL)
- **Database**: MySQL 8.0+ (jh_ prefix, InnoDB, utf8mb4_unicode_ci)

## Documentation

项目文档体系详见 [`docs/DOCUMENTATION-GUIDE.md`](docs/DOCUMENTATION-GUIDE.md)，涵盖：

| 文档类别 | 路径 | 说明 |
|---------|------|------|
| 战略规划 | `docs/plan/` | 项目计划、阶段任务、进度报告 |
| 需求业务 | `docs/prd/` | 功能清单、业务规则、模块 PRD |
| 设计架构 | `docs/architecture/` | 系统架构、数据库设计、API 设计 |
| 编码规范 | `docs/standards/` | 开发规范、编码指南 |
| 测试质量 | `docs/testing/` | 测试策略、测试用例 |
| 运维部署 | `docs/deployment/` | 环境搭建、部署指南、故障排查 |
| 用户手册 | `docs/user-manual/` | 管理员和买家操作指南 |
| API 规范 | `docs/api-spec.json` | OpenAPI 自动生成文档 |
