<?php

namespace App\Events;

use App\Models\Central\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 站点删除事件
 *
 * 当 StoreProvisioningService::deprovision() 完成后触发。
 */
class StoreDeprovisioned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Store $store
    ) {}
}
