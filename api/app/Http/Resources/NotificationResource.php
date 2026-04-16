<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 通知 API Resource（Admin 和 Merchant 共用）
 *
 * 用于格式化 Notification 模型输出。
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'user_type'  => $this->user_type,
            'user_id'    => $this->user_id,
            'type'       => $this->type,
            'title'      => $this->title,
            'content'    => $this->content,
            'channel'    => $this->channel,
            'is_read'    => (bool) $this->is_read,
            'read_at'    => $this->read_at?->format('Y-m-d H:i:s'),
            'metadata'   => $this->metadata,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
