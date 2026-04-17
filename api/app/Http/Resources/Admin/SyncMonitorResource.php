<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理端同步监控 Resource
 *
 * 用于管理端查看商户同步概况的输出格式。
 */
class SyncMonitorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'merchant_id'           => $this->resource['merchant_id'] ?? null,
            'merchant_name'         => $this->resource['merchant_name'] ?? null,
            'store_count'           => $this->resource['store_count'] ?? 0,
            'total_syncs'           => $this->resource['total_syncs'] ?? 0,
            'success_rate'          => $this->resource['success_rate'] ?? 0.0,
            'last_sync_at'          => $this->resource['last_sync_at'] ?? null,
            'recent_failures_count' => $this->resource['recent_failures_count'] ?? 0,
        ];
    }
}
