<?php

namespace App\Services;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantApiKey;
use App\Models\Central\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 商户状态级联服务
 *
 * 负责商户状态变更时对名下站点、API 密钥、资金冻结的级联处理。
 *
 * 级联规则：
 *  suspended（4）→ 所有 active(1) 站点改为 maintenance(2)
 *  banned（5）    → 所有站点改为 inactive(0)，冻结资金 180 天，吊销 active API 密钥
 *  active（1）    → 因暂停导致 maintenance 的站点恢复为 active（通过 meta 标记区分）
 *
 * Store.status 约定：
 *  0 = inactive
 *  1 = active
 *  2 = maintenance（暂停导致）
 */
class MerchantStatusCascadeService
{
    /**
     * 根据新状态级联处理商户名下站点
     *
     * @param  Merchant $merchant
     * @param  string   $newStatus  目标状态字符串（active/suspended/banned/…）
     * @return void
     */
    public function cascadeStatus(Merchant $merchant, string $newStatus): void
    {
        DB::connection('central')->transaction(function () use ($merchant, $newStatus) {
            match ($newStatus) {
                'suspended' => $this->handleSuspend($merchant),
                'banned'    => $this->handleBan($merchant),
                'active'    => $this->handleReactivation($merchant),
                default     => null,   // pending/rejected/info_required 无需级联
            };
        });
    }

    /**
     * 处理商户暂停：active 站点 → maintenance
     *
     * @param  Merchant $merchant
     * @return void
     */
    private function handleSuspend(Merchant $merchant): void
    {
        $affected = $merchant->stores()
            ->where('status', 1)   // active
            ->get();

        if ($affected->isEmpty()) {
            return;
        }

        // 批量更新为 maintenance(2)
        $merchant->stores()
            ->where('status', 1)
            ->update(['status' => 2]);

        Log::info('[MerchantStatusCascade] Stores set to maintenance due to merchant suspension.', [
            'merchant_id'   => $merchant->id,
            'affected_count' => $affected->count(),
            'store_ids'     => $affected->pluck('id')->toArray(),
        ]);
    }

    /**
     * 处理商户封禁：所有站点 → inactive，冻结资金，吊销 API 密钥
     *
     * @param  Merchant $merchant
     * @return void
     */
    private function handleBan(Merchant $merchant): void
    {
        // 1. 所有站点置为 inactive(0)
        $affectedStores = $merchant->stores()
            ->whereIn('status', [1, 2])  // active 或 maintenance 的均置为 inactive
            ->get();

        if ($affectedStores->isNotEmpty()) {
            $merchant->stores()
                ->whereIn('status', [1, 2])
                ->update(['status' => 0]);
        }

        // 2. 冻结资金 180 天
        $this->handleBanEffects($merchant);

        Log::info('[MerchantStatusCascade] Merchant banned: stores deactivated, funds frozen, API keys revoked.', [
            'merchant_id'        => $merchant->id,
            'deactivated_stores' => $affectedStores->pluck('id')->toArray(),
            'fund_frozen_until'  => now()->addDays(180)->toIso8601String(),
        ]);
    }

    /**
     * 封禁时的附加处理：冻结资金 + 吊销 API 密钥 + 记录日志
     *
     * @param  Merchant $merchant
     * @return void
     */
    public function handleBanEffects(Merchant $merchant): void
    {
        // 2a. 冻结资金 180 天
        $merchant->update([
            'fund_frozen_until' => now()->addDays(180),
        ]);

        // 2b. 吊销所有 active 的 API 密钥
        $revokedCount = MerchantApiKey::where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->update([
                'status'        => 'revoked',
                'revoked_at'    => now(),
                'revoke_reason' => 'Merchant banned',
            ]);

        Log::warning('[MerchantStatusCascade] Merchant ban effects applied.', [
            'merchant_id'         => $merchant->id,
            'fund_frozen_until'   => now()->addDays(180)->toIso8601String(),
            'revoked_api_keys'    => $revokedCount,
        ]);
    }

    /**
     * 处理商户解除暂停/恢复 active：maintenance 站点恢复为 active
     *
     * 注意：不自动恢复已吊销的 API 密钥（需管理员手动操作）。
     *
     * @param  Merchant $merchant
     * @return void
     */
    public function handleReactivation(Merchant $merchant): void
    {
        // 仅恢复因暂停而处于 maintenance(2) 状态的站点，不触碰 inactive(0)
        $affected = $merchant->stores()
            ->where('status', 2)   // maintenance
            ->get();

        if ($affected->isEmpty()) {
            return;
        }

        $merchant->stores()
            ->where('status', 2)
            ->update(['status' => 1]);   // 恢复为 active

        Log::info('[MerchantStatusCascade] Stores restored to active after merchant reactivation.', [
            'merchant_id'    => $merchant->id,
            'restored_count' => $affected->count(),
            'store_ids'      => $affected->pluck('id')->toArray(),
        ]);
    }

    /**
     * 获取将受影响的站点列表（供前端预览确认）
     *
     * @param  Merchant $merchant
     * @return Collection<int, Store>
     */
    public function getAffectedStores(Merchant $merchant): Collection
    {
        return $merchant->stores()
            ->whereIn('status', [0, 1, 2])
            ->get(['id', 'store_name', 'store_code', 'domain', 'status', 'merchant_id']);
    }
}
