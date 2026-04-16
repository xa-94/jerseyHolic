<?php

namespace App\Events;

use App\Models\Central\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 站点创建成功事件
 *
 * 当 StoreProvisioningService::provision() 完成全部流程后触发。
 */
class StoreProvisioned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Store $store
    ) {}
}
