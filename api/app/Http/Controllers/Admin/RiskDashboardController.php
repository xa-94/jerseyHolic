<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\RiskScoreResource;
use App\Models\Central\Blacklist;
use App\Models\Central\MerchantRiskScore;
use App\Services\Payment\MerchantRiskService;
use Illuminate\Http\JsonResponse;

/**
 * 平台管理端 — 风控仪表板 Controller（M3-016）
 *
 * 路由前缀：/api/v1/admin/risk
 * 中间件：auth:sanctum + force.json + central.only
 */
class RiskDashboardController extends BaseAdminController
{
    public function __construct(
        private readonly MerchantRiskService $riskService,
    ) {}

    /**
     * 商户风险评分详情
     *
     * GET /api/v1/admin/risk/merchants/{merchantId}/score
     *
     * 实时计算并返回 5 维度评分明细。
     */
    public function merchantScore(int $merchantId): JsonResponse
    {
        $result = $this->riskService->calculateRiskScore($merchantId);

        return $this->success($result->toArray());
    }

    /**
     * 风控仪表板
     *
     * GET /api/v1/admin/risk/dashboard
     *
     * 返回：
     *  - high_risk_count  高/极高风险商户数
     *  - blacklist_count  有效黑名单条目数
     *  - risk_trend       近 30 天风险趋势（按日统计高风险数）
     *  - recent_alerts    最近 10 条高风险告警
     */
    public function dashboard(): JsonResponse
    {
        // 高风险 + 极高风险商户数
        $highRiskCount = MerchantRiskScore::query()
            ->whereIn('level', ['high', 'critical'])
            ->count();

        // 有效黑名单条目数
        $blacklistCount = Blacklist::query()->active()->count();

        // 近 30 天风险趋势（按日统计高风险/极高风险商户数）
        $riskTrend = MerchantRiskScore::query()
            ->where('evaluated_at', '>=', now()->subDays(30))
            ->whereIn('level', ['high', 'critical'])
            ->selectRaw('DATE(evaluated_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(evaluated_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'  => $row->date,
                'count' => (int) $row->count,
            ])
            ->toArray();

        // 最近 10 条高风险商户评分
        $recentAlerts = MerchantRiskScore::query()
            ->with('merchant:id,merchant_name,email,status,level')
            ->whereIn('level', ['high', 'critical'])
            ->latest('evaluated_at')
            ->take(10)
            ->get()
            ->map(fn ($record) => [
                'merchant_id'   => $record->merchant_id,
                'merchant_name' => $record->merchant?->merchant_name,
                'score'         => $record->score,
                'level'         => $record->level,
                'evaluated_at'  => $record->evaluated_at?->toISOString(),
            ])
            ->toArray();

        // 各风险等级分布
        $levelDistribution = MerchantRiskScore::query()
            ->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        return $this->success([
            'high_risk_count'    => $highRiskCount,
            'blacklist_count'    => $blacklistCount,
            'risk_trend'         => $riskTrend,
            'recent_alerts'      => $recentAlerts,
            'level_distribution' => $levelDistribution,
        ]);
    }
}
