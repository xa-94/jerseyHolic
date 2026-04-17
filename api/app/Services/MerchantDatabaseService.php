<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\Merchant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 商户专属数据库管理服务
 *
 * 负责商户数据库（jerseyholic_merchant_{id}）的完整生命周期：
 * - 创建数据库并初始化核心表（master_products, master_product_translations, sync_rules）
 * - 销毁数据库（商户封禁时可用）
 * - 检查数据库是否存在
 * - 切换 merchant 数据库连接上下文
 *
 * 所有 DDL 语句在 central 连接对应的 MySQL 实例上执行，
 * 使用 DB::statement() 原生 SQL，不依赖 ORM。
 */
class MerchantDatabaseService
{
    /**
     * 商户数据库名称前缀
     */
    protected const DB_PREFIX = 'jerseyholic_merchant_';

    /* ================================================================
     |  公开方法
     | ================================================================ */

    /**
     * 为商户创建专属数据库并初始化核心表
     *
     * 流程：
     * 1. CREATE DATABASE（若已存在则跳过）
     * 2. 创建 master_products 主商品库
     * 3. 创建 master_product_translations 商品翻译
     * 4. 创建 sync_rules 同步规则
     *
     * @param  Merchant $merchant
     * @return void
     * @throws \RuntimeException  数据库创建失败时
     */
    public function createMerchantDatabase(Merchant $merchant): void
    {
        $dbName = $this->getDatabaseName($merchant);

        Log::info('[MerchantDatabase] Creating merchant database.', [
            'merchant_id' => $merchant->id,
            'db_name'     => $dbName,
        ]);

        try {
            // 1. 创建数据库（使用 central 连接所在 MySQL 实例）
            DB::connection('central')->statement(
                "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            // 2. 创建 master_products 表
            DB::connection('central')->statement("
                CREATE TABLE IF NOT EXISTS `{$dbName}`.`master_products` (
                    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                    `sku`             VARCHAR(100)     NOT NULL COMMENT 'SKU编码',
                    `name`            VARCHAR(255)     NOT NULL COMMENT '默认名称（英文）',
                    `description`     TEXT             NULL     COMMENT '默认描述',
                    `category_l1_id`  BIGINT UNSIGNED  NULL     COMMENT 'Central DB L1品类ID',
                    `category_l2_id`  BIGINT UNSIGNED  NULL     COMMENT 'Central DB L2品类ID',
                    `is_sensitive`    TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '是否特货',
                    `base_price`      DECIMAL(10, 2)   NOT NULL COMMENT '基础价格',
                    `currency`        VARCHAR(3)       NOT NULL DEFAULT 'USD' COMMENT '币种',
                    `images`          JSON             NULL     COMMENT '图片URL数组',
                    `attributes`      JSON             NULL     COMMENT '商品属性（颜色、尺码等）',
                    `variants`        JSON             NULL     COMMENT '变体信息',
                    `weight`          DECIMAL(8, 2)    NULL     COMMENT '重量(g)',
                    `dimensions`      JSON             NULL     COMMENT '尺寸 {length,width,height}',
                    `status`          TINYINT          NOT NULL DEFAULT 1 COMMENT '1=active,0=inactive,2=draft',
                    `sync_status`     VARCHAR(20)      NOT NULL DEFAULT 'pending' COMMENT 'pending/syncing/synced/failed',
                    `last_synced_at`  TIMESTAMP        NULL     COMMENT '最后同步时间',
                    `created_at`      TIMESTAMP        NULL,
                    `updated_at`      TIMESTAMP        NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_sku` (`sku`),
                    KEY `idx_category_l1` (`category_l1_id`),
                    KEY `idx_category_l2` (`category_l2_id`),
                    KEY `idx_status` (`status`),
                    KEY `idx_sync_status` (`sync_status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='商户主商品库'
            ");

            // 3. 创建 master_product_translations 表
            DB::connection('central')->statement("
                CREATE TABLE IF NOT EXISTS `{$dbName}`.`master_product_translations` (
                    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `master_product_id`  BIGINT UNSIGNED NOT NULL COMMENT '关联 master_products.id',
                    `locale`             VARCHAR(10)     NOT NULL COMMENT '语言代码，如 en, zh, de',
                    `name`               VARCHAR(255)    NOT NULL COMMENT '翻译后商品名称',
                    `description`        TEXT            NULL     COMMENT '翻译后商品描述',
                    `meta_title`         VARCHAR(255)    NULL     COMMENT 'SEO标题',
                    `meta_description`   TEXT            NULL     COMMENT 'SEO描述',
                    `created_at`         TIMESTAMP       NULL,
                    `updated_at`         TIMESTAMP       NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_product_locale` (`master_product_id`, `locale`),
                    KEY `idx_locale` (`locale`),
                    CONSTRAINT `fk_translation_product`
                        FOREIGN KEY (`master_product_id`) REFERENCES `{$dbName}`.`master_products` (`id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='商品多语言翻译'
            ");

            // 4. 创建 sync_rules 表
            DB::connection('central')->statement("
                CREATE TABLE IF NOT EXISTS `{$dbName}`.`sync_rules` (
                    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name`              VARCHAR(100)    NOT NULL COMMENT '规则名称',
                    `target_store_ids`  JSON            NOT NULL COMMENT '目标站点ID列表',
                    `excluded_store_ids` JSON           NULL     COMMENT '排除站点',
                    `sync_fields`       JSON            NOT NULL COMMENT '要同步的字段列表',
                    `price_strategy`    VARCHAR(20)     NOT NULL DEFAULT 'fixed' COMMENT 'fixed/multiplier/custom',
                    `price_multiplier`  DECIMAL(5, 2)   NOT NULL DEFAULT 1.00 COMMENT '价格乘数',
                    `auto_sync`         TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '自动同步开关',
                    `status`            TINYINT         NOT NULL DEFAULT 1 COMMENT '1=enabled,0=disabled',
                    `last_synced_at`    TIMESTAMP       NULL     COMMENT '最后同步时间',
                    `sync_interval_hours` INT           NOT NULL DEFAULT 24 COMMENT '同步间隔小时数',
                    `created_at`        TIMESTAMP       NULL,
                    `updated_at`        TIMESTAMP       NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='商品同步规则'
            ");

            Log::info('[MerchantDatabase] Merchant database created successfully.', [
                'merchant_id' => $merchant->id,
                'db_name'     => $dbName,
            ]);

        } catch (\Throwable $e) {
            Log::error('[MerchantDatabase] Failed to create merchant database.', [
                'merchant_id' => $merchant->id,
                'db_name'     => $dbName,
                'error'       => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "商户数据库创建失败（{$dbName}）：{$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * 删除商户专属数据库
     *
     * 危险操作，仅在商户永久封禁时调用。
     * 调用前请确保已备份数据。
     *
     * @param  Merchant $merchant
     * @return void
     */
    public function dropMerchantDatabase(Merchant $merchant): void
    {
        $dbName = $this->getDatabaseName($merchant);

        Log::warning('[MerchantDatabase] Dropping merchant database.', [
            'merchant_id' => $merchant->id,
            'db_name'     => $dbName,
        ]);

        DB::connection('central')->statement(
            "DROP DATABASE IF EXISTS `{$dbName}`"
        );

        Log::info('[MerchantDatabase] Merchant database dropped.', [
            'merchant_id' => $merchant->id,
            'db_name'     => $dbName,
        ]);
    }

    /**
     * 检查商户专属数据库是否已存在
     *
     * @param  Merchant $merchant
     * @return bool
     */
    public function merchantDatabaseExists(Merchant $merchant): bool
    {
        $dbName = $this->getDatabaseName($merchant);

        $result = DB::connection('central')->select(
            "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?",
            [$dbName]
        );

        return !empty($result);
    }

    /**
     * 获取商户数据库名称
     *
     * 格式：jerseyholic_merchant_{merchant_id}
     *
     * @param  Merchant $merchant
     * @return string
     */
    public function getDatabaseName(Merchant $merchant): string
    {
        return self::DB_PREFIX . $merchant->id;
    }

    /**
     * 切换 merchant 数据库连接到指定商户的数据库
     *
     * 设置后，所有使用 'merchant' 连接的 Model（MerchantModel 子类）
     * 将自动连接到该商户的独立数据库。
     *
     * @param  Merchant $merchant
     * @return void
     */
    public function setMerchantConnection(Merchant $merchant): void
    {
        $dbName = $this->getDatabaseName($merchant);

        Config::set('database.connections.merchant.database', $dbName);

        // 清除已缓存的连接，强制下次查询使用新配置
        DB::purge('merchant');
    }

    /**
     * 在指定商户数据库上下文中执行回调
     *
     * 执行完毕后自动恢复原连接配置。
     *
     * @template T
     * @param  Merchant $merchant
     * @param  callable(): T $callback
     * @return T
     */
    public function run(Merchant $merchant, callable $callback): mixed
    {
        $previousDb = Config::get('database.connections.merchant.database');

        try {
            $this->setMerchantConnection($merchant);
            return $callback();
        } finally {
            Config::set('database.connections.merchant.database', $previousDb);
            DB::purge('merchant');
        }
    }
}
