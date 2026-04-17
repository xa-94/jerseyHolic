# JerseyHolic 数据库迁移执行顺序

> **版本**: v1.0  
> **适用环境**: 生产环境 (Production)  
> **执行前必读**: 请确保已备份数据库

---

## 执行前准备

### 1. 备份现有数据

```bash
# 备份 Central 数据库
mysqldump -u root -p jerseyholic_central > backup_central_$(date +%Y%m%d_%H%M%S).sql

# 备份所有租户数据库
for db in $(mysql -u root -p -e "SHOW DATABASES LIKE 'store_%'" -s --skip-column-names); do
    mysqldump -u root -p "$db" > backup_${db}_$(date +%Y%m%d_%H%M%S).sql
done

# 备份所有商户数据库
for db in $(mysql -u root -p -e "SHOW DATABASES LIKE 'jerseyholic_merchant_%'" -s --skip-column-names); do
    mysqldump -u root -p "$db" > backup_${db}_$(date +%Y%m%d_%H%M%S).sql
done
```

### 2. 进入维护模式

```bash
cd /var/www/jerseyholic/api

# 启用维护模式
php artisan down --message="系统升级中，请稍后再试" --retry=60

# 检查维护状态
php artisan tinker --execute="dd(app()->isDownForMaintenance());"
```

### 3. 停止 Queue Worker

```bash
sudo supervisorctl stop all
```

---

## Phase 1: Central DB 基础表

### 1.1 迁移文件列表

| 顺序 | 迁移文件 | 说明 |
|------|---------|------|
| 1 | `2026_04_16_099900_create_admins_table.php` | 平台管理员表 |
| 2 | `2026_04_16_100000_create_merchants_table.php` | 商户主表 |
| 3 | `2026_04_16_100100_create_stores_table.php` | 租户站点表（stancl/tenancy） |
| 4 | `2026_04_16_100200_create_domains_table.php` | 域名映射表 |
| 5 | `2026_04_16_100300_create_merchant_users_table.php` | 商户用户表 |
| 6 | `2026_04_16_100400_create_merchant_api_keys_table.php` | API 密钥表 |

### 1.2 执行命令

```bash
cd /var/www/jerseyholic/api

# 执行 Central 基础表迁移
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

# 查看迁移状态
php artisan migrate:status --database=central
```

### 1.3 验证方法

```bash
# 验证表结构
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SHOW TABLES LIKE 'jh_%';
" | grep -E "(admins|merchants|stores|domains|merchant_users|merchant_api_keys)"

# 验证表结构详情
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
DESCRIBE jh_merchants;
DESCRIBE jh_stores;
DESCRIBE jh_domains;
"

# 验证迁移记录
php artisan tinker --execute="
\$migrations = DB::connection('central')
  ->table('migrations')
  ->where('batch', 1)
  ->pluck('migration');
dd(\$migrations->toArray());
"
```

### 1.4 预期输出

```
jh_admins
jh_merchants
jh_stores
jh_domains
jh_merchant_users
jh_merchant_api_keys
```

---

## Phase 2: Central DB 支付表

### 2.1 迁移文件列表

| 顺序 | 迁移文件 | 说明 |
|------|---------|------|
| 7 | `2026_04_16_100500_create_payment_accounts_table.php` | 支付账户表 |
| 8 | `2026_04_16_100600_create_store_payment_accounts_table.php` | 站点支付账户关联表 |
| 9 | `2026_04_16_100700_create_settlement_records_table.php` | 结算记录表 |
| 10 | `2026_04_16_100800_create_settlement_details_table.php` | 结算明细表 |
| 11 | `2026_04_16_100900_create_merchant_risk_scores_table.php` | 商户风险评分表 |
| 12 | `2026_04_16_101000_create_fund_flow_logs_table.php` | 资金流水表 |
| 13 | `2026_04_17_000200_create_payment_account_groups_table.php` | 支付账户组表 |
| 14 | `2026_04_17_000300_add_m3_fields_to_payment_accounts_table.php` | 支付账户扩展字段 |
| 15 | `2026_04_17_000400_create_merchant_payment_group_mappings_table.php` | 商户支付组映射表 |
| 16 | `2026_04_17_000900_create_settlement_refund_adjustments_table.php` | 结算退款调整表 |

### 2.2 执行命令

```bash
cd /var/www/jerseyholic/api

# 继续执行支付相关迁移（--step 会创建新的 batch）
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

# 查看迁移状态
php artisan migrate:status --database=central
```

### 2.3 验证方法

```bash
# 验证支付相关表
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SHOW TABLES LIKE 'jh_payment%';
SHOW TABLES LIKE 'jh_settlement%';
SHOW TABLES LIKE 'jh_fund%';
"

# 验证支付账户表结构
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
DESCRIBE jh_payment_accounts;
"

# 关键字段检查
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'jerseyholic_central'
AND TABLE_NAME = 'jh_payment_accounts'
AND COLUMN_NAME IN ('type', 'status', 'credentials', 'group_id');
"
```

---

## Phase 3: Central DB 商品表

### 3.1 迁移文件列表

| 顺序 | 迁移文件 | 说明 |
|------|---------|------|
| 17 | `2026_04_16_101100_create_product_sync_logs_table.php` | 商品同步日志表 |
| 18 | `2026_04_17_001100_create_product_categories_l1_table.php` | 一级品类表 |
| 19 | `2026_04_17_001200_create_product_categories_l2_table.php` | 二级品类表 |
| 20 | `2026_04_17_001300_create_category_safe_names_table.php` | 品类安全名称表 |
| 21 | `2026_04_17_001400_create_sensitive_brands_table.php` | 敏感品牌表 |
| 22 | `2026_04_17_001500_create_store_product_configs_table.php` | 站点商品配置表 |

### 3.2 执行命令

```bash
cd /var/www/jerseyholic/api

# 执行商品相关迁移
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step
```

### 3.3 验证方法

```bash
# 验证商品相关表
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SHOW TABLES LIKE 'jh_product%';
SHOW TABLES LIKE 'jh_category%';
SHOW TABLES LIKE 'jh_sensitive%';
SHOW TABLES LIKE 'jh_store_product%';
"

# 验证品类表外键关系
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'jerseyholic_central'
AND TABLE_NAME IN ('jh_product_categories_l2', 'jh_category_safe_names')
AND REFERENCED_TABLE_NAME IS NOT NULL;
"
```

---

## Phase 4: Central DB 风控与运营表

### 4.1 迁移文件列表

| 顺序 | 迁移文件 | 说明 |
|------|---------|------|
| 23 | `2026_04_16_101200_create_blacklist_table.php` | 黑名单表 |
| 24 | `2026_04_16_101200_create_blacklist_table.php` | 黑名单表（M3 扩展） |
| 25 | `2026_04_17_000500_create_paypal_safe_descriptions_table.php` | PayPal 安全描述表 |
| 26 | `2026_04_17_000600_rebuild_blacklist_table_for_m3.php` | 黑名单表重建（M3） |
| 27 | `2026_04_17_000700_create_commission_rules_table.php` | 佣金规则表 |
| 28 | `2026_04_17_000800_create_notifications_table.php` | 通知表 |
| 29 | `2026_04_17_000100_create_merchant_audit_logs_table.php` | 商户审计日志表 |

### 4.2 执行命令

```bash
cd /var/www/jerseyholic/api

# 执行风控与运营相关迁移
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step
```

### 4.3 验证方法

```bash
# 验证风控相关表
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SHOW TABLES LIKE 'jh_blacklist%';
SHOW TABLES LIKE 'jh_commission%';
SHOW TABLES LIKE 'jh_notifications%';
SHOW TABLES LIKE 'jh_merchant_audit%';
"
```

---

## Phase 5: Tenant DB 模板迁移

### 5.1 迁移文件列表

| 顺序 | 迁移文件 | 说明 |
|------|---------|------|
| 1 | `2026_04_17_000001_create_languages_table.php` | 语言表 |
| 2 | `2026_04_17_000002_create_currencies_table.php` | 货币表 |
| 3 | `2026_04_17_000003_create_countries_table.php` | 国家表 |
| 4 | `2026_04_17_000004_create_geo_zones_table.php` | 地理区域表 |
| 5 | `2026_04_17_000005_create_settings_table.php` | 设置表 |
| 6 | `2026_04_17_000006_create_customers_table.php` | 客户表 |
| 7 | `2026_04_17_000007_create_customer_addresses_table.php` | 客户地址表 |
| 8 | `2026_04_17_000008_create_categories_table.php` | 品类表 |
| 9 | `2026_04_17_000009_create_products_table.php` | 商品表 |
| 10 | `2026_04_17_000010_create_product_related_tables.php` | 商品关联表 |
| 11 | `2026_04_17_000011_create_mapping_tables.php` | 映射表 |
| 12 | `2026_04_17_000012_create_orders_table.php` | 订单表 |
| 13 | `2026_04_17_000013_create_order_related_tables.php` | 订单关联表 |
| 14 | `2026_04_17_000014_create_payment_records_table.php` | 支付记录表 |
| 15 | `2026_04_17_000015_create_shipping_tables.php` | 物流表 |
| 16 | `2026_04_17_000016_create_marketing_tables.php` | 营销表 |
| 17 | `2026_04_17_000017_create_fb_pixel_tables.php` | Facebook Pixel 表 |
| 18 | `2026_04_17_000018_create_content_tables.php` | 内容表 |
| 19 | `2026_04_17_000019_create_risk_orders_table.php` | 风险订单表 |

### 5.2 执行命令

```bash
cd /var/www/jerseyholic/api

# 注意：租户迁移通常由创建租户时自动执行
# 这里仅用于初始化模板验证

# 方法1: 手动执行到模板数据库（用于验证）
php artisan migrate --path=database/migrations/tenant \
  --database=tenant \
  --force

# 方法2: 创建测试租户自动触发迁移
php artisan tinker --execute="
\$tenant = App\Models\Central\Store::create([
    'id' => 'test_migration',
    'merchant_id' => 1,
    'name' => 'Migration Test Store',
]);
\$tenant->domains()->create(['domain' => 'test-migration.jerseyholic.xyz']);
dd('Tenant created, migration should run automatically');
"
```

### 5.3 验证方法

```bash
# 验证租户数据库创建
mysql -u ${DB_USERNAME} -p -e "SHOW DATABASES LIKE 'store_%';"

# 验证租户数据库表结构
mysql -u ${DB_USERNAME} -p -e "
USE store_test_migration;
SHOW TABLES;
"

# 验证核心表
mysql -u ${DB_USERNAME} -p -e "
USE store_test_migration;
SELECT COUNT(*) as table_count FROM information_schema.tables 
WHERE table_schema = 'store_test_migration';
"

# 预期输出: 约 30+ 张表
```

### 5.4 租户迁移自动执行机制

当通过 API 创建新租户时，stancl/tenancy 会自动执行：

```php
// 内部流程
1. Store::create() 触发 TenantCreated 事件
2. DatabaseTenancyBootstrapper 创建数据库 store_{id}
3. 自动执行 database/migrations/tenant/ 下的所有迁移
4. 执行 Database\Seeders\TenantDatabaseSeeder
```

---

## Phase 6: Merchant DB DDL

### 6.1 迁移文件列表

| 顺序 | 迁移文件 | 说明 |
|------|---------|------|
| 1 | `2026_04_17_100001_create_master_products_table.php` | 主商品表 |
| 2 | `2026_04_17_100002_create_master_product_translations_table.php` | 主商品翻译表 |
| 3 | `2026_04_17_100003_create_sync_rules_table.php` | 同步规则表 |
| 4 | `2026_04_17_100004_add_sync_meta_to_sync_rules_table.php` | 同步规则扩展 |

### 6.2 执行机制

商户数据库由 `MerchantDatabaseService` 在创建商户时自动创建：

```php
// 触发条件
POST /api/v1/admin/merchants  ->  MerchantController@store
                              ->  MerchantDatabaseService::createDatabase($merchantId)
                              ->  执行 database/migrations/merchant/ 迁移
```

### 6.3 手动验证

```bash
# 创建测试商户触发数据库创建
curl -X POST https://admin.jerseyholic.xyz/api/v1/admin/merchants \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Migration Test Merchant",
    "email": "test-migration@example.com",
    "phone": "+1234567890"
  }'

# 验证商户数据库创建
mysql -u ${DB_USERNAME} -p -e "SHOW DATABASES LIKE 'jerseyholic_merchant_%';"

# 验证商户数据库表
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_merchant_1;
SHOW TABLES;
"

# 预期表: master_products, master_product_translations, sync_rules
```

---

## 完整迁移脚本

### 一键执行脚本

```bash
#!/bin/bash
# migrate-all.sh - 完整迁移脚本

set -e  # 遇到错误立即退出

echo "======================================"
echo "JerseyHolic Database Migration Script"
echo "======================================"

# 配置
API_PATH="/var/www/jerseyholic/api"
DB_CENTRAL="jerseyholic_central"
BACKUP_DIR="/var/backups/jerseyholic/$(date +%Y%m%d_%H%M%S)"

# 创建备份目录
mkdir -p "$BACKUP_DIR"

echo "[1/6] 备份数据库..."
mysqldump -u root -p${DB_PASSWORD} $DB_CENTRAL > "$BACKUP_DIR/central_before_migration.sql"
echo "备份完成: $BACKUP_DIR"

echo "[2/6] 进入维护模式..."
cd "$API_PATH"
php artisan down --message="数据库升级中" --retry=60

echo "[3/6] 停止 Queue Worker..."
sudo supervisorctl stop all || true

echo "[4/6] 执行 Central DB 迁移..."
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

echo "[5/6] 验证迁移结果..."
php artisan migrate:status --database=central

echo "[6/6] 恢复服务..."
php artisan up
sudo supervisorctl start all

echo "======================================"
echo "Migration completed successfully!"
echo "Backup location: $BACKUP_DIR"
echo "======================================"
```

### 迁移状态检查脚本

```bash
#!/bin/bash
# check-migration-status.sh

cd /var/www/jerseyholic/api

echo "=== Central DB Migration Status ==="
php artisan migrate:status --database=central

echo ""
echo "=== Pending Migrations ==="
php artisan migrate:status --database=central | grep Pending || echo "All migrations completed!"

echo ""
echo "=== Database Table Count ==="
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} -e "
SELECT 
    'Central' as db_type,
    COUNT(*) as table_count
FROM information_schema.tables 
WHERE table_schema = 'jerseyholic_central'
UNION ALL
SELECT 
    'Tenant Template' as db_type,
    COUNT(*) as table_count
FROM information_schema.tables 
WHERE table_schema LIKE 'store_%'
LIMIT 1;
"
```

---

## 迁移问题排查

### 常见问题 1: 迁移文件冲突

```bash
# 症状: Migration already exists 错误

# 解决: 查看迁移表状态
php artisan migrate:status --database=central

# 如果迁移已执行但文件缺失，手动标记
php artisan tinker --execute="
DB::connection('central')->table('migrations')->insert([
    'migration' => '2026_04_17_001500_create_store_product_configs_table',
    'batch' => 5
]);
"
```

### 常见问题 2: 外键约束失败

```bash
# 症状: Foreign key constraint fails

# 解决: 检查依赖表是否存在
mysql -u ${DB_USERNAME} -p -e "
USE jerseyholic_central;
SHOW TABLES LIKE 'jh_payment%';
"

# 临时禁用外键检查（仅用于排查）
mysql -u ${DB_USERNAME} -p -e "
SET FOREIGN_KEY_CHECKS = 0;
-- 执行修复操作
SET FOREIGN_KEY_CHECKS = 1;
"
```

### 常见问题 3: 租户迁移失败

```bash
# 症状: 创建租户时数据库未创建

# 检查数据库权限
mysql -u root -p -e "
SHOW GRANTS FOR '${DB_USERNAME}'@'%';
"

# 确保有 CREATE DATABASE 权限
mysql -u root -p -e "
GRANT CREATE, DROP ON *.* TO '${DB_USERNAME}'@'%';
FLUSH PRIVILEGES;
"
```

---

## 迁移后验证清单

- [ ] Central DB 所有表已创建（约 25+ 张）
- [ ] 租户模板数据库可正常创建
- [ ] 商户数据库可正常创建
- [ ] 外键约束正确建立
- [ ] 索引已正确创建
- [ ] 迁移表记录完整
- [ ] Queue Worker 已重启
- [ ] 应用已退出维护模式

---

**执行时间**: _______________  
**执行人**: _______________  
**验证人**: _______________
