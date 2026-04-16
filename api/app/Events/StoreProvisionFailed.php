<?php

namespace App\Events;

use App\Models\Central\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 站点创建失败事件
 *
 * 当 StoreProvisioningService::provision() 过程中发生异常时触发。
 */
class StoreProvisionFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?Store $store,
        public readonly \Throwable $exception,
        public readonly array $context = []
    ) {}
}
