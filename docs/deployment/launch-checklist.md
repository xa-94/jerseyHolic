# JerseyHolic 上线检查清单

> **版本**: v1.0  
> **适用环境**: 生产环境 (Production)  
> **执行前必读**: 每个检查项必须由两人核对确认（执行人 + 审核人）

---

## 使用说明

- `[ ]` 表示待完成
- `[x]` 表示已完成
- `[!]` 表示已跳过（需注明原因）
- 每项完成后填写 **执行人** 和 **完成时间**

---

## 一、环境准备检查

### 1.1 基础软件版本

| 组件 | 要求版本 | 检查命令 | 状态 | 执行人 | 时间 |
|------|---------|---------|------|-------|------|
| PHP | 8.1+ | `php -v` | `[ ]` | | |
| MySQL | 8.0+ | `mysql --version` | `[ ]` | | |
| Redis | 6.2+ | `redis-server --version` | `[ ]` | | |
| Node.js | 18+ | `node -v` | `[ ]` | | |
| npm | 9+ | `npm -v` | `[ ]` | | |
| Composer | 2.x | `composer --version` | `[ ]` | | |
| Nginx | 1.20+ | `nginx -v` | `[ ]` | | |
| Supervisor | 4.x | `supervisord --version` | `[ ]` | | |

```bash
# 批量检查版本
php -v | head -1
mysql --version
redis-server --version
node -v
npm -v
composer --version
nginx -v
supervisord --version
```

### 1.2 PHP 扩展

```bash
# 检查必要扩展
php -m | grep -E "(pdo|pdo_mysql|redis|bcmath|json|mbstring|openssl|tokenizer|xml|ctype|fileinfo|gd|intl|zip)"
```

| 扩展 | 状态 |
|------|------|
| pdo | `[ ]` |
| pdo_mysql | `[ ]` |
| redis / predis | `[ ]` |
| bcmath | `[ ]` |
| json | `[ ]` |
| mbstring | `[ ]` |
| openssl | `[ ]` |
| tokenizer | `[ ]` |
| xml | `[ ]` |
| gd | `[ ]` |
| intl | `[ ]` |
| zip | `[ ]` |

### 1.3 系统资源检查

```bash
# 磁盘空间（至少 20GB 可用）
df -h /

# 内存（至少 4GB）
free -h

# CPU 核数
nproc

# 文件描述符限制
ulimit -n

# 系统时区（必须与应用配置一致）
timedatectl status
```

- `[ ]` 磁盘可用空间 ≥ 20GB
- `[ ]` 内存 ≥ 4GB
- `[ ]` CPU ≥ 2 核
- `[ ]` 文件描述符 ≥ 65535（`ulimit -n 65535`）
- `[ ]` 系统时区已设置为 `Asia/Shanghai` 或与业务一致

---

## 二、数据库迁移步骤

> **参考文档**: `docs/deployment/database-migration-order.md`  
> **执行前**: 必须完成数据库备份

### 2.0 迁移前准备

```bash
BACKUP_DIR="/var/backups/jerseyholic/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# 备份 Central 数据库
mysqldump -u root -p jerseyholic_central > "$BACKUP_DIR/central_pre_launch.sql"
echo "备份完成: $BACKUP_DIR/central_pre_launch.sql"

# 进入维护模式
cd /var/www/jerseyholic/api
php artisan down --message="系统升级中，请稍后再试" --retry=60

# 停止 Queue Worker
sudo supervisorctl stop all
```

- `[ ]` Central 数据库已备份
- `[ ]` 应用已进入维护模式
- `[ ]` Queue Worker 已停止

### 2.1 第一阶段：Central DB 基础表（6个表）

```bash
cd /var/www/jerseyholic/api

php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

# 验证
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} jerseyholic_central \
  -e "SHOW TABLES LIKE 'jh_%';" | grep -E "(admins|merchants|stores|domains|merchant_users|merchant_api_keys)"
```

- `[ ]` jh_admins 表已创建
- `[ ]` jh_merchants 表已创建
- `[ ]` jh_stores 表已创建
- `[ ]` jh_domains 表已创建
- `[ ]` jh_merchant_users 表已创建
- `[ ]` jh_merchant_api_keys 表已创建

### 2.2 第二阶段：Central DB 支付表（10个表）

```bash
# 继续执行（如果 Phase 1 中断，重新运行完整命令）
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

# 验证支付表
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} jerseyholic_central \
  -e "SHOW TABLES LIKE 'jh_payment%'; SHOW TABLES LIKE 'jh_settlement%';"
```

- `[ ]` jh_payment_accounts 表已创建
- `[ ]` jh_store_payment_accounts 表已创建
- `[ ]` jh_settlement_records 表已创建
- `[ ]` jh_settlement_details 表已创建
- `[ ]` jh_merchant_risk_scores 表已创建
- `[ ]` jh_fund_flow_logs 表已创建
- `[ ]` jh_payment_account_groups 表已创建
- `[ ]` jh_merchant_payment_group_mappings 表已创建
- `[ ]` jh_settlement_refund_adjustments 表已创建

### 2.3 第三阶段：Central DB 商品表（6个表）

```bash
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

# 验证品类表
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} jerseyholic_central \
  -e "SHOW TABLES LIKE 'jh_product%'; SHOW TABLES LIKE 'jh_category%';"
```

- `[ ]` jh_product_sync_logs 表已创建
- `[ ]` jh_product_categories_l1 表已创建
- `[ ]` jh_product_categories_l2 表已创建
- `[ ]` jh_category_safe_names 表已创建
- `[ ]` jh_sensitive_brands 表已创建
- `[ ]` jh_store_product_configs 表已创建

### 2.4 第四阶段：Central DB 风控与运营表（7个表）

```bash
php artisan migrate --path=database/migrations/central \
  --database=central \
  --force \
  --step

# 验证
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} jerseyholic_central \
  -e "SHOW TABLES LIKE 'jh_blacklist%'; SHOW TABLES LIKE 'jh_commission%';"
```

- `[ ]` jh_blacklist 表已创建
- `[ ]` jh_paypal_safe_descriptions 表已创建
- `[ ]` jh_commission_rules 表已创建
- `[ ]` jh_notifications 表已创建
- `[ ]` jh_merchant_audit_logs 表已创建

### 2.5 第五阶段：Tenant DB 模板迁移

```bash
# 验证租户迁移文件
ls database/migrations/tenant/ | wc -l
# 预期: 约 19 个迁移文件

# 创建测试租户验证自动迁移
php artisan tinker --execute="
\$store = App\Models\Central\Store::create([
    'id' => 'launch_test',
    'merchant_id' => 1,
    'name' => 'Launch Test Store',
]);
\$store->domains()->create(['domain' => 'launch-test.jerseyholic.xyz']);
echo 'Tenant created successfully';
"

# 验证租户数据库
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} -e "SHOW DATABASES LIKE 'store_launch_test';"
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} store_launch_test -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'store_launch_test';"
```

- `[ ]` 租户数据库可自动创建
- `[ ]` 租户数据库包含约 30+ 张表
- `[ ]` 测试租户已删除

```bash
# 删除测试租户
php artisan tinker --execute="
\$store = App\Models\Central\Store::find('launch_test');
if (\$store) { \$store->delete(); echo 'Test tenant deleted'; }
"
```

### 2.6 第六阶段：Merchant DB DDL

```bash
# 通过创建测试商户验证商户数据库迁移
curl -X POST https://admin.jerseyholic.xyz/api/v1/admin/merchants \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"name":"Launch Test Merchant","email":"launch-test@example.com","phone":"+1234567890"}'

# 验证商户数据库
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} -e "SHOW DATABASES LIKE 'jerseyholic_merchant_%';"
```

- `[ ]` 商户数据库可自动创建
- `[ ]` master_products, master_product_translations, sync_rules 表已创建
- `[ ]` 测试商户已删除

### 2.7 迁移完成总体验证

```bash
# 查看完整迁移状态
php artisan migrate:status --database=central

# 统计表数量
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} -e "
SELECT
    'Central' as db,
    COUNT(*) as table_count
FROM information_schema.tables
WHERE table_schema = 'jerseyholic_central';"
```

- `[ ]` Central DB 迁移全部完成（约 25+ 张表）
- `[ ]` 无 Pending 状态的迁移
- `[ ]` 外键约束正确（无 constraint 错误）

---

## 三、Nginx 配置

### 3.1 通配符域名证书

> SSL 证书配置见第六节，此处仅做路径验证

```bash
# 确认证书文件存在
ls -la /etc/letsencrypt/live/jerseyholic.xyz/
# 预期文件: fullchain.pem, privkey.pem, cert.pem, chain.pem
```

- `[ ]` SSL 证书文件存在且未过期

### 3.2 Nginx 配置文件

将以下四个 server block 写入 `/etc/nginx/sites-available/jerseyholic.conf`：

```nginx
# ============================================================
# Admin Panel: admin.jerseyholic.xyz
# ============================================================
server {
    listen 80;
    server_name admin.jerseyholic.xyz;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admin.jerseyholic.xyz;

    ssl_certificate     /etc/letsencrypt/live/jerseyholic.xyz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/jerseyholic.xyz/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 10m;

    root /var/www/jerseyholic/admin-ui/dist;
    index index.html;

    # 安全头
    add_header X-Content-Type-Options    "nosniff"       always;
    add_header X-Frame-Options           "SAMEORIGIN"    always;
    add_header X-XSS-Protection          "1; mode=block" always;
    add_header Referrer-Policy           "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # SPA 路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 代理到 Laravel
    location /api/ {
        proxy_pass         http://127.0.0.1:9000;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade      $http_upgrade;
        proxy_set_header   Connection   "upgrade";
        proxy_set_header   Host         $host;
        proxy_set_header   X-Real-IP    $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
        proxy_connect_timeout 60;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
        return 404;
    }

    access_log  /var/log/nginx/admin.jerseyholic.xyz.access.log;
    error_log   /var/log/nginx/admin.jerseyholic.xyz.error.log warn;
}

# ============================================================
# Merchant Panel: merchant.jerseyholic.xyz
# ============================================================
server {
    listen 80;
    server_name merchant.jerseyholic.xyz;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name merchant.jerseyholic.xyz;

    ssl_certificate     /etc/letsencrypt/live/jerseyholic.xyz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/jerseyholic.xyz/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/jerseyholic/merchant-ui/dist;
    index index.html;

    add_header X-Content-Type-Options    "nosniff"       always;
    add_header X-Frame-Options           "SAMEORIGIN"    always;
    add_header X-XSS-Protection          "1; mode=block" always;
    add_header Referrer-Policy           "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api/ {
        proxy_pass         http://127.0.0.1:9000;
        proxy_http_version 1.1;
        proxy_set_header   Host         $host;
        proxy_set_header   X-Real-IP    $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ /\. { deny all; return 404; }

    access_log  /var/log/nginx/merchant.jerseyholic.xyz.access.log;
    error_log   /var/log/nginx/merchant.jerseyholic.xyz.error.log warn;
}

# ============================================================
# Storefront: *.jerseyholic.xyz (多租户通配符域名)
# ============================================================
server {
    listen 80;
    server_name ~^(?<subdomain>.+)\.jerseyholic\.xyz$;

    # 排除已知固定子域名
    if ($subdomain ~* "^(admin|merchant|api)$") {
        return 301 https://$host$request_uri;
    }

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ~^(?<subdomain>.+)\.jerseyholic\.xyz$;

    ssl_certificate     /etc/letsencrypt/live/jerseyholic.xyz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/jerseyholic.xyz/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/jerseyholic/storefront/.output/public;
    index index.html;

    add_header X-Content-Type-Options    "nosniff"       always;
    add_header X-Frame-Options           "SAMEORIGIN"    always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Nuxt 3 SSR / Static
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 代理
    location /api/ {
        proxy_pass         http://127.0.0.1:9000;
        proxy_http_version 1.1;
        proxy_set_header   Host         $host;
        proxy_set_header   X-Real-IP    $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 120;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ /\. { deny all; return 404; }

    access_log  /var/log/nginx/storefront.access.log;
    error_log   /var/log/nginx/storefront.error.log warn;
}

# ============================================================
# API Backend: api.jerseyholic.xyz (Laravel PHP-FPM)
# ============================================================
server {
    listen 80;
    server_name api.jerseyholic.xyz;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.jerseyholic.xyz;

    ssl_certificate     /etc/letsencrypt/live/jerseyholic.xyz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/jerseyholic.xyz/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/jerseyholic/api/public;
    index index.php;

    # 客户端请求体限制
    client_max_body_size 50M;

    add_header X-Content-Type-Options    "nosniff"       always;
    add_header X-Frame-Options           "DENY"          always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    # 隐藏服务器信息
    server_tokens off;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass    unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index   index.php;
        fastcgi_param   SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include         fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 64k;
        fastcgi_buffers 16 64k;
    }

    # 禁止直接访问敏感文件
    location ~ /\.(env|git|htaccess|htpasswd) {
        deny all;
        return 404;
    }

    # 健康检查端点
    location = /health {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    access_log  /var/log/nginx/api.jerseyholic.xyz.access.log;
    error_log   /var/log/nginx/api.jerseyholic.xyz.error.log warn;
}
```

```bash
# 启用配置
sudo ln -sf /etc/nginx/sites-available/jerseyholic.conf \
  /etc/nginx/sites-enabled/jerseyholic.conf

# 测试配置
sudo nginx -t

# 重载 Nginx
sudo systemctl reload nginx
```

- `[ ]` Nginx 配置文件已创建
- `[ ]` `nginx -t` 测试通过，无语法错误
- `[ ]` Nginx 已重载
- `[ ]` 四个域名均可访问（返回正确响应）

```bash
# 验证各域名
curl -I https://admin.jerseyholic.xyz
curl -I https://merchant.jerseyholic.xyz
curl -I https://api.jerseyholic.xyz/health
curl -I https://demo.jerseyholic.xyz
```

---

## 四、Redis 配置

### 4.1 Redis 实例配置

生产环境建议使用三个逻辑数据库（或三个独立连接）分别处理 Cache、Session、Queue：

```bash
# 检查 Redis 是否运行
redis-cli ping  # 预期: PONG

# 检查 Redis 版本和内存
redis-cli info server | grep -E "(redis_version|tcp_port|maxmemory)"
redis-cli info memory | grep used_memory_human
```

### 4.2 Laravel .env Redis 配置

```bash
# 确认以下配置已正确设置
grep -E "^REDIS_" /var/www/jerseyholic/api/.env
```

`.env` 中应包含：

```dotenv
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# Cache 连接（使用 DB 0）
REDIS_CACHE_DB=0

# Session 连接（使用 DB 1）
REDIS_SESSION_DB=1

# Queue 连接（使用 DB 2）
REDIS_QUEUE_DB=2

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 4.3 Redis 连接测试

```bash
cd /var/www/jerseyholic/api

# 测试 Cache 连接
php artisan tinker --execute="
Cache::put('launch_test', 'ok', 60);
\$val = Cache::get('launch_test');
dd(\$val === 'ok' ? 'Cache: OK' : 'Cache: FAILED');
"

# 测试 Session 连接
php artisan tinker --execute="
\$client = Redis::connection('session');
\$client->set('session_test', 'ok', 60);
\$val = \$client->get('session_test');
dd(\$val === 'ok' ? 'Session Redis: OK' : 'Session Redis: FAILED');
"

# 测试 Queue 连接
php artisan tinker --execute="
\$client = Redis::connection('queue');
\$result = \$client->ping();
dd(\$result ? 'Queue Redis: OK' : 'Queue Redis: FAILED');
"
```

- `[ ]` Redis Cache 连接正常（DB 0）
- `[ ]` Redis Session 连接正常（DB 1）
- `[ ]` Redis Queue 连接正常（DB 2）

### 4.4 Redis 持久化配置

```bash
# 验证 RDB 持久化开启
redis-cli config get save
# 预期: 有配置项，如 3600 1 300 100 60 10000

# 验证 AOF 持久化（推荐生产开启）
redis-cli config get appendonly
# 推荐值: yes
```

- `[ ]` Redis 持久化已配置（RDB 或 AOF）
- `[ ]` Redis 最大内存策略已设置（`maxmemory-policy allkeys-lru`）

---

## 五、Supervisor Queue Worker 配置

### 5.1 Supervisor 配置文件

创建 `/etc/supervisor/conf.d/jerseyholic-worker.conf`：

```ini
[program:jh-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/jerseyholic/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=default,high,low
directory=/var/www/jerseyholic/api
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/jh-worker-default.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:jh-worker-payment]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/jerseyholic/api/artisan queue:work redis --sleep=1 --tries=5 --max-time=3600 --queue=payment,settlement
directory=/var/www/jerseyholic/api
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/jh-worker-payment.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:jh-worker-tenant]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/jerseyholic/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=tenant
directory=/var/www/jerseyholic/api
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/jh-worker-tenant.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

[group:jh-workers]
programs=jh-worker-default,jh-worker-payment,jh-worker-tenant
```

```bash
# 重新读取配置并启动
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start jh-workers:*

# 验证状态
sudo supervisorctl status
```

- `[ ]` Supervisor 配置文件已创建
- `[ ]` jh-worker-default 进程运行中（RUNNING）
- `[ ]` jh-worker-payment 进程运行中（RUNNING）
- `[ ]` jh-worker-tenant 进程运行中（RUNNING）

### 5.2 队列健康检查

```bash
cd /var/www/jerseyholic/api

# 查看队列积压
php artisan queue:monitor redis:default,redis:payment,redis:settlement

# 查看失败队列
php artisan queue:failed | head -20
```

- `[ ]` 各队列无异常积压（< 100 条）
- `[ ]` 失败队列为空或在预期范围内

---

## 六、SSL 证书（Let's Encrypt 通配符）

### 6.1 申请通配符证书

```bash
# 安装 Certbot
sudo apt install certbot python3-certbot-dns-cloudflare -y

# 申请通配符证书（需要 DNS TXT 验证）
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
  -d "jerseyholic.xyz" \
  -d "*.jerseyholic.xyz" \
  --email ops@jerseyholic.xyz \
  --agree-tos \
  --no-eff-email

# 或使用手动 DNS 验证
sudo certbot certonly \
  --manual \
  --preferred-challenges=dns \
  -d "jerseyholic.xyz" \
  -d "*.jerseyholic.xyz"
```

### 6.2 验证证书

```bash
# 查看证书详情
sudo certbot certificates

# 验证证书有效期
openssl x509 -in /etc/letsencrypt/live/jerseyholic.xyz/fullchain.pem \
  -noout -dates

# 测试续期（dry-run）
sudo certbot renew --dry-run
```

### 6.3 自动续期配置

```bash
# 查看 Certbot 定时任务
sudo systemctl status certbot.timer

# 如果没有，手动配置 crontab
sudo crontab -e
# 添加: 0 3 * * * certbot renew --quiet && systemctl reload nginx
```

- `[ ]` SSL 证书已申请（覆盖 `*.jerseyholic.xyz`）
- `[ ]` 证书有效期 > 60 天
- `[ ]` 自动续期已配置
- `[ ]` 续期测试通过（dry-run）

---

## 七、Laravel 部署命令

### 7.1 安装依赖

```bash
cd /var/www/jerseyholic/api

# 安装 Composer 依赖（生产模式）
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist

# 设置正确的文件权限
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

- `[ ]` Composer 依赖安装完成
- `[ ]` storage 目录权限正确（755/775）

### 7.2 环境配置

```bash
# 确认 .env 文件存在且配置正确
diff .env .env.example | head -50

# 关键配置项检查
grep -E "^(APP_ENV|APP_KEY|APP_DEBUG|DB_|REDIS_|MAIL_|QUEUE_)" .env
```

**必须确认的配置**：

- `[ ]` `APP_ENV=production`
- `[ ]` `APP_DEBUG=false`
- `[ ]` `APP_KEY` 已生成（`php artisan key:generate`）
- `[ ]` `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` 正确
- `[ ]` `REDIS_HOST`, `REDIS_PASSWORD` 正确
- `[ ]` `MAIL_*` 配置正确（如使用邮件功能）
- `[ ]` `PAYPAL_*` / 支付相关密钥已配置

### 7.3 缓存优化命令

```bash
cd /var/www/jerseyholic/api

# 1. 清除旧缓存
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 2. 重新生成缓存（生产加速）
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. 创建 storage 软链接
php artisan storage:link

# 4. 优化 autoloader
composer dump-autoload --optimize

echo "Laravel deployment commands completed!"
```

- `[ ]` `config:cache` 成功（无报错）
- `[ ]` `route:cache` 成功（无报错）
- `[ ]` `view:cache` 成功（无报错）
- `[ ]` `storage:link` 成功（public/storage 软链接存在）

### 7.4 前端构建

```bash
# Admin UI
cd /var/www/jerseyholic/admin-ui
npm ci
npm run build
echo "Admin UI built: $(ls dist/ | wc -l) files"

# Merchant UI
cd /var/www/jerseyholic/merchant-ui
npm ci
npm run build
echo "Merchant UI built: $(ls dist/ | wc -l) files"

# Storefront (Nuxt 3)
cd /var/www/jerseyholic/storefront
npm ci
npm run generate  # 或 npm run build 用于 SSR
echo "Storefront built"
```

- `[ ]` Admin UI 构建成功（dist/ 目录存在）
- `[ ]` Merchant UI 构建成功（dist/ 目录存在）
- `[ ]` Storefront 构建成功（.output/ 目录存在）

---

## 八、验证步骤清单

### 8.1 服务健康检查

```bash
# API 健康检查
curl -s https://api.jerseyholic.xyz/health | python3 -m json.tool

# 预期响应
# {
#   "status": "ok",
#   "database": "connected",
#   "redis": "connected",
#   "queue": "running"
# }
```

- `[ ]` API `/health` 返回 200，数据库和 Redis 状态均为 connected

### 8.2 认证系统验证

```bash
# Admin 登录
ADMIN_TOKEN=$(curl -s -X POST https://api.jerseyholic.xyz/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"admin@jerseyholic.xyz\",\"password\":\"${ADMIN_INIT_PASSWORD}\"}" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

echo "Admin Token: ${ADMIN_TOKEN:0:20}..."

# 验证 Admin 获取自身信息
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
  https://api.jerseyholic.xyz/api/v1/admin/auth/me | python3 -m json.tool
```

- `[ ]` Admin 登录返回有效 Token
- `[ ]` `/api/v1/admin/auth/me` 返回正确用户信息

### 8.3 商户系统验证

```bash
# 通过 Admin 创建测试商户
curl -s -X POST https://api.jerseyholic.xyz/api/v1/admin/merchants \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Launch Test","email":"launch@example.com","phone":"+1234567890"}'

# 商户列表
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
  https://api.jerseyholic.xyz/api/v1/admin/merchants | python3 -m json.tool
```

- `[ ]` 商户创建接口返回 201
- `[ ]` 商户列表接口返回数据正常

### 8.4 多租户验证

```bash
# 创建测试店铺（通过商户账号登录后操作）
# 访问测试租户域名
curl -I https://launch-test.jerseyholic.xyz
```

- `[ ]` 租户域名可访问
- `[ ]` 租户数据库隔离正常

### 8.5 支付系统验证

```bash
# 检查支付账户配置
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
  https://api.jerseyholic.xyz/api/v1/admin/payment-accounts
```

- `[ ]` PayPal 支付账户配置正确
- `[ ]` Webhook 地址已在 PayPal 后台注册

### 8.6 队列处理验证

```bash
cd /var/www/jerseyholic/api

# 发送测试任务
php artisan tinker --execute="
dispatch(new App\Jobs\TestJob('launch_check'));
echo 'Test job dispatched';
"

# 检查队列处理（等待 5 秒）
sleep 5
php artisan queue:failed | head -5
```

- `[ ]` 测试任务已被处理（无失败记录）

---

## 九、监控告警配置

### 9.1 磁盘使用告警

```bash
# 创建磁盘监控脚本
cat > /usr/local/bin/check-disk.sh << 'EOF'
#!/bin/bash
THRESHOLD=80
USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')

if [ "$USAGE" -gt "$THRESHOLD" ]; then
    echo "ALERT: Disk usage is ${USAGE}% on $(hostname)" | \
      mail -s "[JerseyHolic] Disk Alert" ops@jerseyholic.xyz
fi
EOF

chmod +x /usr/local/bin/check-disk.sh

# 添加到 crontab
echo "*/30 * * * * /usr/local/bin/check-disk.sh" | sudo crontab -
```

**告警阈值**：

| 指标 | 警告阈值 | 严重阈值 |
|------|---------|---------|
| 磁盘使用率 | 80% | 90% |
| 内存使用率 | 85% | 95% |
| CPU 使用率（5分钟均值）| 70% | 90% |
| 队列积压 | 500 条 | 2000 条 |
| 错误日志增速 | 100 条/分钟 | 500 条/分钟 |

### 9.2 内存告警

```bash
cat > /usr/local/bin/check-memory.sh << 'EOF'
#!/bin/bash
THRESHOLD=85
USAGE=$(free | awk '/Mem/{printf("%.0f", $3/$2*100)}')

if [ "$USAGE" -gt "$THRESHOLD" ]; then
    echo "ALERT: Memory usage is ${USAGE}% on $(hostname)" | \
      mail -s "[JerseyHolic] Memory Alert" ops@jerseyholic.xyz
fi
EOF

chmod +x /usr/local/bin/check-memory.sh
echo "*/5 * * * * /usr/local/bin/check-memory.sh" | sudo crontab -
```

### 9.3 CPU 负载告警

```bash
cat > /usr/local/bin/check-cpu.sh << 'EOF'
#!/bin/bash
THRESHOLD=70
LOAD=$(uptime | awk -F'load average:' '{print $2}' | cut -d',' -f1 | tr -d ' ')
CPUS=$(nproc)
USAGE=$(echo "$LOAD $CPUS" | awk '{printf("%.0f", ($1/$2)*100)}')

if [ "$USAGE" -gt "$THRESHOLD" ]; then
    echo "ALERT: CPU load is ${USAGE}% (load: $LOAD, cpus: $CPUS) on $(hostname)" | \
      mail -s "[JerseyHolic] CPU Alert" ops@jerseyholic.xyz
fi
EOF

chmod +x /usr/local/bin/check-cpu.sh
echo "*/5 * * * * /usr/local/bin/check-cpu.sh" | sudo crontab -
```

### 9.4 队列积压告警

```bash
cat > /usr/local/bin/check-queue.sh << 'EOF'
#!/bin/bash
THRESHOLD=500
cd /var/www/jerseyholic/api

# 检查各队列积压
for QUEUE in default payment settlement tenant; do
    COUNT=$(php artisan tinker --execute="
    echo Redis::connection('queue')->llen('queues:$QUEUE');
    " 2>/dev/null | tail -1)

    if [ -n "$COUNT" ] && [ "$COUNT" -gt "$THRESHOLD" ]; then
        echo "ALERT: Queue '$QUEUE' has $COUNT pending jobs" | \
          mail -s "[JerseyHolic] Queue Backlog Alert" ops@jerseyholic.xyz
    fi
done
EOF

chmod +x /usr/local/bin/check-queue.sh
echo "*/10 * * * * /usr/local/bin/check-queue.sh" | sudo crontab -
```

- `[ ]` 磁盘告警脚本已配置
- `[ ]` 内存告警脚本已配置
- `[ ]` CPU 告警脚本已配置
- `[ ]` 队列积压告警已配置
- `[ ]` 告警邮件接收地址已验证可达

---

## 十、DNS 切换步骤

### 10.1 切换前准备

- `[ ]` 已确认生产服务器所有服务正常运行
- `[ ]` 已完成全量功能测试（UAT 通过）
- `[ ]` 已通知相关团队 DNS 切换时间窗口
- `[ ]` 已准备回滚方案（参考 `docs/deployment/rollback-plan.md`）

### 10.2 降低 TTL

```bash
# 切换前 24 小时，将 DNS TTL 降低为 300 秒（5分钟）
# 便于快速回滚

# 在 DNS 提供商控制台（如 Cloudflare）中：
# jerseyholic.xyz    A    → 新服务器 IP    TTL: 300
# *.jerseyholic.xyz  A    → 新服务器 IP    TTL: 300
```

- `[ ]` DNS TTL 已降低为 300 秒（切换前 24 小时完成）

### 10.3 执行 DNS 切换

```bash
# 新服务器 IP（替换为实际 IP）
NEW_SERVER_IP="x.x.x.x"

# 在 DNS 提供商控制台更新以下记录：
# 记录类型  主机名                    值               TTL
# A         jerseyholic.xyz           $NEW_SERVER_IP   300
# A         *.jerseyholic.xyz         $NEW_SERVER_IP   300
# A         admin.jerseyholic.xyz     $NEW_SERVER_IP   300
# A         merchant.jerseyholic.xyz  $NEW_SERVER_IP   300
# A         api.jerseyholic.xyz       $NEW_SERVER_IP   300
```

### 10.4 DNS 切换验证

```bash
# 等待 DNS 传播（通常 5-15 分钟）
sleep 300

# 检查 DNS 解析
dig +short jerseyholic.xyz
dig +short api.jerseyholic.xyz
dig +short admin.jerseyholic.xyz

# 使用全球 DNS 检查工具验证传播
# https://www.whatsmydns.net/#A/jerseyholic.xyz

# 验证 HTTPS 可访问
curl -I https://admin.jerseyholic.xyz
curl -I https://api.jerseyholic.xyz/health
```

- `[ ]` DNS 解析指向新服务器 IP
- `[ ]` 全球主要地区 DNS 已传播
- `[ ]` HTTPS 可正常访问
- `[ ]` SSL 证书有效（无证书警告）

### 10.5 切换后恢复 TTL

```bash
# 确认服务稳定运行 2 小时后，恢复 TTL 为 3600（1小时）
# 在 DNS 控制台中将 TTL 改回 3600
```

- `[ ]` 服务稳定 2 小时后，TTL 恢复为 3600

---

## 十一、上线后 24 小时观察

### 11.1 关键指标监控

```bash
# 持续监控日志
tail -f /var/www/jerseyholic/api/storage/logs/laravel.log | grep -E "(ERROR|CRITICAL|EMERGENCY)"

# 监控 Nginx 错误
tail -f /var/log/nginx/api.jerseyholic.xyz.error.log

# 监控 Supervisor
sudo supervisorctl status
watch -n 60 'sudo supervisorctl status'
```

### 11.2 上线后检查点

| 时间节点 | 检查内容 | 状态 |
|---------|---------|------|
| 上线后 15 分钟 | 核心接口返回 200，无 5xx 错误 | `[ ]` |
| 上线后 1 小时 | 队列处理正常，失败队列 < 10 条 | `[ ]` |
| 上线后 4 小时 | 数据库连接稳定，无连接池耗尽 | `[ ]` |
| 上线后 24 小时 | 完整业务流程验证（注册→下单→支付） | `[ ]` |

---

## 签字确认

| 角色 | 姓名 | 签字 | 日期 |
|------|------|------|------|
| 执行工程师 | | | |
| 审核工程师 | | | |
| 技术负责人 | | | |
| 产品负责人 | | | |

---

**文档版本**: v1.0  
**最后更新**: 2026-04-17  
**关联文档**: `database-migration-order.md` | `rollback-plan.md` | `operations-guide.md`
