<?php

namespace App\Services\Payment;

use App\DTOs\RiskScoreResult;
use App\Enums\OrderDisputeStatus;
use App\Enums\OrderRefundStatus;
use App\Models\Central\Blacklist;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantRiskScore;
use App\Models\Central\PaymentAccount;
use App\Models\Tenant\Order;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * 商户风险评分服务（M3-016 / M3-017）
 *
 * 5 维度加权评分（总分 0-100）：
 *  - 退款率   (30%)：30 天滑动窗口，refund / total
 *  - 争议率   (25%)：30 天滑动窗口，dispute / total
 *  - 销量异常 (20%)：日均订单量与 7 日均值偏差 > 200% 则满分
 *  - 账号健康 (15%)：商户下支付账号平均健康度取反映射
 *  - 投诉率   (10%)：30 天窗口，complaint / total
 *
 * 风险等级：
 *  0-30:  low      — 正常
 *  31-60: medium   — 关注
 *  61-80: high     — 限额下调 50%
 *  81-100: critical — 暂停交易 + 自动加黑名单
 */
class MerchantRiskService
{
    /** 各维度权重 */
    private const WEIGHT_REFUND    = 30;
    private const WEIGHT_DISPUTE   = 25;
    private const WEIGHT_VOLUME    = 20;
    private const WEIGHT_HEALTH    = 15;
    private const WEIGHT_COMPLAINT = 10;

    /** 销量异常偏差阈值（200%） */
    private const VOLUME_DEVIATION_THRESHOLD = 2.0;

    /** 30 天滑动窗口 */
    private const WINDOW_DAYS = 30;

    public function __construct(
        private readonly AccountHealthScoreService $healthScoreService,
        private readonly BlacklistService $blacklistService,
        private readonly NotificationService $notificationService,
    ) {}

    /* ----------------------------------------------------------------
     |  核心方法
     | ---------------------------------------------------------------- */

    /**
     * 计算商户风险评分
     *
     * @param  int $merchantId
     * @return RiskScoreResult
     */
    public function calculateRiskScore(int $merchantId): RiskScoreResult
    {
        $breakdown  = $this->getScoreBreakdown($merchantId);
        $totalScore = max(0, min(100, (int) round(
            $breakdown['refund_score']
            + $breakdown['dispute_score']
            + $breakdown['volume_score']
            + $breakdown['health_score']
            + $breakdown['complaint_score']
        )));

        $level   = RiskScoreResult::scoreToLevel($totalScore);
        $actions = $this->executeRiskActions($merchantId, $totalScore, $level);

        // 持久化评分
        $this->persistScore($merchantId, $totalScore, $level, $breakdown);

        return RiskScoreResult::fromScore($merchantId, $totalScore, $breakdown, $actions);
    }

    /**
     * 获取评分明细（含各维度分数）
     *
     * @param  int $merchantId
     * @return array
     */
    public function getScoreBreakdown(int $merchantId): array
    {
        $stats = $this->aggregateMerchantOrderStats($merchantId);

        $totalOrders = $stats['total_orders'];

        // ---- 退款率维度 (0-30) ----
        $refundRate  = $totalOrders > 0 ? ($stats['refund_count'] / $totalOrders) : 0;
        $refundScore = min(self::WEIGHT_REFUND, $refundRate * 100 * (self::WEIGHT_REFUND / 10));

        // ---- 争议率维度 (0-25) ----
        $disputeRate  = $totalOrders > 0 ? ($stats['dispute_count'] / $totalOrders) : 0;
        $disputeScore = min(self::WEIGHT_DISPUTE, $disputeRate * 100 * (self::WEIGHT_DISPUTE / 10));

        // ---- 销量异常维度 (0-20) ----
        $volumeScore = $this->calculateVolumeScore($stats);

        // ---- 账号健康维度 (0-15) ---- 越低越危险（取反）
        $avgHealth   = $this->getAverageAccountHealth($merchantId);
        $healthScore = self::WEIGHT_HEALTH * (1 - ($avgHealth / 100));

        // ---- 投诉率维度 (0-10) ----
        $complaintRate  = $totalOrders > 0 ? ($stats['complaint_count'] / $totalOrders) : 0;
        $complaintScore = min(self::WEIGHT_COMPLAINT, $complaintRate * 100 * (self::WEIGHT_COMPLAINT / 5));

        return [
            'refund_score'    => round($refundScore, 2),
            'dispute_score'   => round($disputeScore, 2),
            'volume_score'    => round($volumeScore, 2),
            'health_score'    => round($healthScore, 2),
            'complaint_score' => round($complaintScore, 2),
            'raw' => [
                'total_orders'    => $totalOrders,
                'refund_count'    => $stats['refund_count'],
                'dispute_count'   => $stats['dispute_count'],
                'complaint_count' => $stats['complaint_count'],
                'refund_rate'     => $totalOrders > 0 ? round($refundRate * 100, 2) : 0,
                'dispute_rate'    => $totalOrders > 0 ? round($disputeRate * 100, 2) : 0,
                'complaint_rate'  => $totalOrders > 0 ? round($complaintRate * 100, 2) : 0,
                'avg_health'      => round($avgHealth, 2),
                'today_orders'    => $stats['today_orders'],
                'avg_daily_7d'    => $stats['avg_daily_7d'],
            ],
        ];
    }

    /**
     * 批量检查所有商户风险（定时任务用）
     *
     * @return void
     */
    public function checkAllMerchants(): void
    {
        Merchant::query()
            ->where('status', 1) // 仅检查 active 商户
            ->chunkById(50, function ($merchants) {
                /** @var Merchant $merchant */
                foreach ($merchants as $merchant) {
                    try {
                        $this->calculateRiskScore($merchant->id);
                    } catch (\Throwable $e) {
                        Log::error('[MerchantRisk] Failed to calculate risk score', [
                            'merchant_id' => $merchant->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('[MerchantRisk] Batch risk check completed');
    }

    /* ----------------------------------------------------------------
     |  风险操作（M3-017 动态限额调整）
     | ---------------------------------------------------------------- */

    /**
     * 根据风险评分执行自动操作
     *
     * @param  int    $merchantId
     * @param  int    $score
     * @param  string $level
     * @return array  已执行的操作列表
     */
    private function executeRiskActions(int $merchantId, int $score, string $level): array
    {
        $actions = [];

        switch ($level) {
            case 'high':
                // 限额下调 50%
                $this->adjustAccountLimits($merchantId, 0.5);
                $actions[] = 'limit_reduced_50';

                $this->notificationService->sendToAdmin(
                    '高风险商户告警',
                    "商户 #{$merchantId} 风险评分 {$score}，已自动下调限额 50%",
                    NotificationService::TYPE_RISK,
                    NotificationService::LEVEL_WARNING,
                );

                Log::warning('[MerchantRisk] High risk — limits reduced 50%', [
                    'merchant_id' => $merchantId,
                    'score'       => $score,
                ]);
                break;

            case 'critical':
                // 暂停所有支付账号
                $this->adjustAccountLimits($merchantId, 0);
                $actions[] = 'accounts_suspended';

                // 自动加入黑名单
                $this->blacklistService->autoAdd($merchantId);
                $actions[] = 'auto_blacklisted';

                // 冻结商户资金
                Merchant::where('id', $merchantId)
                    ->update(['fund_frozen_until' => now()->addDays(90)]);
                $actions[] = 'fund_frozen_90d';

                $this->notificationService->sendToAdmin(
                    '极高风险商户 — 已暂停交易',
                    "商户 #{$merchantId} 风险评分 {$score}，已暂停全部交易并加入黑名单，资金冻结 90 天",
                    NotificationService::TYPE_RISK,
                    NotificationService::LEVEL_ERROR,
                );

                Log::error('[MerchantRisk] Critical risk — all accounts suspended, auto-blacklisted', [
                    'merchant_id' => $merchantId,
                    'score'       => $score,
                ]);
                break;

            default:
                // low / medium — 无自动操作
                break;
        }

        return $actions;
    }

    /**
     * 调整商户下所有支付账号限额
     *
     * @param  int   $merchantId
     * @param  float $factor  乘数（0.5 = 下调 50%，0 = 暂停）
     * @return void
     */
    private function adjustAccountLimits(int $merchantId, float $factor): void
    {
        $merchant = Merchant::with('stores.paymentAccounts')->findOrFail($merchantId);

        foreach ($merchant->stores as $store) {
            foreach ($store->paymentAccounts as $account) {
                if ($factor <= 0) {
                    // 暂停账号
                    $account->update(['status' => 0, 'permission' => 0]);
                } else {
                    // 按比例下调限额
                    $account->update([
                        'single_limit'  => bcmul((string) $account->single_limit, (string) $factor, 2),
                        'daily_limit'   => bcmul((string) $account->daily_limit, (string) $factor, 2),
                        'monthly_limit' => bcmul((string) $account->monthly_limit, (string) $factor, 2),
                    ]);
                }
            }
        }
    }

    /* ----------------------------------------------------------------
     |  跨 Tenant 聚合
     | ---------------------------------------------------------------- */

    /**
     * 聚合商户跨租户订单统计数据（30 天滑动窗口）
     *
     * @param  int $merchantId
     * @return array
     */
    private function aggregateMerchantOrderStats(int $merchantId): array
    {
        $merchant = Merchant::with('stores')->findOrFail($merchantId);

        $stats = [
            'total_orders'    => 0,
            'refund_count'    => 0,
            'dispute_count'   => 0,
            'complaint_count' => 0,
            'today_orders'    => 0,
            'daily_orders_7d' => [],
            'avg_daily_7d'    => 0,
        ];

        $windowStart = now()->subDays(self::WINDOW_DAYS);

        foreach ($merchant->stores as $store) {
            $store->run(function () use (&$stats, $windowStart) {
                // 30 天总订单数
                $stats['total_orders'] += Order::where('created_at', '>=', $windowStart)->count();

                // 退款订单数（refund_status 为已退款或部分退款）
                $stats['refund_count'] += Order::where('created_at', '>=', $windowStart)
                    ->whereIn('refund_status', [
                        OrderRefundStatus::REFUNDED,
                        OrderRefundStatus::PARTIAL_REFUNDED,
                    ])
                    ->count();

                // 争议订单数
                $stats['dispute_count'] += Order::where('created_at', '>=', $windowStart)
                    ->where('dispute_status', OrderDisputeStatus::OPEN)
                    ->count();

                // 投诉数（使用 is_blacklist 字段作为投诉标记的近似）
                $stats['complaint_count'] += Order::where('created_at', '>=', $windowStart)
                    ->where('is_blacklist', true)
                    ->count();

                // 今日订单数
                $stats['today_orders'] += Order::whereDate('created_at', today())->count();

                // 近 7 天每日订单数（用于销量异常检测）
                for ($i = 0; $i < 7; $i++) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    $count = Order::whereDate('created_at', $date)->count();
                    $stats['daily_orders_7d'][$date] = ($stats['daily_orders_7d'][$date] ?? 0) + $count;
                }
            });
        }

        // 计算 7 日日均
        $dailyValues = array_values($stats['daily_orders_7d']);
        $stats['avg_daily_7d'] = count($dailyValues) > 0
            ? array_sum($dailyValues) / count($dailyValues)
            : 0;

        return $stats;
    }

    /**
     * 计算销量异常维度分数
     *
     * 日均订单量与 7 日均值偏差超过 200% 则满分。
     *
     * @param  array $stats
     * @return float
     */
    private function calculateVolumeScore(array $stats): float
    {
        $avgDaily  = $stats['avg_daily_7d'];
        $todayOrds = $stats['today_orders'];

        if ($avgDaily <= 0) {
            // 无历史数据时，如果今日有大量订单视为异常
            return $todayOrds > 10 ? (float) self::WEIGHT_VOLUME : 0.0;
        }

        $deviation = abs($todayOrds - $avgDaily) / $avgDaily;

        if ($deviation >= self::VOLUME_DEVIATION_THRESHOLD) {
            return (float) self::WEIGHT_VOLUME;
        }

        // 线性插值：0% → 0 分，200% → 满分
        return self::WEIGHT_VOLUME * min(1.0, $deviation / self::VOLUME_DEVIATION_THRESHOLD);
    }

    /**
     * 获取商户下所有支付账号的平均健康度
     *
     * @param  int $merchantId
     * @return float 0-100
     */
    private function getAverageAccountHealth(int $merchantId): float
    {
        $merchant = Merchant::with('stores.paymentAccounts')->findOrFail($merchantId);

        $scores = [];
        foreach ($merchant->stores as $store) {
            foreach ($store->paymentAccounts as $account) {
                $scores[] = (int) $account->health_score;
            }
        }

        if (empty($scores)) {
            return 100.0; // 无账号时默认健康
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * 持久化评分到 jh_merchant_risk_scores 表
     *
     * @param  int    $merchantId
     * @param  int    $score
     * @param  string $level
     * @param  array  $breakdown
     * @return void
     */
    private function persistScore(int $merchantId, int $score, string $level, array $breakdown): void
    {
        MerchantRiskScore::updateOrCreate(
            ['merchant_id' => $merchantId],
            [
                'score'        => $score,
                'level'        => $level,
                'factors'      => $breakdown,
                'evaluated_at' => now(),
            ]
        );
    }
}
