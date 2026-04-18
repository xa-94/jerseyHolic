# JerseyHolic 运维手册

> **版本**: v1.0  
> **适用环境**: 生产环境 (Production)  
> **更新频率**: 按需更新

---

## 1. 日常运维任务

### 1.1 日志轮转 (Laravel Log Rotate)

#### 1.1.1 手动日志清理

```bash
# 查看日志目录大小
du -sh /var/www/jerseyholic/api/storage/logs/

# 压缩 7 天前的日志
find /var/www/jerseyholic/api/storage/logs/ -name "laravel-*.log" -mtime +7 -exec gzip {} \;

# 删除 30 天前的压缩日志
find /var/www/jerseyholic/api/storage/logs/ -name "*.gz" -mtime +30 -delete

# 清空当前日志（保留文件）
> /var/www/jerseyholic/api/storage/logs/laravel.log
```

#### 1.1.2 配置 Logrotate

```bash
# 创建 logrotate 配置
sudo tee /etc/logrotate.d/jerseyholic << 'EOF'
/var/www/jerseyholic/api/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
    sharedscripts
    postrotate
        # 通知 Laravel 重新打开日志文件
        cd /var/www/jerseyholic/api && php artisan cache:clear > /dev/null 2>&1 || true
    endscript
}
EOF

# 测试配置
sudo logrotate -d /etc/logrotate.d/jerseyholic

# 强制运行一次
sudo logrotate -f /etc/logrotate.d/jerseyholic
```

#### 1.1.3 Nginx 日志轮转

```bash
sudo tee /etc/logrotate.d/jerseyholic-nginx << 'EOF'
/var/log/nginx/jerseyholic-*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data adm
    sharedscripts
    postrotate
        # 优雅地重新打开日志文件
        [ -f /var/run/nginx.pid ] && kill -USR1 $(cat /var/run/nginx.pid)
    endscript
}
EOF
```

### 1.2 缓存清理

#### 1.2.1 应用缓存清理

```bash
cd /var/www/jerseyholic/api

# 清理所有缓存（谨慎使用）
php artisan cache:clear

# 仅清理配置缓存
php artisan config:clear

# 重新生成配置缓存（生产环境推荐）
php artisan config:cache

# 清理路由缓存
php artisan route:clear
php artisan route:cache

# 清理视图缓存
php artisan view:clear
php artisan view:cache

# 清理事件缓存
php artisan event:clear
php artisan event:cache

# 清理优化文件
php artisan optimize:clear

# 重新优化（生产环境部署后执行）
php artisan optimize
```

#### 1.2.2 Redis 缓存清理

```bash
# 连接到 Redis
redis-cli

# 查看当前数据库键数量
DBSIZE

# 清理当前数据库（谨慎！）
FLUSHDB

# 清理所有数据库（极度谨慎！）
FLUSHALL

# 按模式删除键
del laravel:cache:*
del laravel:session:*

# 退出
exit
```

#### 1.2.3 定时清理脚本

```bash
#!/bin/bash
# /usr/local/bin/jerseyholic-cache-cleanup.sh

APP_PATH="/var/www/jerseyholic/api"
LOG_FILE="/var/log/jerseyholic/cache-cleanup.log"

exec 1>>"$LOG_FILE"
exec 2>&1

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting cache cleanup..."

cd "$APP_PATH" || exit 1

# 清理过期的缓存标签
php artisan cache:prune-stale-tags 2>/dev/null || true

# 清理失败的队列任务（保留最近 7 天）
php artisan queue:prune-failed --hours=168

# 清理过期的密码重置令牌
php artisan auth:clear-resets

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cache cleanup completed."
```

### 1.3 队列监控

#### 1.3.1 Horizon 监控

```bash
cd /var/www/jerseyholic/api

# 查看 Horizon 状态
php artisan horizon:status

# 查看 Horizon 指标
php artisan horizon:snapshot

# 清理过期指标数据
php artisan horizon:purge

# 暂停 Horizon（维护模式）
php artisan horizon:pause

# 恢复 Horizon
php artisan horizon:continue

# 终止 Horizon（优雅关闭）
php artisan horizon:terminate
```

#### 1.3.2 Supervisor 管理

```bash
# 查看所有进程状态
sudo supervisorctl status

# 查看 JerseyHolic 相关进程
sudo supervisorctl status | grep jh-

# 重启所有队列 worker
sudo supervisorctl restart all

# 重启特定队列
sudo supervisorctl restart jh-horizon
sudo supervisorctl restart jh-queue-default
sudo supervisorctl restart jh-queue-high

# 停止所有队列
sudo supervisorctl stop all

# 启动所有队列
sudo supervisorctl start all

# 重新加载配置
sudo supervisorctl reread
sudo supervisorctl update
```

#### 1.3.3 队列深度监控

```bash
#!/bin/bash
# /usr/local/bin/check-queue-depth.sh

REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"
ALERT_THRESHOLD=1000

# 获取各队列长度
queues=("default" "high" "low" "emails" "payments")

for queue in "${queues[@]}"; do
    depth=$(redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" LLEN "queues:$queue" 2>/dev/null || echo 0)
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Queue '$queue' depth: $depth"
    
    if [ "$depth" -gt "$ALERT_THRESHOLD" ]; then
        echo "ALERT: Queue '$queue' depth ($depth) exceeds threshold ($ALERT_THRESHOLD)"
        # 发送告警（集成企业微信/钉钉/邮件等）
        # curl -X POST ...
    fi
done

# 检查失败任务数量
failed_count=$(redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" LLEN "failed" 2>/dev/null || echo 0)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Failed jobs count: $failed_count"
```

---

## 2. 备份策略

### 2.1 数据库备份

#### 2.1.1 每日全量备份 (mysqldump)

```bash
#!/bin/bash
# /usr/local/bin/backup-database.sh

set -e

BACKUP_DIR="/var/backups/jerseyholic/database"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# 创建备份目录
mkdir -p "$BACKUP_DIR/$DATE"

# 备份 Central 数据库
echo "[$(date)] Back up central database..."
mysqldump \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    -u root -p"${DB_PASSWORD}" \
    jerseyholic_central \
    | gzip > "$BACKUP_DIR/$DATE/central.sql.gz"

# 备份所有租户数据库
echo "[$(date)] Back up tenant databases..."
mysql -u root -p"${DB_PASSWORD}" -e "SHOW DATABASES LIKE 'store_%'" -s --skip-column-names | \
while read db; do
    echo "  Backing up $db..."
    mysqldump \
        --single-transaction \
        -u root -p"${DB_PASSWORD}" \
        "$db" \
        | gzip > "$BACKUP_DIR/$DATE/${db}.sql.gz"
done

# 备份所有商户数据库
echo "[$(date)] Back up merchant databases..."
mysql -u root -p"${DB_PASSWORD}" -e "SHOW DATABASES LIKE 'jerseyholic_merchant_%'" -s --skip-column-names | \
while read db; do
    echo "  Backing up $db..."
    mysqldump \
        --single-transaction \
        -u root -p"${DB_PASSWORD}" \
        "$db" \
        | gzip > "$BACKUP_DIR/$DATE/${db}.sql.gz"
done

# 生成备份校验和
cd "$BACKUP_DIR/$DATE" && md5sum *.sql.gz > checksums.md5

# 清理旧备份
echo "[$(date)] Cleaning up old backups..."
find "$BACKUP_DIR" -maxdepth 1 -type d -mtime +$RETENTION_DAYS -exec rm -rf {} \;

echo "[$(date)] Backup completed: $BACKUP_DIR/$DATE"
```

#### 2.1.2 Binlog 增量备份

```bash
#!/bin/bash
# /usr/local/bin/backup-binlog.sh

BINLOG_BACKUP_DIR="/var/backups/jerseyholic/binlog"
MYSQL_DATA_DIR="/var/lib/mysql"

mkdir -p "$BINLOG_BACKUP_DIR"

# 刷新日志，生成新的 binlog
mysql -u root -p"${DB_PASSWORD}" -e "FLUSH LOGS"

# 复制非当前正在使用的 binlog 文件
current_binlog=$(mysql -u root -p"${DB_PASSWORD}" -e "SHOW MASTER STATUS" -s --skip-column-names | awk '{print $1}')

for binlog in "$MYSQL_DATA_DIR"/binlog.*; do
    filename=$(basename "$binlog")
    if [ "$filename" != "$current_binlog" ]; then
        cp "$binlog" "$BINLOG_BACKUP_DIR/"
        echo "Backed up $filename"
    fi
done

# 压缩 binlog 文件
gzip -f "$BINLOG_BACKUP_DIR"/binlog.*

# 清理 30 天前的 binlog 备份
find "$BINLOG_BACKUP_DIR" -name "binlog.*.gz" -mtime +30 -delete
```

#### 2.1.3 备份验证

```bash
#!/bin/bash
# /usr/local/bin/verify-backup.sh

BACKUP_DIR="/var/backups/jerseyholic/database"
LATEST_BACKUP=$(ls -td "$BACKUP_DIR"/*/ | head -1)

echo "Verifying backup: $LATEST_BACKUP"

# 验证校验和
cd "$LATEST_BACKUP"
if md5sum -c checksums.md5; then
    echo "Checksum verification: PASSED"
else
    echo "Checksum verification: FAILED"
    exit 1
fi

# 测试解压
gunzip -t central.sql.gz && echo "Archive test: PASSED" || echo "Archive test: FAILED"

# 可选：恢复到测试数据库进行验证
# mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS test_restore;"
# gunzip < "$LATEST_BACKUP/central.sql.gz" | mysql -u root -p test_restore
```

### 2.2 文件备份

#### 2.2.1 商品图片/上传文件备份

```bash
#!/bin/bash
# /usr/local/bin/backup-files.sh

set -e

BACKUP_DIR="/var/backups/jerseyholic/files"
DATE=$(date +%Y%m%d_%H%M%S)
SOURCE_DIRS=(
    "/var/www/jerseyholic/api/storage/app/public/products"
    "/var/www/jerseyholic/api/storage/app/public/uploads"
    "/var/www/jerseyholic/api/storage/app/public/media"
)

mkdir -p "$BACKUP_DIR/$DATE"

# 使用 rsync 进行增量备份
for dir in "${SOURCE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        target_name=$(basename "$dir")
        echo "Backing up $dir..."
        tar -czf "$BACKUP_DIR/$DATE/${target_name}.tar.gz" -C "$(dirname "$dir")" "$(basename "$dir")"
    fi
done

# 备份存储链接配置
cp /var/www/jerseyholic/api/storage/app/public/.gitignore "$BACKUP_DIR/$DATE/" 2>/dev/null || true

echo "File backup completed: $BACKUP_DIR/$DATE"

# 保留策略：本地 7 天
find "$BACKUP_DIR" -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
```

#### 2.2.2 配置文件备份

```bash
#!/bin/bash
# /usr/local/bin/backup-config.sh

BACKUP_DIR="/var/backups/jerseyholic/config"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR/$DATE"

# 备份应用配置
cp /var/www/jerseyholic/api/.env "$BACKUP_DIR/$DATE/"
cp -r /var/www/jerseyholic/api/config "$BACKUP_DIR/$DATE/"

# 备份 Nginx 配置
cp -r /etc/nginx/sites-available "$BACKUP_DIR/$DATE/nginx/"
cp -r /etc/nginx/sites-enabled "$BACKUP_DIR/$DATE/nginx/"

# 备份 Supervisor 配置
cp -r /etc/supervisor/conf.d "$BACKUP_DIR/$DATE/supervisor/"

# 备份 PHP-FPM 配置
cp /etc/php/8.2/fpm/php-fpm.conf "$BACKUP_DIR/$DATE/" 2>/dev/null || true
cp -r /etc/php/8.2/fpm/pool.d "$BACKUP_DIR/$DATE/" 2>/dev/null || true

# 打包
tar -czf "$BACKUP_DIR/config_$DATE.tar.gz" -C "$BACKUP_DIR/$DATE" .
rm -rf "$BACKUP_DIR/$DATE"

# 保留 30 个版本
ls -t "$BACKUP_DIR"/config_*.tar.gz | tail -n +31 | xargs -r rm

echo "Config backup completed: $BACKUP_DIR/config_$DATE.tar.gz"
```

### 2.3 远程备份同步

```bash
#!/bin/bash
# /usr/local/bin/sync-remote-backup.sh

# 配置
LOCAL_BACKUP_DIR="/var/backups/jerseyholic"
REMOTE_SERVER="backup@backup-server.example.com"
REMOTE_PATH="/backups/jerseyholic"
RETENTION_DAYS=30

# 同步到远程服务器
echo "Syncing backups to remote server..."
rsync -avz --delete \
    "$LOCAL_BACKUP_DIR/" \
    "$REMOTE_SERVER:$REMOTE_PATH/"

# 清理远程旧备份（保留 30 天）
ssh "$REMOTE_SERVER" "find $REMOTE_PATH/database -maxdepth 1 -type d -mtime +$RETENTION_DAYS -exec rm -rf {} \;"

echo "Remote sync completed."
```

### 2.4 备份保留策略

| 备份类型 | 本地保留 | 远程保留 | 备份时间 |
|---------|---------|---------|---------|
| 数据库全量 | 7 天 | 30 天 | 每日 02:00 |
| Binlog 增量 | 7 天 | 30 天 | 每小时 |
| 文件备份 | 7 天 | 30 天 | 每日 03:00 |
| 配置备份 | 30 个版本 | 90 天 | 配置变更时 |

#### Crontab 配置

```bash
# 编辑 crontab
sudo crontab -e

# 添加以下任务
# 每日 02:00 执行数据库备份
0 2 * * * /usr/local/bin/backup-database.sh >> /var/log/jerseyholic/backup-db.log 2>&1

# 每小时执行 binlog 备份
0 * * * * /usr/local/bin/backup-binlog.sh >> /var/log/jerseyholic/backup-binlog.log 2>&1

# 每日 03:00 执行文件备份
0 3 * * * /usr/local/bin/backup-files.sh >> /var/log/jerseyholic/backup-files.log 2>&1

# 每日 04:00 同步到远程
0 4 * * * /usr/local/bin/sync-remote-backup.sh >> /var/log/jerseyholic/backup-sync.log 2>&1

# 每日 06:00 验证备份
0 6 * * * /usr/local/bin/verify-backup.sh >> /var/log/jerseyholic/backup-verify.log 2>&1
```

---

## 3. 性能监控

### 3.1 系统资源监控

#### 3.1.1 关键指标采集

```bash
#!/bin/bash
# /usr/local/bin/system-metrics.sh

METRICS_FILE="/var/log/jerseyholic/metrics/$(date +%Y%m%d).log"
mkdir -p "$(dirname "$METRICS_FILE")"

# 时间戳
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# CPU 使用率
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)

# 内存使用率
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.2f", $3/$2 * 100.0}')

# 磁盘使用率
DISK_USAGE=$(df -h /var/www/jerseyholic | tail -1 | awk '{print $5}' | tr -d '%')

# 负载平均
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')

# 记录指标
echo "$TIMESTAMP,CPU:$CPU_USAGE,MEM:$MEMORY_USAGE,DISK:$DISK_USAGE,LOAD:$LOAD_AVG" >> "$METRICS_FILE"
```

#### 3.1.2 阈值告警

```bash
#!/bin/bash
# /usr/local/bin/alert-check.sh

# 阈值配置
CPU_THRESHOLD=80
MEM_THRESHOLD=85
DISK_THRESHOLD=90
LOAD_THRESHOLD=$(nproc)

# 检查 CPU
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1 | cut -d'.' -f1)
if [ "$CPU_USAGE" -gt "$CPU_THRESHOLD" ]; then
    echo "ALERT: CPU usage is ${CPU_USAGE}% (threshold: ${CPU_THRESHOLD}%)"
    # 发送告警通知
fi

# 检查内存
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%d", $3/$2 * 100}')
if [ "$MEMORY_USAGE" -gt "$MEM_THRESHOLD" ]; then
    echo "ALERT: Memory usage is ${MEMORY_USAGE}% (threshold: ${MEM_THRESHOLD}%)"
fi

# 检查磁盘
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | tr -d '%')
if [ "$DISK_USAGE" -gt "$DISK_THRESHOLD" ]; then
    echo "ALERT: Disk usage is ${DISK_USAGE}% (threshold: ${DISK_THRESHOLD}%)"
fi

# 检查负载
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}')
if (( $(echo "$LOAD_AVG > $LOAD_THRESHOLD" | bc -l) )); then
    echo "ALERT: Load average is $LOAD_AVG (threshold: $LOAD_THRESHOLD)"
fi
```

### 3.2 应用性能监控

#### 3.2.1 API 响应时间监控

```bash
#!/bin/bash
# /usr/local/bin/api-health-check.sh

API_ENDPOINT="https://admin.jerseyholic.xyz/api/health"
RESPONSE_TIME_THRESHOLD=2000  # 毫秒

# 测量响应时间
START_TIME=$(date +%s%N)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_ENDPOINT")
END_TIME=$(date +%s%N)

RESPONSE_TIME=$(( (END_TIME - START_TIME) / 1000000 ))  # 转换为毫秒

echo "[$(date)] API Health Check - HTTP: $HTTP_CODE, Response Time: ${RESPONSE_TIME}ms"

if [ "$HTTP_CODE" != "200" ]; then
    echo "ALERT: API returned HTTP $HTTP_CODE"
fi

if [ "$RESPONSE_TIME" -gt "$RESPONSE_TIME_THRESHOLD" ]; then
    echo "ALERT: API response time ${RESPONSE_TIME}ms exceeds threshold ${RESPONSE_TIME_THRESHOLD}ms"
fi
```

#### 3.2.2 数据库性能监控

```bash
#!/bin/bash
# /usr/local/bin/db-performance-check.sh

# 慢查询数量
SLOW_QUERIES=$(mysql -u root -p"${DB_PASSWORD}" -e "SHOW GLOBAL STATUS LIKE 'Slow_queries'" -s --skip-column-names | awk '{print $2}')

# 连接数
CONNECTIONS=$(mysql -u root -p"${DB_PASSWORD}" -e "SHOW GLOBAL STATUS LIKE 'Threads_connected'" -s --skip-column-names | awk '{print $2}')
MAX_CONNECTIONS=$(mysql -u root -p"${DB_PASSWORD}" -e "SHOW VARIABLES LIKE 'max_connections'" -s --skip-column-names | awk '{print $2}')
CONNECTION_PCT=$(( CONNECTIONS * 100 / MAX_CONNECTIONS ))

# 查询缓存命中率（如果启用）
# QCACHE_HITS=$(mysql -u root -p"${DB_PASSWORD}" -e "SHOW GLOBAL STATUS LIKE 'Qcache_hits'" -s --skip-column-names | awk '{print $2}')

echo "[$(date)] DB Stats - Slow Queries: $SLOW_QUERIES, Connections: $CONNECTIONS/$MAX_CONNECTIONS (${CONNECTION_PCT}%)"

# 告警阈值
if [ "$CONNECTION_PCT" -gt 80 ]; then
    echo "ALERT: Database connections at ${CONNECTION_PCT}%"
fi
```

### 3.3 Laravel Telescope / Horizon 监控

#### 3.3.1 Telescope 数据管理

```bash
cd /var/www/jerseyholic/api

# 清理 Telescope 旧数据（保留最近 48 小时）
php artisan telescope:prune --hours=48

# 清理所有 Telescope 数据
php artisan telescope:clear

# 查看 Telescope 数据量
php artisan tinker --execute="
\$counts = [
    'entries' => DB::table('telescope_entries')->count(),
    'monitoring' => DB::table('telescope_monitoring')->count(),
];
dd(\$counts);
"
```

#### 3.3.2 Horizon 指标监控

```bash
# 查看队列吞吐量
php artisan horizon:snapshot

# 在 Redis 中查看 Horizon 数据
redis-cli

# 查看最近指标
ZREVRANGE horizon:metrics:throughput 0 10 WITHSCORES
ZREVRANGE horizon:metrics:runtime 0 10 WITHSCORES

# 查看当前进程信息
HGETALL horizon:master:supervisor-1

exit
```

### 3.4 监控 Dashboard 配置

#### 3.4.1 关键监控指标汇总

| 类别 | 指标 | 正常范围 | 警告阈值 | 严重阈值 |
|-----|------|---------|---------|---------|
| 系统 | CPU 使用率 | < 50% | 70% | 85% |
| 系统 | 内存使用率 | < 60% | 80% | 90% |
| 系统 | 磁盘使用率 | < 70% | 85% | 95% |
| 系统 | 负载平均 | < CPU核心数 | CPU核心数×1.5 | CPU核心数×2 |
| 应用 | API 响应时间 | < 200ms | 500ms | 1000ms |
| 应用 | 队列深度 | < 100 | 500 | 1000 |
| 应用 | 失败任务数 | 0 | < 10/小时 | > 50/小时 |
| 数据库 | 连接数 | < 70% | 80% | 90% |
| 数据库 | 慢查询/分钟 | < 5 | 20 | 50 |

---

## 4. 常见故障排查

### 4.1 数据库连接失败

#### 症状
- 应用返回 500 错误
- 日志中出现 `SQLSTATE[HY000] [2002] Connection refused`
- 页面显示 "Database connection error"

#### 排查步骤

```bash
# 1. 检查 MySQL 服务状态
sudo systemctl status mysql
sudo systemctl status mariadb

# 2. 检查 MySQL 端口
sudo netstat -tlnp | grep 3306
sudo ss -tlnp | grep 3306

# 3. 测试本地连接
mysql -u root -p -e "SELECT 1"

# 4. 检查连接数
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';"

# 5. 检查错误日志
sudo tail -f /var/log/mysql/error.log

# 6. 测试应用数据库配置
cd /var/www/jerseyholic/api
php artisan tinker --execute="
try {
    DB::connection('central')->select('SELECT 1');
    echo 'Central DB: OK\n';
} catch (\Exception \$e) {
    echo 'Central DB: ' . \$e->getMessage() . '\n';
}
"
```

#### 解决方案

```bash
# 重启 MySQL
sudo systemctl restart mysql

# 增加最大连接数（临时）
mysql -u root -p -e "SET GLOBAL max_connections = 500;"

# 增加最大连接数（永久）
sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf << 'EOF'
[mysqld]
max_connections = 500
EOF
sudo systemctl restart mysql

# 检查并终止长时间运行的查询
mysql -u root -p -e "SHOW PROCESSLIST;"
mysql -u root -p -e "KILL <process_id>;"
```

### 4.2 Redis 连接异常

#### 症状
- 缓存不生效
- 队列无法处理
- 日志中出现 `Connection refused` 或 `NOAUTH`

#### 排查步骤

```bash
# 1. 检查 Redis 服务状态
sudo systemctl status redis
sudo systemctl status redis-server

# 2. 测试 Redis 连接
redis-cli ping

# 3. 检查 Redis 配置
cat /etc/redis/redis.conf | grep -E "^(port|bind|requirepass)"

# 4. 查看 Redis 日志
sudo tail -f /var/log/redis/redis-server.log

# 5. 检查连接数
redis-cli INFO clients

# 6. 检查内存使用
redis-cli INFO memory
```

#### 解决方案

```bash
# 重启 Redis
sudo systemctl restart redis

# 清除所有数据（谨慎使用）
redis-cli FLUSHALL

# 检查配置文件
cat /var/www/jerseyholic/api/.env | grep REDIS

# 测试 Laravel Redis 连接
cd /var/www/jerseyholic/api
php artisan tinker --execute="
try {
    Redis::ping();
    echo 'Redis: OK\n';
} catch (\Exception \$e) {
    echo 'Redis: ' . \$e->getMessage() . '\n';
}
"
```

### 4.3 队列阻塞

#### 症状
- 任务长时间未处理
- 队列深度持续增长
- Horizon Dashboard 显示进程繁忙

#### 排查步骤

```bash
# 1. 检查队列深度
redis-cli LLEN queues:default
redis-cli LLEN queues:high

# 2. 检查 Horizon 状态
cd /var/www/jerseyholic/api
php artisan horizon:status

# 3. 检查 Supervisor 进程
sudo supervisorctl status | grep jh-

# 4. 查看失败任务
php artisan queue:failed

# 5. 检查队列 worker 日志
sudo tail -f /var/log/supervisor/jh-horizon.log

# 6. 查看最近处理的任务
redis-cli LRANGE queues:default 0 10
```

#### 解决方案

```bash
# 重启 Horizon
sudo supervisorctl restart jh-horizon

# 增加 worker 数量（临时）
php artisan queue:work --queue=high,default --sleep=3 --tries=3 &

# 清理失败任务
php artisan queue:flush

# 重新推送失败任务
php artisan queue:retry all

# 检查是否有死锁
cd /var/www/jerseyholic/api
php artisan tinker --execute="
\$processes = DB::select('SHOW PROCESSLIST');
foreach (\$processes as \$process) {
    if (\$process->Command === 'Sleep' && \$process->Time > 300) {
        echo 'Long sleep: ' . \$process->Id . '\n';
    }
}
"
```

### 4.4 磁盘空间满

#### 症状
- 无法写入文件
- 数据库报错 "Disk full"
- 应用返回 500 错误

#### 排查步骤

```bash
# 1. 查看磁盘使用情况
df -h

# 2. 查看目录大小
du -sh /var/www/jerseyholic/*
du -sh /var/log/*

# 3. 查找大文件
find /var/www/jerseyholic -type f -size +100M -exec ls -lh {} \;
find /var/log -type f -size +100M -exec ls -lh {} \;

# 4. 查看日志目录大小
du -sh /var/www/jerseyholic/api/storage/logs/
du -sh /var/log/nginx/

# 5. 查找临时文件
find /tmp -type f -atime +7 -size +10M
```

#### 解决方案

```bash
# 清理日志
> /var/www/jerseyholic/api/storage/logs/laravel.log
find /var/www/jerseyholic/api/storage/logs/ -name "*.log" -mtime +7 -delete

# 清理 Nginx 日志
> /var/log/nginx/access.log
> /var/log/nginx/error.log

# 清理系统日志
sudo journalctl --vacuum-time=7d

# 清理包管理器缓存
sudo apt-get clean
sudo apt-get autoremove

# 清理 Docker（如果使用）
docker system prune -a

# 清理旧备份（保留最近 7 天）
find /var/backups/jerseyholic -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
```

### 4.5 SSL 证书过期

#### 症状
- 浏览器显示 "证书已过期" 警告
- HTTPS 请求失败
- 证书监控告警

#### 排查步骤

```bash
# 1. 检查证书过期时间
echo | openssl s_client -servername jerseyholic.xyz -connect jerseyholic.xyz:443 2>/dev/null | openssl x509 -noout -dates

# 2. 检查证书详情
echo | openssl s_client -servername jerseyholic.xyz -connect jerseyholic.xyz:443 2>/dev/null | openssl x509 -noout -text

# 3. 检查 Certbot 状态
sudo certbot certificates

# 4. 检查自动续期定时任务
cat /etc/cron.d/certbot
cat /etc/cron.daily/certbot

# 5. 测试续期（模拟）
sudo certbot renew --dry-run
```

#### 解决方案

```bash
# 手动续期证书
sudo certbot renew --force-renewal

# 重启 Nginx 加载新证书
sudo systemctl reload nginx

# 检查续期日志
sudo cat /var/log/letsencrypt/letsencrypt.log

# 设置自动续期（如果未设置）
echo "0 3 * * * root certbot renew --quiet --deploy-hook 'systemctl reload nginx'" | sudo tee /etc/cron.d/certbot-renew
```

### 4.6 502/504 网关错误

#### 症状
- 页面显示 "502 Bad Gateway" 或 "504 Gateway Timeout"
- Nginx 错误日志显示 upstream 错误

#### 排查步骤

```bash
# 1. 检查 Nginx 错误日志
sudo tail -f /var/log/nginx/error.log

# 2. 检查 PHP-FPM 状态
sudo systemctl status php8.2-fpm
sudo systemctl status php-fpm

# 3. 检查 PHP-FPM 进程
ps aux | grep php-fpm

# 4. 检查 PHP-FPM 监听配置
cat /etc/php/8.2/fpm/pool.d/www.conf | grep "listen ="

# 5. 检查 Nginx upstream 配置
cat /etc/nginx/sites-available/jerseyholic-wildcard.conf | grep -A5 "location.*php"

# 6. 测试 PHP-FPM 响应
sudo systemctl reload php8.2-fpm
```

#### 解决方案

```bash
# 重启 PHP-FPM
sudo systemctl restart php8.2-fpm

# 重启 Nginx
sudo systemctl restart nginx

# 增加 PHP-FPM 子进程数
sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf << 'EOF'
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
EOF
sudo systemctl restart php8.2-fpm

# 增加 Nginx 超时时间
sudo tee /etc/nginx/conf.d/timeout.conf << 'EOF'
proxy_connect_timeout 600;
proxy_send_timeout 600;
proxy_read_timeout 600;
send_timeout 600;
fastcgi_connect_timeout 600;
fastcgi_send_timeout 600;
fastcgi_read_timeout 600;
EOF
sudo nginx -t && sudo systemctl reload nginx
```

---

## 5. 扩容方案

### 5.1 Web 节点水平扩展

#### 5.1.1 Nginx Upstream 配置

```nginx
# /etc/nginx/conf.d/upstream.conf
upstream jerseyholic_backend {
    least_conn;  # 使用最少连接算法
    
    server 192.168.1.10:80 weight=5 max_fails=3 fail_timeout=30s;
    server 192.168.1.11:80 weight=5 max_fails=3 fail_timeout=30s;
    server 192.168.1.12:80 weight=3 max_fails=3 fail_timeout=30s backup;
    
    keepalive 32;
}

server {
    listen 80;
    server_name admin.jerseyholic.xyz;
    
    location / {
        proxy_pass http://jerseyholic_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }
}
```

#### 5.1.2 会话共享配置

```bash
# 使用 Redis 存储会话
# 修改 .env
SESSION_DRIVER=redis
SESSION_CONNECTION=session
SESSION_LIFETIME=120

# 配置 Redis 连接
cat >> /var/www/jerseyholic/api/.env << 'EOF'
REDIS_SESSION_HOST=192.168.1.20
REDIS_SESSION_PORT=6379
REDIS_SESSION_DB=1
EOF

# 清除配置缓存
cd /var/www/jerseyholic/api
php artisan config:cache
```

#### 5.1.3 文件存储共享

```bash
# 使用 NFS 共享存储
# 在 NFS 服务器上
sudo apt-get install nfs-kernel-server
sudo mkdir -p /exports/jerseyholic-storage
sudo tee /etc/exports << 'EOF'
/exports/jerseyholic-storage 192.168.1.0/24(rw,sync,no_subtree_check,no_root_squash)
EOF
sudo exportfs -a

# 在 Web 节点上
sudo apt-get install nfs-common
sudo mkdir -p /var/www/jerseyholic/api/storage/app/public
sudo mount 192.168.1.30:/exports/jerseyholic-storage /var/www/jerseyholic/api/storage/app/public

# 添加到 fstab
echo "192.168.1.30:/exports/jerseyholic-storage /var/www/jerseyholic/api/storage/app/public nfs defaults 0 0" | sudo tee -a /etc/fstab
```

### 5.2 数据库读写分离

#### 5.2.1 MySQL 主从配置

```bash
# 主服务器配置 /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin
binlog_do_db = jerseyholic_central
binlog_do_db = store_%

# 从服务器配置 /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
server-id = 2
relay_log = /var/log/mysql/mysql-relay-bin
log_bin = /var/log/mysql/mysql-bin
read_only = 1
```

#### 5.2.2 Laravel 读写分离配置

```php
// config/database.php
'central' => [
    'driver' => 'mysql',
    'read' => [
        'host' => [
            '192.168.1.21',
            '192.168.1.22',
        ],
    ],
    'write' => [
        'host' => [
            '192.168.1.20',
        ],
    ],
    'sticky' => true,
    'database' => env('DB_CENTRAL_DATABASE', 'jerseyholic_central'),
    // ... 其他配置
],
```

### 5.3 Redis Cluster

#### 5.3.1 Redis Cluster 搭建

```bash
# 创建 6 个 Redis 实例（3 主 3 从）
# 端口：7000-7005

for port in 7000 7001 7002 7003 7004 7005; do
    mkdir -p /var/redis/$port
    cat > /var/redis/$port/redis.conf << EOF
port $port
cluster-enabled yes
cluster-config-file nodes-$port.conf
cluster-node-timeout 5000
appendonly yes
dir /var/redis/$port
EOF
done

# 启动所有节点
for port in 7000 7001 7002 7003 7004 7005; do
    redis-server /var/redis/$port/redis.conf
done

# 创建集群
redis-cli --cluster create \
    127.0.0.1:7000 127.0.0.1:7001 127.0.0.1:7002 \
    127.0.0.1:7003 127.0.0.1:7004 127.0.0.1:7005 \
    --cluster-replicas 1
```

#### 5.3.2 Laravel Redis Cluster 配置

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),
    
    'clusters' => [
        'default' => [
            [
                'host' => '192.168.1.30',
                'port' => 7000,
            ],
            [
                'host' => '192.168.1.31',
                'port' => 7000,
            ],
            [
                'host' => '192.168.1.32',
                'port' => 7000,
            ],
        ],
    ],
    
    'options' => [
        'cluster' => 'redis',
        'parameters' => [
            'password' => env('REDIS_PASSWORD', null),
        ],
    ],
],
```

### 5.4 CDN 静态资源加速

#### 5.4.1 配置 CloudFront/Cloudflare

```bash
# 修改 Laravel 使用 CDN URL
# .env
ASSET_URL=https://cdn.jerseyholic.xyz

# 清除配置缓存
cd /var/www/jerseyholic/api
php artisan config:cache
```

#### 5.4.2 Nginx 静态资源缓存

```nginx
# /etc/nginx/conf.d/static-cache.conf
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary "Accept-Encoding";
    
    access_log off;
    log_not_found off;
}

# 图片优化
location ~* \.(jpg|jpeg|png|gif)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    
    # 可选：启用图片压缩
    # gzip_static on;
}
```

---

## 6. 安全运维

### 6.1 依赖安全审计

#### 6.1.1 Composer Audit

```bash
cd /var/www/jerseyholic/api

# 检查 PHP 依赖漏洞
composer audit

# 详细报告
composer audit --format=json > /var/log/jerseyholic/composer-audit-$(date +%Y%m%d).json

# 自动修复（如果可能）
composer audit --fix

# 更新单个包
composer update vendor/package --no-dev --optimize-autoloader
```

#### 6.1.2 NPM Audit

```bash
# 检查前端依赖漏洞
cd /var/www/jerseyholic/admin-ui
npm audit

cd /var/www/jerseyholic/merchant-ui
npm audit

cd /var/www/jerseyholic/storefront
npm audit

# 生成报告
npm audit --json > /var/log/jerseyholic/npm-audit-$(date +%Y%m%d).json

# 自动修复
npm audit fix

# 强制修复（可能包含破坏性变更）
npm audit fix --force
```

#### 6.1.3 定时审计脚本

```bash
#!/bin/bash
# /usr/local/bin/security-audit.sh

LOG_DIR="/var/log/jerseyholic/security"
mkdir -p "$LOG_DIR"
DATE=$(date +%Y%m%d)

echo "[$(date)] Starting security audit..."

# PHP 依赖审计
cd /var/www/jerseyholic/api
composer audit --format=json > "$LOG_DIR/composer-audit-$DATE.json" 2>&1
if [ $? -ne 0 ]; then
    echo "ALERT: Composer audit found vulnerabilities"
    # 发送告警
fi

# 前端依赖审计
for dir in admin-ui merchant-ui storefront; do
    cd "/var/www/jerseyholic/$dir"
    npm audit --json > "$LOG_DIR/npm-audit-$dir-$DATE.json" 2>&1 || true
done

echo "[$(date)] Security audit completed."
```

### 6.2 SSL 证书自动续期

#### 6.2.1 Certbot 自动续期配置

```bash
# 安装 Certbot
sudo apt-get install certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d jerseyholic.xyz -d *.jerseyholic.xyz

# 测试自动续期
sudo certbot renew --dry-run

# 查看证书信息
sudo certbot certificates
```

#### 6.2.2 续期钩子脚本

```bash
#!/bin/bash
# /etc/letsencrypt/renewal-hooks/deploy/jerseyholic.sh

# 重新加载 Nginx
systemctl reload nginx

# 通知监控
# curl -X POST https://monitoring.example.com/webhook/cert-renewed

# 记录日志
echo "[$(date)] SSL certificate renewed for JerseyHolic" >> /var/log/jerseyholic/ssl-renewal.log
```

### 6.3 防火墙规则

#### 6.3.1 UFW 配置

```bash
# 安装 UFW
sudo apt-get install ufw

# 默认策略
sudo ufw default deny incoming
sudo ufw default allow outgoing

# 允许 SSH
sudo ufw allow 22/tcp

# 允许 HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 允许特定 IP 访问管理端口
sudo ufw allow from 192.168.1.0/24 to any port 22

# 启用防火墙
sudo ufw enable

# 查看状态
sudo ufw status verbose
```

#### 6.3.2 高级防火墙规则

```bash
# 限制连接速率（防 DDoS）
sudo ufw limit 80/tcp
sudo ufw limit 443/tcp

# 拒绝特定 IP
sudo ufw deny from 192.0.2.100

# 删除规则
sudo ufw delete allow 80/tcp

# 重置防火墙
sudo ufw reset
```

### 6.4 Fail2ban 配置

#### 6.4.1 安装与基础配置

```bash
# 安装 Fail2ban
sudo apt-get install fail2ban

# 创建本地配置
sudo tee /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
backend = systemd

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
port = http,https
logpath = /var/log/nginx/error.log

[nginx-botsearch]
enabled = true
filter = nginx-botsearch
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 10
EOF

# 重启服务
sudo systemctl restart fail2ban
```

#### 6.4.2 自定义 Laravel 登录保护

```bash
# 创建过滤器
sudo tee /etc/fail2ban/filter.d/jerseyholic-auth.conf << 'EOF'
[Definition]
failregex = ^.*Failed login attempt.*from <HOST>.*$
            ^.*Invalid credentials.*from <HOST>.*$
ignoreregex =
EOF

# 添加到 jail.local
sudo tee -a /etc/fail2ban/jail.local << 'EOF'

[jerseyholic-auth]
enabled = true
filter = jerseyholic-auth
port = http,https
logpath = /var/www/jerseyholic/api/storage/logs/laravel.log
maxretry = 5
findtime = 300
bantime = 1800
EOF

sudo systemctl restart fail2ban
```

#### 6.4.3 Fail2ban 管理

```bash
# 查看状态
sudo fail2ban-client status

# 查看特定 jail 状态
sudo fail2ban-client status sshd

# 查看被封禁的 IP
sudo fail2ban-client status sshd | grep "Banned IP list"

# 解封 IP
sudo fail2ban-client set sshd unbanip 192.0.2.100

# 查看日志
sudo tail -f /var/log/fail2ban.log
```

---

## 7. 紧急响应流程

### 7.1 事件分级 (L1-L4)

| 级别 | 名称 | 定义 | 响应时间 | 解决时间 |
|-----|------|------|---------|---------|
| L1 | 严重 (Critical) | 系统完全不可用，所有用户受影响 | 5 分钟 | 1 小时 |
| L2 | 高 (High) | 核心功能故障（支付/订单），大量用户受影响 | 15 分钟 | 4 小时 |
| L3 | 中 (Medium) | 非核心功能故障，部分用户受影响 | 1 小时 | 24 小时 |
| L4 | 低 (Low) | 轻微问题，个别用户受影响 | 4 小时 | 72 小时 |

### 7.2 响应时间 SLA

| 指标 | L1 | L2 | L3 | L4 |
|-----|----|----|----|----|
| 首次响应 | 5 分钟 | 15 分钟 | 1 小时 | 4 小时 |
| 临时解决 | 30 分钟 | 2 小时 | 8 小时 | 24 小时 |
| 完全解决 | 1 小时 | 4 小时 | 24 小时 | 72 小时 |
| 事后复盘 | 24 小时内 | 48 小时内 | 1 周内 | 可选 |

### 7.3 联系人列表模板

```markdown
## JerseyHolic 紧急联系人

### 技术团队

| 角色 | 姓名 | 电话 | 邮箱 | 值班时间 |
|-----|------|------|------|---------|
| 技术负责人 | ______ | ______ | ______ | 7×24 |
| 运维工程师 | ______ | ______ | ______ | 7×24 |
| DBA | ______ | ______ | ______ | 工作日 9:00-21:00 |
| 后端开发 | ______ | ______ | ______ | 工作日 9:00-18:00 |
| 前端开发 | ______ | ______ | ______ | 工作日 9:00-18:00 |

### 业务团队

| 角色 | 姓名 | 电话 | 邮箱 | 职责 |
|-----|------|------|------|------|
| 产品经理 | ______ | ______ | ______ | 业务决策 |
| 运营负责人 | ______ | ______ | ______ | 用户通知 |
| 客服负责人 | ______ | ______ | ______ | 客诉处理 |

### 外部供应商

| 供应商 | 服务 | 联系人 | 电话 | 邮箱 |
|-------|------|--------|------|------|
| 阿里云/腾讯云 | 云服务器 | ______ | ______ | ______ |
| 域名服务商 | DNS | ______ | ______ | ______ |
| CDN 服务商 | 加速 | ______ | ______ | ______ |
| 支付服务商 | 支付通道 | ______ | ______ | ______ |

### 沟通渠道

- 紧急热线：________
- 企业微信群：________
- 钉钉群：________
- 邮件列表：________
```

### 7.4 事后复盘模板

```markdown
# 事后复盘报告 (Post-Mortem)

## 基本信息

- **事件编号**: INC-YYYYMMDD-XXX
- **事件时间**: YYYY-MM-DD HH:MM ~ YYYY-MM-DD HH:MM (UTC+8)
- **事件级别**: L1/L2/L3/L4
- **报告人**: ______
- **审核人**: ______
- **报告日期**: YYYY-MM-DD

## 事件摘要

一句话描述：________

## 时间线

| 时间 | 事件 | 处理人 |
|-----|------|--------|
| HH:MM | 监控告警/用户反馈 | ______ |
| HH:MM | 运维介入排查 | ______ |
| HH:MM | 确定故障原因 | ______ |
| HH:MM | 执行修复措施 | ______ |
| HH:MM | 服务恢复 | ______ |

## 影响范围

- **受影响用户**: ______
- **受影响功能**: ______
- **业务损失**: ______
- **数据影响**: ______

## 根因分析

### 直接原因
________

### 深层原因
________

### 触发条件
________

## 解决方案

### 临时方案（已执行）
________

### 长期方案（计划中）
- [ ] ______
- [ ] ______
- [ ] ______

## 经验教训

### 做得好的
- ______

### 需要改进的
- ______

## 预防措施

- [ ] 监控告警优化
- [ ] 自动化测试补充
- [ ] 文档更新
- [ ] 流程改进
- [ ] 其他：________

## 相关链接

- 监控图表：________
- 日志文件：________
- 代码变更：________
- 工单链接：________
```

### 7.5 紧急响应检查清单

```bash
# 事件响应检查清单

## 发现阶段
□ 确认事件真实性
□ 初步评估影响范围
□ 确定事件级别 (L1-L4)
□ 通知相关人员

## 响应阶段
□ 建立事件响应群/会议
□ 收集日志和监控数据
□ 分析根因
□ 制定修复方案

## 修复阶段
□ 执行修复操作
□ 验证修复效果
□ 监控服务恢复情况
□ 通知用户（如需要）

## 恢复阶段
□ 确认服务完全恢复
□ 解除告警
□ 更新状态页面
□ 收集用户反馈

## 复盘阶段
□ 整理事件时间线
□ 编写事后复盘报告
□ 制定改进措施
□ 更新应急预案
```

---

## 附录

### A. 常用命令速查

```bash
# 应用维护
php artisan down --message="系统维护中" --retry=60
php artisan up

# 缓存管理
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# 队列管理
php artisan horizon:status
php artisan horizon:terminate
sudo supervisorctl restart all

# 数据库
php artisan migrate:status
php artisan migrate:rollback --step=1

# 日志查看
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/error.log

# 系统监控
df -h
free -h
top
htop
```

### B. 关键路径

| 项目 | 路径 |
|-----|------|
| 应用代码 | `/var/www/jerseyholic` |
| API 目录 | `/var/www/jerseyholic/api` |
| 环境配置 | `/var/www/jerseyholic/api/.env` |
| Nginx 配置 | `/etc/nginx/sites-available/` |
| PHP-FPM 配置 | `/etc/php/8.2/fpm/pool.d/` |
| Supervisor 配置 | `/etc/supervisor/conf.d/` |
| 日志文件 | `/var/www/jerseyholic/api/storage/logs/` |
| 系统日志 | `/var/log/nginx/`, `/var/log/mysql/` |
| 数据库备份 | `/var/backups/jerseyholic/database/` |
| 文件备份 | `/var/backups/jerseyholic/files/` |

---

**最后更新**: 2026-04-18  
**文档版本**: v1.0  
**审核人**: _______________
