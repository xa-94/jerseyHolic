<?php

namespace App\Services\Payment;

use App\DTOs\CommissionResult;
use App\Enums\OrderPaymentStatus;
use App\Models\Central\Merchant;
use App\Models\Central\SettlementDetail;
use App\Models\Central\SettlementRecord;
use App\Models\Central\Store;
use App\Models\Tenant\Dispute;
use App\Models\Tenant\Order;
use App\Models\Tenant\Refund;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 结算核心服务（M3-013）
 *
 * 负责按商户维度聚合跨租户订单数据，调用 CommissionService 计算佣金，
 * 生成 SettlementRecord + SettlementDetail 月结单据。
 *
 * 金额计算全部使用 bcmath，禁止浮点运算。
 */
class SettlementService
{
    /** bcmath 金额精度 */
    private const AMOUNT_SCALE = 2;

    /** 单次聚合最大站点数 */
    private const MAX_STORES_PER_BATCH = 50;

    /** 结算单状态 */
    public const STATUS_DRAFT          = 0;
    public const STATUS_PENDING_REVIEW = 1;
    public const STATUS_PAID           = 2;
    public const STATUS_CANCELLED      = 3;
    public const STATUS_APPROVED       = 4;
    public const STATUS_REJECTED       = 5;

    /** @deprecated Use STATUS_PENDING_REVIEW instead */
    public const STATUS_CONFIRMED = self::STATUS_PENDING_REVIEW;

    /** 状态标签 */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT          => 'draft',
        self::STATUS_PENDING_REVIEW => 'pending_review',
        self::STATUS_PAID           => 'paid',
        self::STATUS_CANCELLED      => 'cancelled',
        self::STATUS_APPROVED       => 'approved',
        self::STATUS_REJECTED       => 'rejected',
    ];

    /** 允许的状态转换映射 */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT          => [self::STATUS_PENDING_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_PENDING_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED       => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_REJECTED       => [self::STATUS_CANCELLED],
        self::STATUS_PAID           => [],
        self::STATUS_CANCELLED      => [],
    ];

    public function __construct(
        private readonly CommissionService  $commissionService,
        private readonly NotificationService $notificationService,
    ) {}

    /* ================================================================
     |  核心：为指定商户生成结算单（月结）
     | ================================================================ */

    /**
     * 为指定商户生成结算单
     *
     * 流程：
     *  1. 获取商户所有活跃 Store
     *  2. 遍历每个 Store，使用 Store::run() 切入租户上下文聚合订单数据
     *  3. 回到 Central 上下文，调用 CommissionService 计算佣金
     *  4. 创建 SettlementRecord（draft）+ SettlementDetail
     *
     * @param  int    $merchantId  商户 ID
     * @param  Carbon $periodStart 结算周期开始（含）
     * @param  Carbon $periodEnd   结算周期结束（含）
     * @return SettlementRecord
     *
     * @throws \RuntimeException 商户不存在或无活跃店铺时
     */
    public function generateForMerchant(int $merchantId, Carbon $periodStart, Carbon $periodEnd): SettlementRecord
    {
        $merchant = Merchant::findOrFail($merchantId);

        $stores = $merchant->stores()
            ->where('status', 1)
            ->limit(self::MAX_STORES_PER_BATCH)
            ->get();

        if ($stores->isEmpty()) {
            Log::warning('[SettlementService] Merchant has no active stores.', [
                'merchant_id' => $merchantId,
            ]);
        }

        // 幂等检查：同一商户同一周期只允许一个非取消的结算单
        $existing = SettlementRecord::where('merchant_id', $merchantId)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->first();

        if ($existing !== null) {
            Log::info('[SettlementService] Settlement already exists, returning existing.', [
                'settlement_id' => $existing->id,
                'merchant_id'   => $merchantId,
            ]);
            return $existing;
        }

        // 聚合各店铺订单数据
        $storeAggregations = [];
        foreach ($stores as $store) {
            $storeAggregations[$store->id] = $this->aggregateStoreOrders($store, $periodStart, $periodEnd);
        }

        // 汇总商户级 GMV
        $totalGmv         = '0.00';
        $totalOrderCount  = 0;
        $totalRefund      = '0.00';
        $totalDisputeFrozen = '0.00';

        foreach ($storeAggregations as $agg) {
            $totalGmv           = bcadd($totalGmv, $agg['gmv'], self::AMOUNT_SCALE);
            $totalOrderCount   += $agg['order_count'];
            $totalRefund        = bcadd($totalRefund, $agg['refund_amount'], self::AMOUNT_SCALE);
            $totalDisputeFrozen = bcadd($totalDisputeFrozen, $agg['dispute_frozen'], self::AMOUNT_SCALE);
        }

        // 调用 CommissionService 计算佣金（以商户级 GMV 整体计算）
        $commissionResult = $this->commissionService->calculate(
            merchantId: $merchantId,
            storeId:    null,
            amount:     $totalGmv,
            monthlyGmv: (float) $totalGmv,
        );

        $commissionAmount = $commissionResult->commissionAmount;

        // 净结算额 = GMV - 佣金 - 退款 - 争议冻结
        $netAmount = bcsub($totalGmv, $commissionAmount, self::AMOUNT_SCALE);
        $netAmount = bcsub($netAmount, $totalRefund, self::AMOUNT_SCALE);
        $netAmount = bcsub($netAmount, $totalDisputeFrozen, self::AMOUNT_SCALE);

        // 在事务中创建结算单 + 明细
        return DB::connection('central')->transaction(function () use (
            $merchantId, $periodStart, $periodEnd,
            $totalGmv, $commissionAmount, $netAmount, $totalOrderCount,
            $storeAggregations, $commissionResult,
        ) {
            $record = SettlementRecord::create([
                'merchant_id'       => $merchantId,
                'settlement_no'     => $this->generateSettlementNo($merchantId),
                'total_amount'      => $totalGmv,
                'commission_amount' => $commissionAmount,
                'net_amount'        => $netAmount,
                'order_count'       => $totalOrderCount,
                'period_start'      => $periodStart->toDateString(),
                'period_end'        => $periodEnd->toDateString(),
                'status'            => self::STATUS_DRAFT,
            ]);

            // 为每个 Store 创建明细
            foreach ($storeAggregations as $storeId => $agg) {
                // 按店铺维度计算佣金（使用相同有效费率）
                $storeCommission = bcmul(
                    $agg['gmv'],
                    bcdiv($commissionResult->effectiveRate, '100', 4),
                    self::AMOUNT_SCALE
                );

                $storeNet = bcsub($agg['gmv'], $storeCommission, self::AMOUNT_SCALE);
                $storeNet = bcsub($storeNet, $agg['refund_amount'], self::AMOUNT_SCALE);
                $storeNet = bcsub($storeNet, $agg['dispute_frozen'], self::AMOUNT_SCALE);

                SettlementDetail::create([
                    'settlement_id'     => $record->id,
                    'store_id'          => $storeId,
                    'order_count'       => $agg['order_count'],
                    'total_amount'      => $agg['gmv'],
                    'commission_amount' => $storeCommission,
                    'net_amount'        => $storeNet,
                    'currency'          => 'USD',
                ]);
            }

            Log::info('[SettlementService] Settlement generated.', [
                'settlement_id'    => $record->id,
                'merchant_id'      => $merchantId,
                'period'           => $periodStart->toDateString() . ' ~ ' . $periodEnd->toDateString(),
                'total_amount'     => $totalGmv,
                'commission'       => $commissionAmount,
                'effective_rate'   => $commissionResult->effectiveRate,
                'net_amount'       => $record->net_amount,
                'order_count'      => $record->order_count,
                'store_count'      => count($storeAggregations),
            ]);

            return $record;
        });
    }

    /* ================================================================
     |  批量生成
     | ================================================================ */

    /**
     * 为所有活跃商户批量生成结算单
     *
     * @param  Carbon $periodStart
     * @param  Carbon $periodEnd
     * @return Collection<SettlementRecord>
     */
    public function generateForAllMerchants(Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $merchants = Merchant::where('status', 1)->get();
        $results   = collect();

        foreach ($merchants as $merchant) {
            try {
                $record = $this->generateForMerchant($merchant->id, $periodStart, $periodEnd);
                $results->push($record);
            } catch (\Throwable $e) {
                Log::error('[SettlementService] Failed to generate settlement for merchant.', [
                    'merchant_id' => $merchant->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('[SettlementService] Batch settlement generation completed.', [
            'total_merchants' => $merchants->count(),
            'success_count'   => $results->count(),
            'period'          => $periodStart->toDateString() . ' ~ ' . $periodEnd->toDateString(),
        ]);

        return $results;
    }

    /* ================================================================
     |  查询方法
     | ================================================================ */

    /**
     * 获取结算单列表（Admin 视角）
     *
     * @param  array $filters  可选 keys: merchant_id, status, period_start, period_end, keyword
     * @param  int   $perPage
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SettlementRecord::with('merchant:id,merchant_name,email');

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', (int) $filters['merchant_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['period_start'])) {
            $query->where('period_start', '>=', $filters['period_start']);
        }

        if (!empty($filters['period_end'])) {
            $query->where('period_end', '<=', $filters['period_end']);
        }

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('settlement_no', 'like', $kw)
                  ->orWhereHas('merchant', function ($mq) use ($kw) {
                      $mq->where('merchant_name', 'like', $kw);
                  });
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * 获取结算单详情（含明细）
     *
     * @param  int $settlementId
     * @return SettlementRecord
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getDetail(int $settlementId): SettlementRecord
    {
        return SettlementRecord::with(['merchant:id,merchant_name,email', 'details.store:id,store_name,store_code'])
            ->findOrFail($settlementId);
    }

    /**
     * 获取商户的结算单列表（Merchant 视角）
     *
     * @param  int   $merchantId
     * @param  array $filters  可选 keys: status, period_start, period_end
     * @param  int   $perPage
     * @return LengthAwarePaginator
     */
    public function listForMerchant(int $merchantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SettlementRecord::where('merchant_id', $merchantId);

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['period_start'])) {
            $query->where('period_start', '>=', $filters['period_start']);
        }

        if (!empty($filters['period_end'])) {
            $query->where('period_end', '<=', $filters['period_end']);
        }

        return $query->latest()->paginate($perPage);
    }

    /* ================================================================
     |  审核流程方法（M3-014）
     | ================================================================ */

    /**
     * 提交结算单审核
     *
     * 状态流转：draft → pending_review
     * 提交后通知所有管理员有新的结算单待审核。
     *
     * @param  int $settlementId
     * @return SettlementRecord
     *
     * @throws \InvalidArgumentException 当前状态不允许提交审核
     */
    public function submitForReview(int $settlementId): SettlementRecord
    {
        $record = SettlementRecord::findOrFail($settlementId);

        $this->assertTransition($record->status, self::STATUS_PENDING_REVIEW);

        $record->update([
            'status' => self::STATUS_PENDING_REVIEW,
        ]);

        Log::info('[SettlementService] Settlement submitted for review.', [
            'settlement_id' => $settlementId,
            'settlement_no' => $record->settlement_no,
        ]);

        $this->notificationService->sendToAdmin(
            title:   '新结算单待审核',
            content: "结算单 {$record->settlement_no}（商户 #{$record->merchant_id}）已提交审核，金额：{$record->net_amount} USD",
            type:    NotificationService::TYPE_SETTLEMENT,
        );

        return $record->refresh();
    }

    /**
     * 审核通过结算单
     *
     * 状态流转：pending_review → approved
     * 审核通过后通知商户。
     *
     * @param  int $settlementId
     * @param  int $adminId       审核管理员 ID
     * @return SettlementRecord
     *
     * @throws \InvalidArgumentException 当前状态不允许审核通过
     */
    public function approve(int $settlementId, int $adminId): SettlementRecord
    {
        $record = SettlementRecord::findOrFail($settlementId);

        $this->assertTransition($record->status, self::STATUS_APPROVED);

        $record->update([
            'status'      => self::STATUS_APPROVED,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);

        Log::info('[SettlementService] Settlement approved.', [
            'settlement_id' => $settlementId,
            'admin_id'      => $adminId,
        ]);

        $this->notificationService->sendToMerchant(
            merchantId: $record->merchant_id,
            title:      '结算单审核通过',
            content:    "您的结算单 {$record->settlement_no} 已审核通过，净结算额：{$record->net_amount} USD，请等待打款。",
            type:       NotificationService::TYPE_SETTLEMENT,
        );

        return $record->refresh();
    }

    /**
     * 审核拒绝结算单
     *
     * 状态流转：pending_review → rejected
     * 拒绝后通知商户并记录原因。
     *
     * @param  int    $settlementId
     * @param  int    $adminId  审核管理员 ID
     * @param  string $reason   拒绝原因
     * @return SettlementRecord
     *
     * @throws \InvalidArgumentException 当前状态不允许拒绝
     */
    public function reject(int $settlementId, int $adminId, string $reason): SettlementRecord
    {
        $record = SettlementRecord::findOrFail($settlementId);

        $this->assertTransition($record->status, self::STATUS_REJECTED);

        $record->update([
            'status'      => self::STATUS_REJECTED,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'remark'      => $reason,
        ]);

        Log::info('[SettlementService] Settlement rejected.', [
            'settlement_id' => $settlementId,
            'admin_id'      => $adminId,
            'reason'        => $reason,
        ]);

        $this->notificationService->sendToMerchant(
            merchantId: $record->merchant_id,
            title:      '结算单审核被拒绝',
            content:    "您的结算单 {$record->settlement_no} 审核未通过，原因：{$reason}",
            type:       NotificationService::TYPE_SETTLEMENT,
            level:      NotificationService::LEVEL_WARNING,
        );

        return $record->refresh();
    }

    /**
     * 标记结算单已打款
     *
     * 状态流转：approved → paid
     * 打款后通知商户。
     *
     * @param  int         $settlementId
     * @param  int         $adminId         操作管理员 ID
     * @param  string|null $transactionRef  打款流水号
     * @return SettlementRecord
     *
     * @throws \InvalidArgumentException 当前状态不允许标记打款
     */
    public function markAsPaid(int $settlementId, int $adminId, ?string $transactionRef = null): SettlementRecord
    {
        $record = SettlementRecord::findOrFail($settlementId);

        $this->assertTransition($record->status, self::STATUS_PAID);

        $record->update([
            'status'          => self::STATUS_PAID,
            'settled_at'      => now(),
            'transaction_ref' => $transactionRef,
        ]);

        Log::info('[SettlementService] Settlement marked as paid.', [
            'settlement_id'   => $settlementId,
            'admin_id'        => $adminId,
            'transaction_ref' => $transactionRef,
        ]);

        $this->notificationService->sendToMerchant(
            merchantId: $record->merchant_id,
            title:      '结算款已打款',
            content:    "您的结算单 {$record->settlement_no} 已完成打款，金额：{$record->net_amount} USD" . ($transactionRef ? "，流水号：{$transactionRef}" : ''),
            type:       NotificationService::TYPE_SETTLEMENT,
        );

        return $record->refresh();
    }

    /**
     * 取消结算单
     *
     * 除 paid 状态外，任何状态均可取消。
     *
     * @param  int    $settlementId
     * @param  int    $adminId  操作管理员 ID
     * @param  string $reason   取消原因
     * @return SettlementRecord
     *
     * @throws \InvalidArgumentException 当前状态不允许取消
     */
    public function cancel(int $settlementId, int $adminId, string $reason): SettlementRecord
    {
        $record = SettlementRecord::findOrFail($settlementId);

        $this->assertTransition($record->status, self::STATUS_CANCELLED);

        $record->update([
            'status' => self::STATUS_CANCELLED,
            'remark' => $reason,
        ]);

        Log::info('[SettlementService] Settlement cancelled.', [
            'settlement_id' => $settlementId,
            'admin_id'      => $adminId,
            'reason'        => $reason,
        ]);

        $this->notificationService->sendToMerchant(
            merchantId: $record->merchant_id,
            title:      '结算单已取消',
            content:    "您的结算单 {$record->settlement_no} 已取消，原因：{$reason}",
            type:       NotificationService::TYPE_SETTLEMENT,
            level:      NotificationService::LEVEL_WARNING,
        );

        return $record->refresh();
    }

    /* ================================================================
     |  私有方法
     | ================================================================ */

    /**
     * 跨租户聚合单个 Store 的订单数据
     *
     * 使用 Store::run() 切入 Tenant DB 上下文执行聚合查询。
     *
     * @param  Store  $store
     * @param  Carbon $start
     * @param  Carbon $end
     * @return array{order_count: int, gmv: string, refund_amount: string, dispute_frozen: string}
     */
    private function aggregateStoreOrders(Store $store, Carbon $start, Carbon $end): array
    {
        $result = [
            'order_count'    => 0,
            'gmv'            => '0.00',
            'refund_amount'  => '0.00',
            'dispute_frozen' => '0.00',
        ];

        $store->run(function () use (&$result, $start, $end) {
            // 已支付订单（pay_status = PAID）
            $paidOrders = Order::whereBetween('created_at', [$start, $end])
                ->where('pay_status', OrderPaymentStatus::PAID);

            $result['order_count'] = $paidOrders->count();

            // GMV = 已支付订单的 total 字段求和
            $gmvRaw = $paidOrders->sum('total');
            $result['gmv'] = bcadd((string) $gmvRaw, '0', 2);

            // 退款金额：周期内已完成的退款（status = 1 表示已完成）
            $refundRaw = Refund::whereBetween('created_at', [$start, $end])
                ->where('status', 1)
                ->sum('amount');
            $result['refund_amount'] = bcadd((string) $refundRaw, '0', 2);

            // 争议冻结金额：周期内未结的争议（open scope: status in [1, 2]）
            $disputeRaw = Dispute::whereBetween('created_at', [$start, $end])
                ->open()
                ->sum('amount');
            $result['dispute_frozen'] = bcadd((string) $disputeRaw, '0', 2);
        });

        return $result;
    }

    /**
     * 生成结算单编号
     *
     * 格式：STL-{YYYYMM}-{MerchantId(4位)}-{随机6位大写}
     *
     * @param  int $merchantId
     * @return string
     */
    private function generateSettlementNo(int $merchantId): string
    {
        return sprintf(
            'STL-%s-%04d-%s',
            now()->format('Ym'),
            $merchantId,
            strtoupper(Str::random(6))
        );
    }

    /**
     * 校验状态转换是否合法
     *
     * @param  int $currentStatus  当前状态
     * @param  int $targetStatus   目标状态
     * @return void
     *
     * @throws \InvalidArgumentException 状态转换非法
     */
    private function assertTransition(int $currentStatus, int $targetStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($targetStatus, $allowed, true)) {
            $currentLabel = self::STATUS_LABELS[$currentStatus] ?? 'unknown';
            $targetLabel  = self::STATUS_LABELS[$targetStatus] ?? 'unknown';

            throw new \InvalidArgumentException(
                "Invalid status transition: {$currentLabel}({$currentStatus}) → {$targetLabel}({$targetStatus})"
            );
        }
    }
}
