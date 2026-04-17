# JerseyHolic 回滚方案

> **版本**: v1.0  
> **适用环境**: 生产环境 (Production)  
> **紧急程度**: P0 - 系统不可用 / P1 - 核心功能故障 / P2 - 一般问题

---

## 1. 分级回滚策略

### 1.1 决策流程图

```
发现问题
    │
    ▼
评估影响范围 ◄─────────────────────────────┐
    │                                      │
    ├──► P0: 系统完全不可用                │
    │    └──► 立即执行完整回滚             │
    │                                      │
    ├──► P1: 核心功能故障（支付/订单）     │
    │    └──► 选择性回滚或热修复           │
    │                                      │
    └──► P2: 一般功能异常                  │
         └──► 配置回滚或热修复 ────────────┘
```

### 1.2 回滚级别定义

| 级别 | 名称 | 适用场景 | 预计耗时 | 数据影响 |
|------|------|---------|---------|---------|
| L1 | 配置回滚 | 配置错误导致的问题 | 1-5 分钟 | 无 |
| L2 | 代码回滚 | 代码缺陷导致的问题 | 5-15 分钟 | 无 |
| L3 | 数据库回滚 | 数据迁移问题 | 15-60 分钟 | 可能丢失新数据 |
| L4 | 完整回滚 | 系统完全不可用 | 30-120 分钟 | 可能丢失新数据 |
| L5 | 灾难恢复 | 硬件/基础设施故障 | 1-4 小时 | 依赖备份时间点 |

---

## 2. 配置回滚 (L1)

### 2.1 环境变量回滚

```bash
# 场景: .env 配置错误

# 1. 恢复到上一个版本的 .env
cd /var/www/jerseyholic/api
cp .env .env.bak.$(date +%Y%m%d_%H%M%S)
cp .env.backup .env

# 2. 清除配置缓存
php artisan config:clear
php artisan config:cache

# 3. 验证
php artisan tinker --execute="dd(config('app.env'));"
```

### 2.2 Nginx 配置回滚

```bash
# 场景: Nginx 配置错误导致 502/503

# 1. 恢复到上一个版本的配置
cd /etc/nginx/sites-enabled
sudo rm jerseyholic-wildcard.conf
sudo cp /etc/nginx/backup/jerseyholic-wildcard.conf.backup .
sudo ln -s /etc/nginx/sites-available/jerseyholic-wildcard.conf .

# 2. 测试配置
sudo nginx -t

# 3. 重载 Nginx
sudo systemctl reload nginx

# 4. 验证
curl -I https://admin.jerseyholic.xyz
```

### 2.3 功能开关回滚

```bash
# 场景: 新功能导致问题，快速关闭

# 在 .env 中关闭特定功能
echo "CLOAK_ENABLED=false" >> /var/www/jerseyholic/api/.env
echo "SCRAMBLE_ENABLED=false" >> /var/www/jerseyholic/api/.env

# 清除缓存
php artisan config:cache
```

---

## 3. 代码回滚 (L2)

### 3.1 Git 回滚

```bash
cd /var/www/jerseyholic

# 方法1: 回滚到上一个版本（推荐）
git log --oneline -5  # 查看最近提交
git revert HEAD --no-edit  # 撤销最后一次提交
git push origin main

# 方法2: 回滚到指定版本
git log --oneline -20
git revert abc1234 --no-edit  # 撤销指定提交

# 方法3: 强制回滚到指定版本（谨慎使用）
git reset --hard HEAD~1  # 回滚到上一个版本
git push origin main --force  # 强制推送
```

### 3.2 部署回滚

```bash
cd /var/www/jerseyholic

# 1. 保存当前状态（用于后续分析）
git rev-parse HEAD > /var/log/jerseyholic/rollback_from_$(date +%Y%m%d_%H%M%S).txt

# 2. 回滚代码
git fetch origin
git checkout <PREVIOUS_STABLE_COMMIT>

# 3. 重新部署
cd api
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. 前端重新构建
cd ../admin-ui && npm ci && npm run build
cd ../merchant-ui && npm ci && npm run build
cd ../storefront && npm ci && npm run build

# 5. 重启服务
sudo supervisorctl restart all
sudo systemctl reload nginx
```

### 3.3 快速热修复

```bash
# 场景: 小问题，不需要完整回滚

# 1. 创建热修复分支
git checkout -b hotfix/critical-fix

# 2. 修改代码（使用 sed 或手动编辑）
# 示例: 修改配置文件
sed -i 's/OLD_VALUE/NEW_VALUE/g' api/config/payment.php

# 3. 提交并部署
git add .
git commit -m "hotfix: 紧急修复 xxx 问题"
git push origin hotfix/critical-fix

# 4. 合并到 main
git checkout main
git merge hotfix/critical-fix --no-ff
git push origin main

# 5. 部署
# ... 执行部署流程
```

---

## 4. 数据库回滚 (L3)

### 4.1 迁移回滚

```bash
cd /var/www/jerseyholic/api

# 1. 查看迁移状态
php artisan migrate:status --database=central

# 2. 回滚最后一次迁移批次
php artisan migrate:rollback --database=central --force

# 3. 回滚指定步数（回滚最近 3 个批次）
php artisan migrate:rollback --database=central --step=3 --force

# 4. 回滚到指定迁移
php artisan migrate:rollback --database=central \
  --path=database/migrations/central/2026_04_17_001500_create_store_product_configs_table.php \
  --force
```

### 4.2 数据恢复

```bash
# 场景: 数据损坏或误删除

# 1. 从备份恢复
mysql -u root -p jerseyholic_central < backup_central_20260417_120000.sql

# 2. 恢复特定表
mysql -u root -p jerseyholic_central -e "
DROP TABLE IF EXISTS jh_payment_accounts;
"
mysql -u root -p jerseyholic_central < <(sed -n '/CREATE TABLE.*jh_payment_accounts/,/^-- Table structure/p' backup_central_20260417_120000.sql)

# 3. 恢复租户数据库
for db in $(mysql -u root -p -e "SHOW DATABASES LIKE 'store_%'" -s --skip-column-names); do
    backup_file="backup_${db}_20260417_120000.sql"
    if [ -f "$backup_file" ]; then
        mysql -u root -p -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db;"
        mysql -u root -p "$db" < "$backup_file"
    fi
done
```

### 4.3 租户数据库回滚

```bash
# 场景: 特定租户数据问题

# 1. 删除有问题的租户数据库
mysql -u root -p -e "DROP DATABASE IF EXISTS store_123;"

# 2. 从备份恢复
mysql -u root -p -e "CREATE DATABASE store_123;"
mysql -u root -p store_123 < backup_store_123_20260417_120000.sql

# 3. 或者重新创建租户（数据会丢失，谨慎使用）
php artisan tinker --execute="
\$tenant = App\Models\Central\Store::find('123');
if (\$tenant) {
    \$tenant->delete();
    // 重新创建...
}
"
```

---

## 5. 完整回滚 (L4)

### 5.1 完整回滚检查清单

```bash
#!/bin/bash
# full-rollback.sh - 完整回滚脚本

set -e

ROLLBACK_VERSION="${1:-HEAD~1}"
BACKUP_DIR="/var/backups/jerseyholic/$(date +%Y%m%d_%H%M%S)"

echo "======================================"
echo "JerseyHolic Full Rollback"
echo "Target Version: $ROLLBACK_VERSION"
echo "======================================"

# 1. 启用维护模式
echo "[1/8] 启用维护模式..."
cd /var/www/jerseyholic/api
php artisan down --message="系统维护中，请稍后再试" --retry=60

# 2. 停止所有服务
echo "[2/8] 停止服务..."
sudo supervisorctl stop all

# 3. 备份当前状态
echo "[3/8] 备份当前状态..."
mkdir -p "$BACKUP_DIR"
git rev-parse HEAD > "$BACKUP_DIR/git_commit.txt"
mysqldump -u root -p${DB_PASSWORD} jerseyholic_central > "$BACKUP_DIR/central_current.sql"
cp .env "$BACKUP_DIR/env.txt"

# 4. 数据库回滚
echo "[4/8] 回滚数据库..."
# 恢复到上一个稳定版本的数据库备份
mysql -u root -p${DB_PASSWORD} jerseyholic_central < /var/backups/jerseyholic/STABLE/central_stable.sql

# 5. 代码回滚
echo "[5/8] 回滚代码..."
cd /var/www/jerseyholic
git fetch origin
git checkout "$ROLLBACK_VERSION"

# 6. 重新部署
echo "[6/8] 重新部署..."
cd api
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. 重启服务
echo "[7/8] 重启服务..."
sudo supervisorctl start all
sudo systemctl reload nginx

# 8. 退出维护模式
echo "[8/8] 退出维护模式..."
php artisan up

echo "======================================"
echo "Rollback completed!"
echo "Backup saved to: $BACKUP_DIR"
echo "======================================"
```

### 5.2 蓝绿部署回滚

如果使用蓝绿部署策略：

```bash
# 场景: 新版本在 Green 环境，需要切回 Blue

# 1. 检查 Green 环境状态
curl -s https://green.jerseyholic.xyz/api/health

# 2. 切换负载均衡器到 Blue 环境
sudo sed -i 's/green/blue/g' /etc/nginx/upstream.conf
sudo nginx -t && sudo systemctl reload nginx

# 3. 验证 Blue 环境
curl -s https://admin.jerseyholic.xyz/api/health

# 4. 停止 Green 环境（可选）
sudo systemctl stop jerseyholic-green
```

---

## 6. 各 Phase 回滚步骤

### 6.1 Phase M1: 多租户基础设施回滚

```bash
# 影响范围: tenants, domains, stores 表

# 1. 回滚迁移
php artisan migrate:rollback --path=database/migrations/central \
  --database=central \
  --step=6  # 回滚 6 个基础表迁移

# 2. 清理创建的数据库
mysql -u root -p -e "
SELECT CONCAT('DROP DATABASE IF EXISTS ', schema_name, ';')
FROM information_schema.schemata
WHERE schema_name LIKE 'store_%' OR schema_name LIKE 'jerseyholic_merchant_%';
" | mysql -u root -p

# 3. 验证
php artisan migrate:status --database=central
```

### 6.2 Phase M2: 商户与站点管理回滚

```bash
# 影响范围: merchants, merchant_users, api_keys

# 1. 回滚迁移
php artisan migrate:rollback --database=central --step=4

# 2. 数据清理（如果需要）
mysql -u root -p jerseyholic_central -e "
DELETE FROM jh_merchants WHERE created_at > '2026-04-17 00:00:00';
DELETE FROM jh_merchant_users WHERE created_at > '2026-04-17 00:00:00';
"

# 3. 验证
mysql -u root -p jerseyholic_central -e "SELECT COUNT(*) FROM jh_merchants;"
```

### 6.3 Phase M3: 支付系统回滚

```bash
# 影响范围: payment_accounts, settlements, risk_scores

# ⚠️ 警告: 支付数据敏感，回滚前务必确认

# 1. 备份当前支付数据
mysqldump -u root -p jerseyholic_central jh_payment_accounts jh_settlement_records \
  > backup_payment_$(date +%Y%m%d_%H%M%S).sql

# 2. 回滚迁移（谨慎操作）
php artisan migrate:rollback --database=central --step=10

# 3. 如果需要恢复数据
mysql -u root -p jerseyholic_central < backup_payment_xxx.sql
```

### 6.4 Phase M4: 商品系统回滚

```bash
# 影响范围: product_categories, sync_rules, master_products

# 1. 回滚 Central 表
php artisan migrate:rollback --database=central --step=6

# 2. 回滚 Merchant 表
php artisan migrate:rollback --path=database/migrations/merchant \
  --database=merchant \
  --step=4

# 3. 清理同步日志
mysql -u root -p jerseyholic_central -e "TRUNCATE TABLE jh_product_sync_logs;"
```

---

## 7. Nginx 配置切换

### 7.1 切换到旧版本配置

```bash
# 1. 备份当前配置
sudo cp /etc/nginx/sites-available/jerseyholic-wildcard.conf \
  /etc/nginx/backup/jerseyholic-wildcard.conf.$(date +%Y%m%d_%H%M%S)

# 2. 切换到旧版本配置
sudo cp /etc/nginx/backup/jerseyholic-wildcard.conf.stable \
  /etc/nginx/sites-available/jerseyholic-wildcard.conf

# 3. 测试并重载
sudo nginx -t
sudo systemctl reload nginx

# 4. 验证
curl -I https://admin.jerseyholic.xyz
```

### 7.2 快速禁用问题站点

```bash
# 场景: 特定租户站点出现问题

# 1. 创建临时禁止配置
cat > /etc/nginx/sites-available/blocked-store.conf << 'EOF'
server {
    listen 443 ssl http2;
    server_name problematic-store.jerseyholic.xyz;
    
    ssl_certificate /etc/letsencrypt/live/jerseyholic.xyz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/jerseyholic.xyz/privkey.pem;
    
    location / {
        return 503 "Service temporarily unavailable";
    }
}
EOF

# 2. 启用配置
sudo ln -sf /etc/nginx/sites-available/blocked-store.conf \
  /etc/nginx/sites-enabled/problematic-store.conf

# 3. 重载 Nginx
sudo nginx -t && sudo systemctl reload nginx
```

---

## 8. 回滚验证清单

### 8.1 基础验证

```bash
#!/bin/bash
# rollback-verification.sh

echo "=== Rollback Verification ==="

# 1. 应用状态
echo "[1/6] Checking application status..."
cd /var/www/jerseyholic/api
php artisan tinker --execute="dd(app()->isDownForMaintenance() ? 'DOWN' : 'UP');"

# 2. 数据库连接
echo "[2/6] Checking database connections..."
php artisan tinker --execute="
try {
    DB::connection('central')->select('SELECT 1');
    echo 'Central DB: OK\n';
} catch (\Exception \$e) {
    echo 'Central DB: FAILED\n';
}
"

# 3. API 响应
echo "[3/6] Checking API response..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://admin.jerseyholic.xyz/api/health)
if [ "$HTTP_CODE" = "200" ]; then
    echo "API Health: OK (200)"
else
    echo "API Health: FAILED ($HTTP_CODE)"
fi

# 4. 队列状态
echo "[4/6] Checking queue workers..."
sudo supervisorctl status | grep jh-

# 5. 关键表检查
echo "[5/6] Checking critical tables..."
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} -e "
USE jerseyholic_central;
SELECT 
    'merchants' as table_name, COUNT(*) as count FROM jh_merchants
UNION ALL
SELECT 'stores', COUNT(*) FROM jh_stores
UNION ALL
SELECT 'payment_accounts', COUNT(*) FROM jh_payment_accounts;
"

# 6. 日志检查
echo "[6/6] Checking recent errors..."
tail -20 /var/www/jerseyholic/api/storage/logs/laravel.log | grep ERROR || echo "No recent errors"

echo "=== Verification Complete ==="
```

### 8.2 功能验证

```bash
# 1. 登录验证
curl -X POST https://admin.jerseyholic.xyz/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@jerseyholic.xyz","password":"${ADMIN_PASSWORD}"}'

# 2. 商户列表
curl -H "Authorization: Bearer ${TOKEN}" \
  https://admin.jerseyholic.xyz/api/v1/admin/merchants

# 3. 支付账户列表
curl -H "Authorization: Bearer ${TOKEN}" \
  https://admin.jerseyholic.xyz/api/v1/admin/payment-accounts
```

---

## 9. 紧急联系人和决策流程

### 9.1 联系人列表

| 角色 | 姓名 | 电话 | 邮箱 | 职责 |
|------|------|------|------|------|
| 技术负责人 | - | - | - | 回滚决策审批 |
| 运维工程师 | - | - | - | 执行回滚操作 |
| DBA | - | - | - | 数据库回滚支持 |
| 产品经理 | - | - | - | 业务影响评估 |
| 客服负责人 | - | - | - | 用户通知协调 |

### 9.2 决策流程

```
┌─────────────────────────────────────────────────────────────┐
│                      问题发现                                │
│  • 监控系统告警 / 用户反馈 / 人工发现                         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    影响评估 (5分钟内)                        │
│  • 受影响用户数                                             │
│  • 受影响功能范围                                           │
│  • 是否为支付/订单核心流程                                   │
└─────────────────────────────────────────────────────────────┘
                              │
            ┌─────────────────┼─────────────────┐
            ▼                 ▼                 ▼
      ┌─────────┐      ┌─────────┐      ┌─────────┐
      │   P0    │      │   P1    │      │   P2    │
      │ 系统不可用│      │核心功能故障│      │一般问题  │
      └────┬────┘      └────┬────┘      └────┬────┘
           │                │                │
           ▼                ▼                ▼
      ┌─────────┐      ┌─────────┐      ┌─────────┐
      │立即回滚  │      │15分钟内 │      │热修复或  │
      │无需审批  │      │决策窗口 │      │计划修复  │
      │通知TL    │      │需审批   │      │          │
      └─────────┘      └─────────┘      └─────────┘
```

### 9.3 通知模板

#### P0 紧急通知

```
【紧急】JerseyHolic 生产环境故障

时间: 2026-04-17 12:00 UTC+8
影响: 系统完全不可用
原因: [待调查]
行动: 正在执行完整回滚
预计恢复: 30分钟内

联系人: [运维工程师] [电话]
```

#### 回滚完成通知

```
【已恢复】JerseyHolic 生产环境

时间: 2026-04-17 12:25 UTC+8
状态: 服务已恢复
回滚版本: abc1234 → def5678
影响数据: [说明是否有数据丢失]
后续行动: [根因分析/修复计划]
```

---

## 10. 回滚后行动

### 10.1 立即行动

- [ ] 验证所有核心功能正常
- [ ] 检查数据一致性
- [ ] 确认无用户投诉
- [ ] 更新状态页面
- [ ] 通知相关团队

### 10.2 24小时内

- [ ] 完成故障根因分析 (RCA)
- [ ] 编写事后复盘文档
- [ ] 制定预防措施
- [ ] 更新监控告警规则

### 10.3 一周内

- [ ] 修复代码问题
- [ ] 完善自动化测试
- [ ] 更新部署流程
- [ ] 团队复盘会议

---

## 附录: 快速参考卡

### 紧急回滚命令

```bash
# 立即回滚（复制即用）
cd /var/www/jerseyholic/api
php artisan down --message="系统维护中"
sudo supervisorctl stop all
cd .. && git checkout HEAD~1
cd api && composer install --no-dev
php artisan config:cache && php artisan route:cache
sudo supervisorctl start all
php artisan up
```

### 关键路径

| 文件/目录 | 路径 |
|----------|------|
| 应用代码 | `/var/www/jerseyholic` |
| API 目录 | `/var/www/jerseyholic/api` |
| 环境配置 | `/var/www/jerseyholic/api/.env` |
| Nginx 配置 | `/etc/nginx/sites-available/` |
| 日志文件 | `/var/www/jerseyholic/api/storage/logs/` |
| 数据库备份 | `/var/backups/jerseyholic/` |
| Supervisor | `/etc/supervisor/conf.d/` |

---

**最后更新**: 2026-04-17  
**文档版本**: v1.0  
**审核人**: _______________
