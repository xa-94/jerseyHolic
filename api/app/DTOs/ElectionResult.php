<?php

namespace App\DTOs;

use App\Models\Central\PaymentAccount;

/**
 * 选号算法结果 DTO
 *
 * 封装 ElectionService 8 层筛选的最终结果，
 * 包含选中账号、状态码、描述消息及每层筛选日志。
 *
 * 状态码：
 *  - success      — 选中合格账号
 *  - blocked      — 风控前置拦截（黑名单命中）
 *  - no_mapping   — 三层映射未找到支付分组
 *  - no_available — 分组下无可用账号
 *  - exhausted    — 所有候选均被淘汰（含容灾降级后仍无可用）
 */
class ElectionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?PaymentAccount $account,
        public readonly string $code,
        public readonly string $message,
        public readonly array $layerLogs = [],
    ) {}

    /* ----------------------------------------------------------------
     |  静态工厂方法
     | ---------------------------------------------------------------- */

    /**
     * 选号成功
     */
    public static function success(PaymentAccount $account, array $logs = []): self
    {
        return new self(
            success:   true,
            account:   $account,
            code:      'success',
            message:   "Selected account #{$account->id} ({$account->account})",
            layerLogs: $logs,
        );
    }

    /**
     * 风控拦截
     */
    public static function blocked(string $reason, array $logs = []): self
    {
        return new self(
            success:   false,
            account:   null,
            code:      'blocked',
            message:   "Blocked by risk control: {$reason}",
            layerLogs: $logs,
        );
    }

    /**
     * 无映射分组
     */
    public static function noMapping(array $logs = []): self
    {
        return new self(
            success:   false,
            account:   null,
            code:      'no_mapping',
            message:   'No payment group mapping found for current store and payment method',
            layerLogs: $logs,
        );
    }

    /**
     * 分组下无可用账号
     */
    public static function noAvailable(array $logs = []): self
    {
        return new self(
            success:   false,
            account:   null,
            code:      'no_available',
            message:   'No available payment accounts in the resolved group',
            layerLogs: $logs,
        );
    }

    /**
     * 所有候选均被淘汰
     */
    public static function exhausted(array $logs = []): self
    {
        return new self(
            success:   false,
            account:   null,
            code:      'exhausted',
            message:   'All candidate accounts exhausted after full pipeline (including fallback)',
            layerLogs: $logs,
        );
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 转为数组（日志/调试用）
     */
    public function toArray(): array
    {
        return [
            'success'    => $this->success,
            'account_id' => $this->account?->id,
            'code'       => $this->code,
            'message'    => $this->message,
            'layers'     => $this->layerLogs,
        ];
    }
}
