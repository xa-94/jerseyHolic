<?php

namespace App\Services;

use App\Models\Central\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 商户专属数据库管理服务
 *
 * 负责商户数据库（jerseyholic_merchant_{id}）的完整生命周期：
 * - 创建数据库并初始化核心表（master_products, master_product_translations, sync_rules）
 * - 销毁数据库（商户封禁时可用）
 * - 检查数据库是否存在
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
                    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `sku`              VARCHAR(100)    NOT NULL COMMENT 'SKU编码',
                    `name`             VARCHAR(255)    NOT NULL COMMENT '商品名称',
                    `description`      LONGTEXT        NULL     COMMENT '商品描述',
                    `brand`            VARCHAR(100)    NULL     COMMENT '品牌',
                    `safe_name`        VARCHAR(255)    NULL     COMMENT '审核通过的安全名称',
                    `safe_description` LONGTEXT        NULL     COMMENT '审核通过的安全描述',
                    `images`           JSON            NULL     COMMENT '原始图片URL列表',
                    `safe_images`      JSON            NULL     COMMENT '审核通过的图片URL列表',
                    `price`            DECIMAL(10, 2)  NOT NULL DEFAULT 0.00 COMMENT '售价',
                    `cost`             DECIMAL(10, 2)  NULL     COMMENT '成本价',
                    `status`           TINYINT         NOT NULL DEFAULT 0 COMMENT '0=draft,1=active,2=inactive',
                    `created_at`       TIMESTAMP       NULL,
                    `updated_at`       TIMESTAMP       NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_sku` (`sku`),
                    KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='商户主商品库'
            ");

            // 3. 创建 master_product_translations 表
            DB::connection('central')->statement("
                CREATE TABLE IF NOT EXISTS `{$dbName}`.`master_product_translations` (
                    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `product_id`  BIGINT UNSIGNED NOT NULL COMMENT '关联 master_products.id',
                    `locale`      VARCHAR(10)     NOT NULL COMMENT '语言代码，如 en, zh, es',
                    `name`        VARCHAR(255)    NOT NULL COMMENT '翻译后商品名称',
                    `description` LONGTEXT        NULL     COMMENT '翻译后商品描述',
                    `created_at`  TIMESTAMP       NULL,
                    `updated_at`  TIMESTAMP       NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_product_locale` (`product_id`, `locale`),
                    KEY `idx_locale` (`locale`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='商品多语言翻译'
            ");

            // 4. 创建 sync_rules 表
            DB::connection('central')->statement("
                CREATE TABLE IF NOT EXISTS `{$dbName}`.`sync_rules` (
                    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name`             VARCHAR(100)    NOT NULL COMMENT '规则名称',
                    `source_type`      VARCHAR(50)     NOT NULL COMMENT '数据来源类型',
                    `target_store_ids` JSON            NULL     COMMENT '目标店铺ID列表',
                    `sync_fields`      JSON            NULL     COMMENT '需同步的字段列表',
                    `auto_sync`        TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '是否自动同步',
                    `status`           TINYINT         NOT NULL DEFAULT 1 COMMENT '0=disabled,1=enabled',
                    `created_at`       TIMESTAMP       NULL,
                    `updated_at`       TIMESTAMP       NULL,
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
}
