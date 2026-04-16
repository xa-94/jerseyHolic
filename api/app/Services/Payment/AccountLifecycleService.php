<?php

namespace App\Services\Payment;

use App\Models\Central\PaymentAccount;
use Illuminate\Support\Facades\Log;

/**
 * 账号生命周期管理服务（M3-005）
 *
 * 4 阶段生命周期流转：NEW → GROWING → MATURE → AGING
 * 每个阶段有独立的限额配置，满足条件时自动升降级。
 *
 * 阶梯限额：
 *  NEW     — 单笔 $50,   日 $100,    月 $500,      日笔数 3
 *  GROWING — 单笔 $200,  日 $500,    月 $5,000,    日笔数 10
 *  MATURE  — 单笔 $2,000,日 $10,000, 月 $100,000,  日笔数 50
 *  AGING   — 全部 $0（暂停使用）
 */
class AccountLifecycleService
{
    /**
     * 各阶段限额配置
     *
     * @var array<string, array{single_limit: string, daily_limit: string, monthly_limit: string, daily_count_limit: int}>
     */
    private const STAGE_LIMITS = [
        PaymentAccount::LIFECYCLE_NEW => [
            'single_limit'     => '50.00',
            'daily_limit'      => '100.00',
            'monthly_limit'    => '500.00',
            'daily_count_limit' => 3,
        ],
        PaymentAccount::LIFECYCLE_GROWING => [
            'single_limit'     => '200.00',
            'daily_limit'      => '500.00',
            'monthly_limit'    => '5000.00',
            'daily_count_limit' => 10,
        ],
        PaymentAccount::LIFECYCLE_MATURE => [
            'single_limit'     => '2000.00',
            'daily_limit'      => '10000.00',
            'monthly_limit'    => '100000.00',
            'daily_count_limit' => 50,
        ],
        PaymentAccount::LIFECYCLE_AGING => [
            'single_limit'     => '0.00',
            'daily_limit'      => '0.00',
            'monthly_limit'    => '0.00',
            'daily_count_limit' => 0,
        ],
    ];

    /**
     * 升级条件配置
     *
     * @var array<string, array{min_success: int, max_refund_rate: float, min_age_days: int}>
     */
    private const UPGRADE_CONDITIONS = [
        // NEW → GROWING
        PaymentAccount::LIFECYCLE_NEW => [
            'target'          => PaymentAccount::LIFECYCLE_GROWING,
            'min_success'     => 10,
            'max_refund_rate' => 0.0,   // 无退款
            'min_age_days'    => 7,
        ],
        // GROWING → MATURE
        PaymentAccount::LIFECYCLE_GROWING => [
            'target'          => PaymentAccount::LIFECYCLE_MATURE,
            'min_success'     => 50,
            'max_refund_rate' => 3.0,   // 退款率 < 3%
            'min_age_days'    => 30,
        ],
    ];

    /**
     * 检查并执行升级
     *
     * @param  PaymentAccount $account
     * @return bool 是否成功升级
     */
    public function checkAndUpgrade(PaymentAccount $account): bool
    {
        $stage = $account->lifecycle_stage;

        if (!isset(self::UPGRADE_CONDITIONS[$stage])) {
            return false;
        }

        $condition  = self::UPGRADE_CONDITIONS[$stage];
        $totalCount = $account->total_success_count + $account->total_fail_count;
        $refundRate = $totalCount > 0
            ? bcdiv((string) $account->total_refund_count, (string) $totalCount, 4) * 100
            : 0.0;
        $ageDays    = $account->created_at->diffInDays(now());

        // 检查升级条件
        if ($account->total_success_count >= $condition['min_success']
            && $refundRate <= $condition['max_refund_rate']
            && $ageDays >= $condition['min_age_days']
        ) {
            $this->transitionTo($account, $condition['target']);
            return true;
        }

        return false;
    }

    /**
     * 检查并执行降级
     *
     * MATURE → AGING：退款率 > 5% 或 健康度 < 50
     * GROWING → AGING：退款率 > 10% 或 健康度 < 30
     *
     * @param  PaymentAccount $account
     * @return bool 是否降级
     */
    public function checkAndDowngrade(PaymentAccount $account): bool
    {
        $stage      = $account->lifecycle_stage;
        $totalCount = $account->total_success_count + $account->total_fail_count;
        $refundRate = $totalCount > 0
            ? bcdiv((string) $account->total_refund_count, (string) $totalCount, 4) * 100
            : 0.0;

        $shouldDowngrade = match ($stage) {
            PaymentAccount::LIFECYCLE_MATURE => $refundRate > 5.0 || $account->health_score < 50,
            PaymentAccount::LIFECYCLE_GROWING => $refundRate > 10.0 || $account->health_score < 30,
            default => false,
        };

        if ($shouldDowngrade) {
            $this->transitionTo($account, PaymentAccount::LIFECYCLE_AGING);
            return true;
        }

        return false;
    }

    /**
     * 执行阶段转换
     *
     * @param  PaymentAccount $account
     * @param  string         $targetStage
     * @return void
     */
    public function transitionTo(PaymentAccount $account, string $targetStage): void
    {
        $fromStage = $account->lifecycle_stage;

        if ($fromStage === $targetStage) {
            return;
        }

        Log::info('[AccountLifecycle] Stage transition', [
            'account_id' => $account->id,
            'from'       => $fromStage,
            'to'         => $targetStage,
        ]);

        $account->update(['lifecycle_stage' => $targetStage]);

        // AGING 阶段设置冷却时间
        if ($targetStage === PaymentAccount::LIFECYCLE_AGING) {
            $account->update([
                'cooling_until' => now()->addDays(30),
                'status'        => 0, // 自动禁用
            ]);
        }

        $this->updateLimits($account);
    }

    /**
     * 根据当前阶段设置限额
     *
     * @param  PaymentAccount $account
     * @return void
     */
    public function updateLimits(PaymentAccount $account): void
    {
        $stage  = $account->lifecycle_stage;
        $limits = self::STAGE_LIMITS[$stage] ?? self::STAGE_LIMITS[PaymentAccount::LIFECYCLE_NEW];

        $account->update($limits);
    }

    /**
     * 检查账号是否可处理指定金额
     *
     * 校验维度：生命周期阶段、单笔限额、日限额、日笔数。
     * 月限额需外部定时重置 monthly_total（此处仅做阈值判断）。
     *
     * @param  PaymentAccount $account
     * @param  string         $amount
     * @return bool
     */
    public function canProcess(PaymentAccount $account, string $amount): bool
    {
        // AGING 阶段禁止处理
        if ($account->lifecycle_stage === PaymentAccount::LIFECYCLE_AGING) {
            return false;
        }

        // 冷却中禁止处理
        if ($account->isCooling()) {
            return false;
        }

        // 账号未启用或无收款权限
        if ($account->status !== 1 || $account->permission !== 1) {
            return false;
        }

        // 单笔限额
        if (bccomp($amount, (string) $account->single_limit, 2) > 0) {
            return false;
        }

        // 日限额
        $dailyAfter = bcadd((string) $account->daily_money_total, $amount, 2);
        if (bccomp($dailyAfter, (string) $account->daily_limit, 2) > 0) {
            return false;
        }

        // 日笔数
        if ($account->daily_count_limit > 0 && $account->deal_count >= $account->daily_count_limit) {
            return false;
        }

        return true;
    }

    /**
     * 记录交易结果，并触发升降级检查
     *
     * @param  PaymentAccount $account
     * @param  bool           $success
     * @return void
     */
    public function recordTransaction(PaymentAccount $account, bool $success): void
    {
        if ($success) {
            $account->increment('total_success_count');
        } else {
            $account->increment('total_fail_count');
        }

        $account->update(['last_used_at' => now()]);
        $account->refresh();

        // 成功时检查升级
        if ($success) {
            $this->checkAndUpgrade($account);
        } else {
            // 失败时检查降级
            $this->checkAndDowngrade($account);
        }
    }

    /**
     * 获取阶段限额配置
     *
     * @param  string $stage
     * @return array
     */
    public function getStageLimits(string $stage): array
    {
        return self::STAGE_LIMITS[$stage] ?? self::STAGE_LIMITS[PaymentAccount::LIFECYCLE_NEW];
    }
}
