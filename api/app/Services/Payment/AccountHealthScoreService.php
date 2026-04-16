<?php

namespace App\Services\Payment;

use App\Models\Central\PaymentAccount;
use Illuminate\Support\Facades\Log;

/**
 * 账号健康度评分服务（M3-006）
 *
 * 评分维度（总分 0-100）：
 *  - 成功率   (40%)：success_count / total * 40
 *  - 退款率   (25%)：max(0, 25 - refund_count / total * 100)
 *  - 争议率   (20%)：max(0, 20 - dispute_count / total * 80)
 *  - 活跃度   (10%)：基于 last_used_at，7 天内满分
 *  - 账龄     (5%) ：基于 created_at，90 天以上满分
 *
 * 联动：
 *  - 评分 < 30 → 自动调用 AccountLifecycleService 降级到 AGING
 *  - 评分 < 50 → 触发告警（NotificationService 接口预留）
 */
class AccountHealthScoreService
{
    /** 各维度权重 */
    private const WEIGHT_SUCCESS_RATE = 40;
    private const WEIGHT_REFUND_RATE  = 25;
    private const WEIGHT_DISPUTE_RATE = 20;
    private const WEIGHT_ACTIVITY     = 10;
    private const WEIGHT_AGE          = 5;

    /** 告警阈值 */
    private const THRESHOLD_DISABLE = 30;
    private const THRESHOLD_ALERT   = 50;

    public function __construct(
        private readonly AccountLifecycleService $lifecycleService,
    ) {}

    /**
     * 计算当前健康度评分
     *
     * @param  PaymentAccount $account
     * @return int 0-100
     */
    public function calculate(PaymentAccount $account): int
    {
        $breakdown = $this->getScoreBreakdown($account);

        $total = (int) round(
            $breakdown['success_rate_score']
            + $breakdown['refund_rate_score']
            + $breakdown['dispute_rate_score']
            + $breakdown['activity_score']
            + $breakdown['age_score']
        );

        return max(0, min(100, $total));
    }

    /**
     * 批量重算所有账号评分
     *
     * @return void
     */
    public function recalculateAll(): void
    {
        PaymentAccount::query()
            ->whereNull('deleted_at')
            ->chunkById(100, function ($accounts) {
                /** @var PaymentAccount $account */
                foreach ($accounts as $account) {
                    $score = $this->calculate($account);
                    $account->updateQuietly(['health_score' => $score]);

                    // 联动逻辑
                    $this->handleScoreThresholds($account, $score);
                }
            });

        Log::info('[AccountHealthScore] Batch recalculation completed');
    }

    /**
     * 判断是否应自动禁用（评分 < 30）
     *
     * @param  PaymentAccount $account
     * @return bool
     */
    public function shouldDisable(PaymentAccount $account): bool
    {
        return $account->health_score < self::THRESHOLD_DISABLE;
    }

    /**
     * 返回各维度评分明细
     *
     * @param  PaymentAccount $account
     * @return array
     */
    public function getScoreBreakdown(PaymentAccount $account): array
    {
        $successCount  = (int) $account->total_success_count;
        $failCount     = (int) $account->total_fail_count;
        $refundCount   = (int) $account->total_refund_count;
        $disputeCount  = (int) $account->total_dispute_count;
        $totalCount    = $successCount + $failCount;

        // 成功率得分 (0-40)
        $successRateScore = $totalCount > 0
            ? ($successCount / $totalCount) * self::WEIGHT_SUCCESS_RATE
            : self::WEIGHT_SUCCESS_RATE; // 无交易记录时满分

        // 退款率得分 (0-25)
        $refundRatePercent = $totalCount > 0 ? ($refundCount / $totalCount) * 100 : 0;
        $refundRateScore   = max(0, self::WEIGHT_REFUND_RATE - $refundRatePercent);

        // 争议率得分 (0-20)
        $disputeRatePercent = $totalCount > 0 ? ($disputeCount / $totalCount) * 80 : 0;
        $disputeRateScore   = max(0, self::WEIGHT_DISPUTE_RATE - $disputeRatePercent);

        // 活跃度得分 (0-10)：7 天内满分，30 天以上 0 分
        $activityScore = self::WEIGHT_ACTIVITY;
        if ($account->last_used_at !== null) {
            $daysSinceUse  = $account->last_used_at->diffInDays(now());
            $activityScore = match (true) {
                $daysSinceUse <= 7  => (float) self::WEIGHT_ACTIVITY,
                $daysSinceUse >= 30 => 0.0,
                default             => self::WEIGHT_ACTIVITY * (1 - ($daysSinceUse - 7) / 23),
            };
        } else {
            // 从未使用过，给半分
            $activityScore = self::WEIGHT_ACTIVITY * 0.5;
        }

        // 账龄得分 (0-5)：90 天以上满分
        $ageScore = self::WEIGHT_AGE;
        if ($account->created_at !== null) {
            $ageDays  = $account->created_at->diffInDays(now());
            $ageScore = $ageDays >= 90
                ? (float) self::WEIGHT_AGE
                : self::WEIGHT_AGE * ($ageDays / 90);
        }

        return [
            'success_rate_score'  => round($successRateScore, 2),
            'refund_rate_score'   => round($refundRateScore, 2),
            'dispute_rate_score'  => round($disputeRateScore, 2),
            'activity_score'      => round($activityScore, 2),
            'age_score'           => round($ageScore, 2),
            'total'               => max(0, min(100, (int) round(
                $successRateScore + $refundRateScore + $disputeRateScore + $activityScore + $ageScore
            ))),
            // 原始数据（方便调试）
            'raw' => [
                'total_count'    => $totalCount,
                'success_count'  => $successCount,
                'fail_count'     => $failCount,
                'refund_count'   => $refundCount,
                'dispute_count'  => $disputeCount,
                'refund_rate'    => $totalCount > 0 ? round($refundRatePercent, 2) : 0,
                'dispute_rate'   => $totalCount > 0 ? round(($disputeCount / $totalCount) * 100, 2) : 0,
                'days_since_use' => $account->last_used_at?->diffInDays(now()),
                'age_days'       => $account->created_at?->diffInDays(now()),
            ],
        ];
    }

    /**
     * 根据评分阈值执行联动操作
     *
     * @param  PaymentAccount $account
     * @param  int            $score
     * @return void
     */
    public function handleScoreThresholds(PaymentAccount $account, int $score): void
    {
        // 评分 < 30 → 自动降级到 AGING
        if ($score < self::THRESHOLD_DISABLE) {
            if ($account->lifecycle_stage !== PaymentAccount::LIFECYCLE_AGING) {
                $this->lifecycleService->transitionTo($account, PaymentAccount::LIFECYCLE_AGING);

                Log::warning('[AccountHealthScore] Auto-downgrade to AGING due to low score', [
                    'account_id' => $account->id,
                    'score'      => $score,
                ]);
            }

            // TODO: 调用 NotificationService 发送紧急告警
            return;
        }

        // 评分 < 50 → 触发告警
        if ($score < self::THRESHOLD_ALERT) {
            Log::warning('[AccountHealthScore] Low health score alert', [
                'account_id' => $account->id,
                'score'      => $score,
            ]);

            // TODO: 调用 NotificationService 发送预警通知
        }
    }
}
