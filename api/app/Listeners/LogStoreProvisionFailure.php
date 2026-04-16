<?php

namespace App\Listeners;

use App\Events\StoreProvisionFailed;
use Illuminate\Support\Facades\Log;

/**
 * 记录站点创建失败的详细日志
 */
class LogStoreProvisionFailure
{
    /**
     * Handle the event.
     */
    public function handle(StoreProvisionFailed $event): void
    {
        Log::error('[StoreProvisionFailed] Store provisioning failed.', [
            'store_id'  => $event->store?->id,
            'error'     => $event->exception->getMessage(),
            'exception' => get_class($event->exception),
            'trace'     => $event->exception->getTraceAsString(),
            'context'   => $event->context,
        ]);
    }
}
