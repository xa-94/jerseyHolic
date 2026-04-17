<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Resources\Merchant\SyncLogResource;
use App\Models\Central\MerchantUser;
use App\Services\Product\SyncMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商户端同步日志控制器
 *
 * 路由前缀：/api/v1/merchant/sync-logs
 * 中间件：auth:merchant
 */
class SyncLogController extends BaseController
{
    public function __construct(
        private readonly SyncMonitorService $monitorService,
    ) {}

    /**
     * 同步日志列表（分页）
     *
     * GET /api/v1/merchant/sync-logs
     *
     * 查询参数：store_id, status, sync_type, date_from, date_to, per_page
     */
    public function index(Request $request): JsonResponse
    {
        /** @var MerchantUser $user */
        $user = $request->user('merchant');

        $filters = [
            'merchant_id' => $user->merchant_id,
            'store_id'    => $request->query('store_id'),
            'status'      => $request->query('status'),
            'sync_type'   => $request->query('sync_type'),
            'date_from'   => $request->query('date_from'),
            'date_to'     => $request->query('date_to'),
        ];

        $perPage = (int) $request->query('per_page', 20);
        $paginator = $this->monitorService->getRecentLogs($filters, min($perPage, 100));

        return $this->success([
            'list'     => SyncLogResource::collection($paginator->items()),
            'total'    => $paginator->total(),
            'page'     => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * 站点同步统计
     *
     * GET /api/v1/merchant/sync-logs/stores/{storeId}/stats
     *
     * 查询参数：period (24h|7d|30d)
     */
    public function stats(Request $request, string $storeId): JsonResponse
    {
        $period = $request->query('period', '24h');
        $stats = $this->monitorService->getStoreStats($storeId, $period);

        return $this->success($stats);
    }

    /**
     * 同步趋势
     *
     * GET /api/v1/merchant/sync-logs/stores/{storeId}/trend
     *
     * 查询参数：days (7|30)
     */
    public function trend(Request $request, string $storeId): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        $trend = $this->monitorService->getSyncTrend($storeId, min($days, 90));

        return $this->success($trend);
    }

    /**
     * 重试失败同步
     *
     * POST /api/v1/merchant/sync-logs/{logId}/retry
     */
    public function retry(int $logId): JsonResponse
    {
        $result = $this->monitorService->retryFailedSync($logId);

        if (!$result['success']) {
            return $this->error(40001, $result['message']);
        }

        return $this->success($result);
    }
}
