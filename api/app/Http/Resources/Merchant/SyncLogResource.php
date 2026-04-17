<?php

declare(strict_types=1);

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商户端同步日志 Resource
 *
 * 用于商户查看同步日志列表和详情的输出格式。
 */
class SyncLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'store_id'         => $this->target_store_id,
            'store_name'       => $this->whenLoaded('store', fn () => $this->store?->store_name),
            'sync_type'        => $this->sync_type,
            'trigger'          => $this->trigger,
            'status'           => $this->status,
            'total_products'   => $this->total_products,
            'synced_products'  => $this->synced_products,
            'failed_products'  => $this->failed_products,
            'duration_ms'      => $this->duration_ms, // 计算属性
            'error_message'    => $this->summarizeErrors(),
            'started_at'       => $this->started_at?->toIso8601String(),
            'completed_at'     => $this->completed_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * 从 error_log JSON 中提取摘要信息
     */
    protected function summarizeErrors(): ?string
    {
        if (empty($this->error_log)) {
            return null;
        }

        if (is_array($this->error_log)) {
            // 取第一条错误作为摘要
            $first = reset($this->error_log);

            return is_string($first) ? $first : json_encode($first, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
