<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Central\SettlementRecord;
use App\Models\Central\Store;
use App\Models\Tenant\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 退款对结算影响服务（M3-015）
 *
 * 处理退款发生后对结算单的影响：
 *  - draft / pending_review 状态的结算单：直接扣减
 *  - approved / paid 状态的结算单：记录为下期抵扣
 *  - 不在任何结算单中：等下次生成时自动包含
 *
 * 金额计算全程使用 bcmath，精度 2 位。
 */
class RefundImpactService
{
    /** bcmath 金额精度 */
    private const AMOUNT_SCALE = 2;

    /** 退款影响类型 */
    public const IMPACT_DEDUCTED = 'deducted';   // 直接从当期扣减
    public const IMPACT_DEFERRED = 'deferred';   // 延迟到下期抵扣
    public const IMPACT_PENDING  = 'pending';    // 未关联结算单，等待后续生成

    /**
     * 处理退款对结算单的影响
     *
     * 逻辑：
     *  1. 查找订单所属的结算周期内的结算单（非取消状态）
     *  2. 根据结算单当前状态决定扣减方式
     *  3. 记录退款影响到 jh_settlement_refund_adjustments 表
     *
     * @param  int    $orderId      订单 ID
     * @param  string $refundAmount 退款金额（bcmath 字符串）
     * @param  int|string $storeId   店铺 ID
     * @return array{impact_type: string, settlement_id: ?int, amount: string}
     */
    public function processRefund(int $orderId, string $refundAmount, int|string $storeId): array
    {
        // 通过 Store 查找 merchant_id
        $store = Store::findOrFail($storeId);
        $merchantId = $store->merchant_id;

        // 查找该商户最近的、覆盖当前时间的非取消结算单
        $settlement = SettlementRecord::where('merchant_id', $merchantId)
            ->whereNotIn('status', [
                SettlementService::STATUS_CANCELLED,
            ])
            ->where('period_start', '<=', now()->toDateString())
            ->where('period_end', '>=', now()->toDateString())
            ->latest('id')
            ->first();

        if ($settlement === null) {
            // 不在任何结算单中，记录退款待后续生成时自动包含
            Log::info('[RefundImpactService] No active settlement found, refund pending.', [
                'order_id'      => $orderId,
                'store_id'      => $storeId,
                'merchant_id'   => $merchantId,
                'refund_amount' => $refundAmount,
            ]);

            $this->recordAdjustment(
                settlementId: null,
                merchantId:   $merchantId,
                orderId:      $orderId,
                amount:       $refundAmount,
                type:         self::IMPACT_PENDING,
            );

            return [
                'impact_type'   => self::IMPACT_PENDING,
                'settlement_id' => null,
                'amount'        => $refundAmount,
            ];
        }

        // 根据结算单状态决定处理方式
        $impactType = match ($settlement->status) {
            SettlementService::STATUS_DRAFT,
            SettlementService::STATUS_PENDING_REVIEW => self::IMPACT_DEDUCTED,

            SettlementService::STATUS_APPROVED,
            SettlementService::STATUS_PAID => self::IMPACT_DEFERRED,

            default => self::IMPACT_PENDING,
        };

        return DB::connection('central')->transaction(function () use (
            $settlement, $orderId, $merchantId, $refundAmount, $impactType,
        ): array {
            if ($impactType === self::IMPACT_DEDUCTED) {
                // 直接扣减结算单净额
                $newNetAmount = bcsub($settlement->net_amount, $refundAmount, self::AMOUNT_SCALE);
                $settlement->update([
                    'net_amount' => $newNetAmount,
                ]);

                Log::info('[RefundImpactService] Refund deducted from settlement.', [
                    'settlement_id' => $settlement->id,
                    'order_id'      => $orderId,
                    'refund_amount' => $refundAmount,
                    'new_net'       => $newNetAmount,
                ]);
            } else {
                // deferred: 记录为下期抵扣
                Log::info('[RefundImpactService] Refund deferred to next period.', [
                    'settlement_id' => $settlement->id,
                    'order_id'      => $orderId,
                    'refund_amount' => $refundAmount,
                ]);
            }

            $this->recordAdjustment(
                settlementId: $settlement->id,
                merchantId:   $merchantId,
                orderId:      $orderId,
                amount:       $refundAmount,
                type:         $impactType,
            );

            return [
                'impact_type'   => $impactType,
                'settlement_id' => $settlement->id,
                'amount'        => $refundAmount,
            ];
        });
    }

    /**
     * 应用历史未抵扣退款到当前结算期
     *
     * 查询 type = deferred 且未应用的退款调整记录，
     * 累加总额作为当期扣减，并标记为已应用。
     *
     * @param  int    $merchantId  商户 ID
     * @param  string $periodStart 当前结算期开始（Y-m-d）
     * @param  string $periodEnd   当前结算期结束（Y-m-d）
     * @return string 总抵扣金额（bcmath）
     */
    public function applyDeferredRefunds(int $merchantId, string $periodStart, string $periodEnd): string
    {
        $deferredRecords = DB::connection('central')
            ->table('settlement_refund_adjustments')
            ->where('merchant_id', $merchantId)
            ->where('type', self::IMPACT_DEFERRED)
            ->where('applied', false)
            ->get();

        if ($deferredRecords->isEmpty()) {
            return '0.00';
        }

        $totalDeduction = '0.00';

        // 查找当前周期的 draft/pending_review 结算单
        $currentSettlement = SettlementRecord::where('merchant_id', $merchantId)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->whereIn('status', [
                SettlementService::STATUS_DRAFT,
                SettlementService::STATUS_PENDING_REVIEW,
            ])
            ->first();

        return DB::connection('central')->transaction(function () use (
            $deferredRecords, $currentSettlement, &$totalDeduction,
        ): string {
            foreach ($deferredRecords as $record) {
                $totalDeduction = bcadd($totalDeduction, (string) $record->amount, self::AMOUNT_SCALE);

                DB::connection('central')
                    ->table('settlement_refund_adjustments')
                    ->where('id', $record->id)
                    ->update([
                        'applied'    => true,
                        'applied_at' => now(),
                    ]);
            }

            // 如果存在当期结算单，扣减净额
            if ($currentSettlement !== null) {
                $newNetAmount = bcsub(
                    (string) $currentSettlement->net_amount,
                    $totalDeduction,
                    self::AMOUNT_SCALE,
                );
                $currentSettlement->update(['net_amount' => $newNetAmount]);

                Log::info('[RefundImpactService] Deferred refunds applied to current settlement.', [
                    'settlement_id'   => $currentSettlement->id,
                    'total_deduction' => $totalDeduction,
                    'new_net_amount'  => $newNetAmount,
                ]);
            }

            return $totalDeduction;
        });
    }

    /**
     * 获取商户退款汇总
     *
     * @param  int $merchantId
     * @return array{total_refunded: string, pending_deduction: string, applied_deduction: string}
     */
    public function getRefundSummary(int $merchantId): array
    {
        $adjustments = DB::connection('central')
            ->table('settlement_refund_adjustments')
            ->where('merchant_id', $merchantId)
            ->get();

        $totalRefunded     = '0.00';
        $pendingDeduction  = '0.00';
        $appliedDeduction  = '0.00';

        foreach ($adjustments as $adj) {
            $amount = (string) $adj->amount;
            $totalRefunded = bcadd($totalRefunded, $amount, self::AMOUNT_SCALE);

            if ($adj->type === self::IMPACT_DEFERRED && !$adj->applied) {
                $pendingDeduction = bcadd($pendingDeduction, $amount, self::AMOUNT_SCALE);
            }

            if ($adj->applied) {
                $appliedDeduction = bcadd($appliedDeduction, $amount, self::AMOUNT_SCALE);
            }
        }

        return [
            'total_refunded'     => $totalRefunded,
            'pending_deduction'  => $pendingDeduction,
            'applied_deduction'  => $appliedDeduction,
        ];
    }

    /* ================================================================
     |  私有方法
     | ================================================================ */

    /**
     * 记录退款调整到 settlement_refund_adjustments 表
     *
     * @param  int|null $settlementId
     * @param  int      $merchantId
     * @param  int      $orderId
     * @param  string   $amount
     * @param  string   $type
     * @return void
     */
    private function recordAdjustment(
        ?int   $settlementId,
        int    $merchantId,
        int    $orderId,
        string $amount,
        string $type,
    ): void {
        DB::connection('central')
            ->table('settlement_refund_adjustments')
            ->insert([
                'settlement_id' => $settlementId,
                'merchant_id'   => $merchantId,
                'order_id'      => $orderId,
                'amount'        => $amount,
                'type'          => $type,
                'applied'       => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
    }
}
