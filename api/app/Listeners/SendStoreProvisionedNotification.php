<?php

namespace App\Listeners;

use App\Events\StoreProvisioned;
use Illuminate\Support\Facades\Log;

/**
 * 站点创建成功后通知商户
 *
 * 当前仅记录日志，后续可扩展为邮件/站内信通知。
 */
class SendStoreProvisionedNotification
{
    /**
     * Handle the event.
     */
    public function handle(StoreProvisioned $event): void
    {
        $store    = $event->store;
        $merchant = $store->merchant;

        Log::info('[StoreProvisioned] Store provisioned successfully. Notification pending.', [
            'store_id'    => $store->id,
            'store_name'  => $store->store_name,
            'merchant_id' => $merchant?->id,
            'email'       => $merchant?->email,
        ]);

        // TODO: 发送邮件或站内信通知商户
        // Mail::to($merchant->email)->queue(new StoreProvisionedMail($store));
    }
}
