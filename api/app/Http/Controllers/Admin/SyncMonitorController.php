<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\SyncMonitorResource;
use App\Http\Resources\Merchant\SyncLogResource;
use App\Models\Central\Merchant;
use App\Models\Central\ProductSyncLog;
use App\Services\Product\SyncMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端同步监控控制器
 *
 * 路由前缀：/api/v1/admin/sync-monitor
 * 中间件：auth:sanctum + force.json + central.only
 */
class SyncMonitorController extends BaseAdminController
{
    public function __construct(
        private readonly SyncMonitorService $monitorService,
    ) {}

    /**
     * 全局同步概览
     *
     * GET /api/v1/admin/sync-monitor/overview
     *
     * 返回所有商户的同步总量、成功率、失败数。
     * 查询参数：period (24h|7d|30d)
     */
    public function overview(Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');
        $global = $this->monitorService->getGlobalOverview($period);

        // 按商户维度聚合概况
        $merchantOverviews = ProductSyncLog::query()
            ->where('created_at', '>=', $this->parsePeriod($period))
            ->join('merchants', 'merchants.id', '=', 'product_sync_logs.merchant_id')
            ->selectRaw("
                product_sync_logs.merchant_id,
                merchants.merchant_name,
                COUNT(DISTINCT product_sync_logs.target_store_id) as store_count,
                COUNT(*) as total_syncs,
                SUM(CASE WHEN product_sync_logs.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN product_sync_logs.status = 'failed' THEN 1 ELSE 0 END) as recent_failures_count,
                MAX(product_sync_logs.completed_at) as last_sync_at
            ")
            ->groupBy('product_sync_logs.merchant_id', 'merchants.merchant_name')
            ->get()
            ->map(fn ($row) => [
                'merchant_id'           => $row->merchant_id,
                'merchant_name'         => $row->merchant_name,
                'store_count'           => (int) $row->store_count,
                'total_syncs'           => (int) $row->total_syncs,
                'success_rate'          => (int) $row->total_syncs > 0
                    ? round((int) $row->completed / (int) $row->total_syncs * 100, 2)
                    : 0.0,
                'last_sync_at'          => $row->last_sync_at,
                'recent_failures_count' => (int) $row->recent_failures_count,
            ]);

        return $this->success([
            'global'    => $global,
            'merchants' => SyncMonitorResource::collection($merchantOverviews),
        ]);
    }

    /**
     * 指定商户同步统计
     *
     * GET /api/v1/admin/sync-monitor/merchants/{merchantId}
     *
     * 查询参数：period (24h|7d|30d)
     */
    public function merchantStats(Request $request, int $merchantId): JsonResponse
    {
        $period = $request->query('period', '24h');
        $stats = $this->monitorService->getMerchantStats($merchantId, $period);

        return $this->success($stats);
    }

    /**
     * 最近失败记录
     *
     * GET /api/v1/admin/sync-monitor/failures
     *
     * 查询参数：merchant_id (optional), limit (default 50)
     */
    public function recentFailures(Request $request): JsonResponse
    {
        $query = ProductSyncLog::failed()
            ->with(['store:id,store_name', 'merchant:id,merchant_name'])
            ->latest('created_at');

        if ($merchantId = $request->query('merchant_id')) {
            $query->ofMerchant((int) $merchantId);
        }

        $limit = min((int) $request->query('limit', 50), 200);
        $logs = $query->limit($limit)->get();

        return $this->success(SyncLogResource::collection($logs));
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    protected function parsePeriod(string $period): \Carbon\Carbon
    {
        return match ($period) {
            '1h'  => now()->subHour(),
            '7d'  => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };
    }
}
