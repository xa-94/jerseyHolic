<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 站点创建/销毁过程中的异常
 *
 * 包含预定义的错误码常量，用于区分不同的失败场景。
 */
class StoreProvisioningException extends RuntimeException
{
    /* ----------------------------------------------------------------
     |  错误码常量
     | ---------------------------------------------------------------- */

    /** 商户未激活 */
    public const MERCHANT_INACTIVE = 'MERCHANT_INACTIVE';

    /** 站点配额已满 */
    public const QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';

    /** 域名已被占用 */
    public const DOMAIN_TAKEN = 'DOMAIN_TAKEN';

    /** 数据库创建失败 */
    public const DB_CREATION_FAILED = 'DB_CREATION_FAILED';

    /** 迁移执行失败 */
    public const MIGRATION_FAILED = 'MIGRATION_FAILED';

    /** Seeder 执行失败 */
    public const SEED_FAILED = 'SEED_FAILED';

    /** 站点仍有未完成订单，无法删除 */
    public const HAS_PENDING_ORDERS = 'HAS_PENDING_ORDERS';

    /**
     * 错误码
     */
    protected string $errorCode;

    /**
     * @param string          $errorCode 错误码常量
     * @param string          $message   可读描述
     * @param int             $code      HTTP 状态码（默认 422）
     * @param \Throwable|null $previous  上一个异常
     */
    public function __construct(
        string $errorCode,
        string $message = '',
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message ?: $errorCode, $code, $previous);
    }

    /**
     * 获取错误码
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /* ----------------------------------------------------------------
     |  工厂方法 — 便捷创建
     | ---------------------------------------------------------------- */

    public static function merchantInactive(int $merchantId): static
    {
        return new static(
            self::MERCHANT_INACTIVE,
            "Merchant #{$merchantId} is not active."
        );
    }

    public static function quotaExceeded(int $merchantId, string $level, int $limit): static
    {
        return new static(
            self::QUOTA_EXCEEDED,
            "Merchant #{$merchantId} (level: {$level}) has reached the store limit of {$limit}."
        );
    }

    public static function domainTaken(string $domain): static
    {
        return new static(
            self::DOMAIN_TAKEN,
            "Domain '{$domain}' is already taken."
        );
    }

    public static function dbCreationFailed(string $dbName, ?\Throwable $previous = null): static
    {
        return new static(
            self::DB_CREATION_FAILED,
            "Failed to create tenant database '{$dbName}'.",
            422,
            $previous
        );
    }

    public static function migrationFailed(string $dbName, ?\Throwable $previous = null): static
    {
        return new static(
            self::MIGRATION_FAILED,
            "Failed to run migrations on tenant database '{$dbName}'.",
            422,
            $previous
        );
    }

    public static function hasPendingOrders(int $storeId): static
    {
        return new static(
            self::HAS_PENDING_ORDERS,
            "Store #{$storeId} still has pending orders and cannot be deprovisioned."
        );
    }
}
