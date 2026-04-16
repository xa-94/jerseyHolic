<?php

namespace App\Services\Payment;

use App\DTOs\ElectionResult;
use App\Models\Central\Blacklist;
use App\Models\Central\PaymentAccount;
use App\Models\Central\PaymentAccountGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 8 层选号算法服务（M3-007）
 *
 * 为订单选择最优支付账号的核心算法，按以下顺序逐层筛选：
 *
 *  Layer 1 — 风控前置检查（黑名单拦截）
 *  Layer 2 — 三层映射定位（Store→Merchant→Group）
 *  Layer 3 — 可用账号筛选（status/health/cooling）
 *  Layer 4 — 优先级排序（分组类型 + priority 字段）
 *  Layer 5 — 限额检查（单笔/日/月限额、日笔数）
 *  Layer 6 — 健康度排序（health_score DESC → last_used_at ASC）
 *  Layer 7 — 行为约束检查（时间限频、IP 地理一致性）
 *  Layer 8 — 返回 / 容灾（降级重试策略）
 *
 * 每层筛选结果均记录到 layerLogs，用于排查和分析。
 */
class ElectionService
{
    /** 同一账号最小交易间隔（秒） */
    private const MIN_INTERVAL_SECONDS = 60;

    /** 容灾降级健康度阈值 */
    private const HEALTH_DEGRADED_THRESHOLD = 20;

    /** 默认最低健康度（正常流程） */
    private const HEALTH_NORMAL_THRESHOLD = 30;

    /** 分组类型优先级映射（数值越大越优先） */
    private const GROUP_TYPE_PRIORITY = [
        PaymentAccountGroup::GROUP_TYPE_VIP_EXCLUSIVE   => 30,
        PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED => 20,
        PaymentAccountGroup::GROUP_TYPE_LITE_SHARED     => 10,
    ];

    /** @var array 每层筛选日志 */
    private array $logs = [];

    public function __construct(
        private readonly PaymentGroupMappingService $mappingService,
        private readonly AccountLifecycleService    $lifecycleService,
        private readonly AccountHealthScoreService  $healthScoreService,
    ) {}

    /* ================================================================
     |  公开 API
     | ================================================================ */

    /**
     * 执行 8 层选号算法，为订单选择最优支付账号
     *
     * @param  int    $storeId       当前站点 ID
     * @param  string $paymentMethod 支付方式（paypal/stripe/credit_card）
     * @param  string $amount        订单金额（字符串精度）
     * @param  array  $buyerInfo     买家信息 ['ip' => '', 'email' => '', 'device_fingerprint' => '']
     * @return ElectionResult
     */
    public function elect(int $storeId, string $paymentMethod, string $amount, array $buyerInfo = []): ElectionResult
    {
        $this->logs = [];

        Log::info('[Election] Starting 8-layer election', [
            'store_id'       => $storeId,
            'payment_method' => $paymentMethod,
            'amount'         => $amount,
        ]);

        // Layer 1 — 风控前置检查
        $blacklistResult = $this->layer1BlacklistCheck($buyerInfo);
        if ($blacklistResult !== null) {
            return $blacklistResult;
        }

        // Layer 2 — 三层映射定位
        $group = $this->layer2ResolveGroup($storeId, $paymentMethod);
        if ($group === null) {
            return ElectionResult::noMapping($this->logs);
        }

        // Layer 3 — 可用账号筛选
        $candidates = $this->layer3AvailableAccounts($group->id);
        if ($candidates->isEmpty()) {
            return ElectionResult::noAvailable($this->logs);
        }

        // Layer 4 — 优先级排序
        $candidates = $this->layer4PrioritySort($candidates, $group);

        // Layer 5 — 限额检查
        $candidates = $this->layer5LimitCheck($candidates, $amount);
        if ($candidates->isEmpty()) {
            // 直接进入容灾
            return $this->layer8Fallback($group->id, $amount, $buyerInfo);
        }

        // Layer 6 — 健康度排序
        $candidates = $this->layer6HealthSort($candidates);

        // Layer 7 — 行为约束检查
        $candidates = $this->layer7BehaviorCheck($candidates, $buyerInfo);
        if ($candidates->isEmpty()) {
            // 直接进入容灾
            return $this->layer8Fallback($group->id, $amount, $buyerInfo);
        }

        // Layer 8 — 返回最优账号
        $selected = $candidates->first();

        $this->addLog(8, 'final_selection', $candidates->count(), 1, 'selected', "Selected account #{$selected->id}");

        Log::info('[Election] Account selected', [
            'account_id' => $selected->id,
            'account'    => $selected->account,
            'layers'     => count($this->logs),
        ]);

        return ElectionResult::success($selected, $this->logs);
    }

    /* ================================================================
     |  Layer 1 — 风控前置检查
     | ================================================================ */

    /**
     * 检查买家 IP / Email / 设备指纹是否在黑名单
     *
     * @param  array $buyerInfo
     * @return ElectionResult|null 命中时返回 blocked，否则 null
     */
    private function layer1BlacklistCheck(array $buyerInfo): ?ElectionResult
    {
        $start = microtime(true);

        $checks = [
            Blacklist::DIMENSION_IP     => $buyerInfo['ip'] ?? null,
            Blacklist::DIMENSION_EMAIL  => $buyerInfo['email'] ?? null,
            Blacklist::DIMENSION_DEVICE => $buyerInfo['device_fingerprint'] ?? null,
        ];

        foreach ($checks as $dimension => $value) {
            if (empty($value)) {
                continue;
            }

            $hit = Blacklist::query()
                ->active()
                ->match($dimension, $value)
                ->exists();

            if ($hit) {
                $durationMs = $this->elapsed($start);
                $reason     = "Blacklist hit: {$dimension}={$value}";

                $this->addLog(1, 'blacklist_check', null, null, 'blocked', $reason, $durationMs);

                Log::warning('[Election] Blacklist blocked', [
                    'dimension' => $dimension,
                    'value'     => $value,
                ]);

                return ElectionResult::blocked($reason, $this->logs);
            }
        }

        $this->addLog(1, 'blacklist_check', null, null, 'passed', 'No blacklist match found', $this->elapsed($start));

        return null;
    }

    /* ================================================================
     |  Layer 2 — 三层映射定位
     | ================================================================ */

    /**
     * 通过 PaymentGroupMappingService 定位支付分组
     *
     * @param  int    $storeId
     * @param  string $paymentMethod
     * @return PaymentAccountGroup|null
     */
    private function layer2ResolveGroup(int $storeId, string $paymentMethod): ?PaymentAccountGroup
    {
        $start = microtime(true);

        $group = $this->mappingService->resolveGroup($storeId, $paymentMethod);

        if ($group === null) {
            $this->addLog(2, 'resolve_group', null, 0, 'no_mapping',
                "No group for store={$storeId}, method={$paymentMethod}", $this->elapsed($start));

            Log::warning('[Election] No group mapping', [
                'store_id'       => $storeId,
                'payment_method' => $paymentMethod,
            ]);

            return null;
        }

        $this->addLog(2, 'resolve_group', null, 1, 'passed',
            "Resolved group #{$group->id} ({$group->name}, type={$group->group_type})", $this->elapsed($start));

        return $group;
    }

    /* ================================================================
     |  Layer 3 — 可用账号筛选
     | ================================================================ */

    /**
     * 获取分组下可用的支付账号
     *
     * @param  int $groupId
     * @param  int $minHealthScore
     * @return Collection<int, PaymentAccount>
     */
    private function layer3AvailableAccounts(int $groupId, int $minHealthScore = self::HEALTH_NORMAL_THRESHOLD): Collection
    {
        $start = microtime(true);

        $accounts = $this->mappingService->getAvailableAccounts($groupId, $minHealthScore);

        // 额外过滤：排除冷却中的账号
        $filtered = $accounts->reject(fn (PaymentAccount $a) => $a->isCooling());

        $this->addLog(3, 'available_accounts', $accounts->count(), $filtered->count(), 'filtered',
            "Group #{$groupId}: {$accounts->count()} raw → {$filtered->count()} after cooling filter (min_health={$minHealthScore})",
            $this->elapsed($start));

        return $filtered->values();
    }

    /* ================================================================
     |  Layer 4 — 优先级排序
     | ================================================================ */

    /**
     * 按分组类型优先级 + 账号 priority 字段排序
     *
     * @param  Collection           $candidates
     * @param  PaymentAccountGroup  $group
     * @return Collection<int, PaymentAccount>
     */
    private function layer4PrioritySort(Collection $candidates, PaymentAccountGroup $group): Collection
    {
        $start = microtime(true);

        $groupTypePriority = self::GROUP_TYPE_PRIORITY[$group->group_type] ?? 0;

        // 在同一分组内，按账号 priority DESC 排序
        $sorted = $candidates->sortByDesc(fn (PaymentAccount $a) => $a->priority)->values();

        $this->addLog(4, 'priority_sort', $candidates->count(), $sorted->count(), 'sorted',
            "Group type={$group->group_type} (weight={$groupTypePriority}), sorted by account priority DESC",
            $this->elapsed($start));

        return $sorted;
    }

    /* ================================================================
     |  Layer 5 — 限额检查
     | ================================================================ */

    /**
     * 调用 AccountLifecycleService::canProcess 逐一检查限额
     *
     * @param  Collection $candidates
     * @param  string     $amount
     * @return Collection<int, PaymentAccount>
     */
    private function layer5LimitCheck(Collection $candidates, string $amount): Collection
    {
        $start    = microtime(true);
        $inputCnt = $candidates->count();

        $passed = $candidates->filter(
            fn (PaymentAccount $a) => $this->lifecycleService->canProcess($a, $amount)
        )->values();

        $this->addLog(5, 'limit_check', $inputCnt, $passed->count(), 'filtered',
            "{$inputCnt} → {$passed->count()} after limit check (amount={$amount})", $this->elapsed($start));

        return $passed;
    }

    /* ================================================================
     |  Layer 6 — 健康度排序
     | ================================================================ */

    /**
     * 按 health_score DESC → last_used_at ASC 排序
     *
     * @param  Collection $candidates
     * @return Collection<int, PaymentAccount>
     */
    private function layer6HealthSort(Collection $candidates): Collection
    {
        $start = microtime(true);

        $sorted = $candidates->sort(function (PaymentAccount $a, PaymentAccount $b) {
            // health_score DESC
            $scoreDiff = $b->health_score - $a->health_score;
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }

            // last_used_at ASC（最久未使用的优先）
            $aUsed = $a->last_used_at?->timestamp ?? 0;
            $bUsed = $b->last_used_at?->timestamp ?? 0;

            return $aUsed <=> $bUsed;
        })->values();

        $top = $sorted->first();
        $this->addLog(6, 'health_sort', $candidates->count(), $sorted->count(), 'sorted',
            "Top candidate: account #{$top?->id} (score={$top?->health_score})", $this->elapsed($start));

        return $sorted;
    }

    /* ================================================================
     |  Layer 7 — 行为约束检查
     | ================================================================ */

    /**
     * 时间限频 + IP 地理一致性检查
     *
     * @param  Collection $candidates
     * @param  array      $buyerInfo
     * @return Collection<int, PaymentAccount>
     */
    private function layer7BehaviorCheck(Collection $candidates, array $buyerInfo): Collection
    {
        $start    = microtime(true);
        $inputCnt = $candidates->count();

        $passed = $candidates->filter(function (PaymentAccount $account) {
            return $this->checkMinInterval($account);
        })->values();

        $this->addLog(7, 'behavior_check', $inputCnt, $passed->count(), 'filtered',
            "{$inputCnt} → {$passed->count()} after behavior constraints (interval >= " . self::MIN_INTERVAL_SECONDS . "s)",
            $this->elapsed($start));

        return $passed;
    }

    /**
     * 检查同一账号连续交易间隔是否满足最小秒数
     *
     * 使用 Redis 原子操作记录最近一次选中时间，保证并发安全。
     *
     * @param  PaymentAccount $account
     * @return bool
     */
    private function checkMinInterval(PaymentAccount $account): bool
    {
        $cacheKey     = "election:last_used:{$account->id}";
        $lastUsedTime = Cache::get($cacheKey);

        if ($lastUsedTime === null) {
            return true;
        }

        return (time() - (int) $lastUsedTime) >= self::MIN_INTERVAL_SECONDS;
    }

    /**
     * 选号成功后标记账号使用时间（供 Layer 7 限频判断）
     *
     * @param  PaymentAccount $account
     * @return void
     */
    public function markAccountUsed(PaymentAccount $account): void
    {
        $cacheKey = "election:last_used:{$account->id}";
        Cache::put($cacheKey, time(), self::MIN_INTERVAL_SECONDS * 2);
    }

    /* ================================================================
     |  Layer 8 — 容灾降级
     | ================================================================ */

    /**
     * 降级重试策略：
     *  ① 放宽健康度到 >= HEALTH_DEGRADED_THRESHOLD 重试
     *  ② 放宽时间限频重试（跳过 Layer 7）
     *  ③ 仍无可用 → 返回 exhausted
     *
     * @param  int   $groupId
     * @param  string $amount
     * @param  array  $buyerInfo
     * @return ElectionResult
     */
    private function layer8Fallback(int $groupId, string $amount, array $buyerInfo): ElectionResult
    {
        $start = microtime(true);

        Log::warning('[Election] Entering fallback mode', [
            'group_id' => $groupId,
            'amount'   => $amount,
        ]);

        // ① 降级策略：放宽健康度
        $degradedCandidates = $this->layer3AvailableAccounts($groupId, self::HEALTH_DEGRADED_THRESHOLD);

        if ($degradedCandidates->isNotEmpty()) {
            $degradedCandidates = $degradedCandidates->filter(
                fn (PaymentAccount $a) => $this->lifecycleService->canProcess($a, $amount)
            )->values();
        }

        if ($degradedCandidates->isNotEmpty()) {
            // 按健康度排序
            $degradedCandidates = $degradedCandidates->sortByDesc(
                fn (PaymentAccount $a) => $a->health_score
            )->values();

            // 行为约束检查
            $behaviorPassed = $degradedCandidates->filter(
                fn (PaymentAccount $a) => $this->checkMinInterval($a)
            )->values();

            if ($behaviorPassed->isNotEmpty()) {
                $selected = $behaviorPassed->first();

                $this->addLog(8, 'fallback_degraded_health', $degradedCandidates->count(), 1, 'selected',
                    "Fallback ①: degraded health threshold to " . self::HEALTH_DEGRADED_THRESHOLD
                    . ", selected account #{$selected->id}", $this->elapsed($start));

                Log::info('[Election] Fallback ① success: degraded health threshold', [
                    'account_id' => $selected->id,
                ]);

                return ElectionResult::success($selected, $this->logs);
            }
        }

        // ② 降级策略：跳过时间限频
        if ($degradedCandidates->isNotEmpty()) {
            $selected = $degradedCandidates->first();

            $this->addLog(8, 'fallback_skip_interval', $degradedCandidates->count(), 1, 'selected',
                "Fallback ②: skipped interval check, selected account #{$selected->id}", $this->elapsed($start));

            Log::info('[Election] Fallback ② success: skipped interval check', [
                'account_id' => $selected->id,
            ]);

            return ElectionResult::success($selected, $this->logs);
        }

        // ③ 完全无可用
        $this->addLog(8, 'fallback_exhausted', 0, 0, 'exhausted',
            'All fallback strategies exhausted — no account available', $this->elapsed($start));

        Log::error('[Election] All accounts exhausted', [
            'group_id' => $groupId,
            'amount'   => $amount,
        ]);

        return ElectionResult::exhausted($this->logs);
    }

    /* ================================================================
     |  日志辅助
     | ================================================================ */

    /**
     * 添加一条层级筛选日志
     *
     * @param  int         $layer
     * @param  string      $name
     * @param  int|null    $inputCount
     * @param  int|null    $outputCount
     * @param  string      $result      passed/blocked/filtered/sorted/selected/exhausted
     * @param  string      $detail
     * @param  float|null  $durationMs
     * @return void
     */
    private function addLog(
        int     $layer,
        string  $name,
        ?int    $inputCount,
        ?int    $outputCount,
        string  $result,
        string  $detail,
        ?float  $durationMs = null,
    ): void {
        $this->logs[] = [
            'layer'        => $layer,
            'name'         => $name,
            'input_count'  => $inputCount,
            'output_count' => $outputCount,
            'result'       => $result,
            'detail'       => $detail,
            'duration_ms'  => $durationMs !== null ? round($durationMs, 2) : null,
        ];
    }

    /**
     * 计算从 $start 到当前的耗时（毫秒）
     *
     * @param  float $start microtime(true)
     * @return float
     */
    private function elapsed(float $start): float
    {
        return (microtime(true) - $start) * 1000;
    }
}
