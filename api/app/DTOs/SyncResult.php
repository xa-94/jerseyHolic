<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\Carbon;

/**
 * 单商品同步结果 DTO
 *
 * 封装一次 MasterProduct → Tenant Product 同步的完整结果。
 * 使用 static factory 方法 success() / failure() 构建实例。
 */
class SyncResult
{
    public function __construct(
        /** 是否成功 */
        public readonly bool $success,
        /** 来源 MasterProduct ID */
        public readonly int $masterProductId,
        /** 目标 Store ID */
        public readonly int $storeId,
        /** 同步后 Tenant 侧商品 ID（失败时为 null） */
        public readonly ?int $tenantProductId,
        /** 同步完成时间 */
        public readonly Carbon $syncedAt,
        /** 错误信息列表（成功时为空数组） */
        public readonly array $errors = [],
    ) {}

    /**
     * 构建成功结果
     */
    public static function success(
        int $masterProductId,
        int $storeId,
        int $tenantProductId,
    ): self {
        return new self(
            success: true,
            masterProductId: $masterProductId,
            storeId: $storeId,
            tenantProductId: $tenantProductId,
            syncedAt: Carbon::now(),
        );
    }

    /**
     * 构建失败结果
     */
    public static function failure(
        int $masterProductId,
        int $storeId,
        array $errors,
    ): self {
        return new self(
            success: false,
            masterProductId: $masterProductId,
            storeId: $storeId,
            tenantProductId: null,
            syncedAt: Carbon::now(),
            errors: $errors,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'success'            => $this->success,
            'master_product_id'  => $this->masterProductId,
            'store_id'           => $this->storeId,
            'tenant_product_id'  => $this->tenantProductId,
            'synced_at'          => $this->syncedAt->toIso8601String(),
            'errors'             => $this->errors,
        ];
    }
}
