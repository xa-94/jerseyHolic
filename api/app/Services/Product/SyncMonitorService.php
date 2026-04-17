<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Jobs\BatchSyncProductsJob;
use App\Models\Central\ProductSyncLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 同步监控服务
 *
 * 负责同步日志的记录、统计、趋势分析及失败重试。
 * 所有数据基于 Central DB 的 product_sync_logs 表。
 */
class SyncMonitorService
{
    /**
     * 记录同步日志
     *
     * @param array{
     *     merchant_id: int,
     *     target_store_id: string,
     *     source_store_id?: int|null,
     *     sync_type: string,
     *     trigger?: string,
     *     status: string,
     *     total_products?: int,
     *     synced_products?: int,
     *     failed_products?: int,
     *     error_log?: array|null,
     *     started_at?: string|null,
     *     completed_at?: string|null,
     * } $data
     */
    public function logSync(array $data): ProductSyncLog
    {
        return ProductSyncLog::create([
            'merchant_id'      => $data['merchant_id'],
            'target_store_id'  => $data['target_store_id'],
            'source_store_id'  => $data['source_store_id'] ?? null,
            'sync_type'        => $data['sync_type'] ?? 'incremental',
            'trigger'          => $data['trigger'] ?? 'manual',
            'status'           => $data['status'],
            'total_products'   => $data['total_products'] ?? 0,
            'synced_products'  => $data['synced_products'] ?? 0,
            'failed_products'  => $data['failed_products'] ?? 0,
            'error_log'        => $data['error_log'] ?? null,
            'started_at'       => $data['started_at'] ?? null,
            'completed_at'     => $data['completed_at'] ?? null,
        ]);
    }

    /**
     * 站点同步统计
     *
     * @param string $storeId  目标站点 ID
     * @param string $period   统计周期：24h / 7d / 30d
     * @return array{
     *     completed_count: int,
     *     failed_count: int,
     *     partial_count: int,
     *     total_count: int,
     *     avg_duration_seconds: float|null,
     *     last_sync_at: string|null,
     *     error_rate: float,
     * }
     */
    public function getStoreStats(string $storeId, string $period = '24h'): array
    {
        $since = $this->parsePeriod($period);

        $query = ProductSyncLog::ofStore($storeId)->where('created_at', '>=', $since);

        $stats = $query->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration_seconds,
            MAX(completed_at) as last_sync_at
        ")->first();

        $total = (int) ($stats->total_count ?? 0);
        $failed = (int) ($stats->failed_count ?? 0);

        return [
            'completed_count'      => (int) ($stats->completed_count ?? 0),
            'failed_count'         => $failed,
            'partial_count'        => (int) ($stats->partial_count ?? 0),
            'total_count'          => $total,
            'avg_duration_seconds' => $stats->avg_duration_seconds !== null
                ? round((float) $stats->avg_duration_seconds, 2)
                : null,
            'last_sync_at'         => $stats->last_sync_at,
            'error_rate'           => $total > 0
                ? round($failed / $total * 100, 2)
                : 0.0,
        ];
    }

    /**
     * 商户级同步统计（汇总所有站点）
     *
     * @param int    $merchantId
     * @param string $period  统计周期：24h / 7d / 30d
     * @return array{
     *     summary: array,
     *     by_store: array,
     * }
     */
    public function getMerchantStats(int $merchantId, string $period = '24h'): array
    {
        $since = $this->parsePeriod($period);

        // 汇总
        $summary = ProductSyncLog::ofMerchant($merchantId)
            ->where('created_at', '>=', $since)
            ->selectRaw("
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration_seconds,
                MAX(completed_at) as last_sync_at
            ")
            ->first();

        $total = (int) ($summary->total_count ?? 0);
        $failed = (int) ($summary->failed_count ?? 0);

        // 按站点分组
        $byStore = ProductSyncLog::ofMerchant($merchantId)
            ->where('created_at', '>=', $since)
            ->join('stores', 'stores.id', '=', 'product_sync_logs.target_store_id')
            ->selectRaw("
                product_sync_logs.target_store_id as store_id,
                stores.store_name,
                COUNT(*) as total_count,
                SUM(CASE WHEN product_sync_logs.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN product_sync_logs.status = 'failed' THEN 1 ELSE 0 END) as failed_count
            ")
            ->groupBy('product_sync_logs.target_store_id', 'stores.store_name')
            ->get()
            ->map(fn ($row) => [
                'store_id'        => $row->store_id,
                'store_name'      => $row->store_name,
                'total_count'     => (int) $row->total_count,
                'completed_count' => (int) $row->completed_count,
                'failed_count'    => (int) $row->failed_count,
                'error_rate'      => (int) $row->total_count > 0
                    ? round((int) $row->failed_count / (int) $row->total_count * 100, 2)
                    : 0.0,
            ])
            ->toArray();

        return [
            'summary' => [
                'total_count'          => $total,
                'completed_count'      => (int) ($summary->completed_count ?? 0),
                'failed_count'         => $failed,
                'partial_count'        => (int) ($summary->partial_count ?? 0),
                'avg_duration_seconds' => $summary->avg_duration_seconds !== null
                    ? round((float) $summary->avg_duration_seconds, 2)
                    : null,
                'last_sync_at'         => $summary->last_sync_at,
                'error_rate'           => $total > 0 ? round($failed / $total * 100, 2) : 0.0,
            ],
            'by_store' => $byStore,
        ];
    }

    /**
     * 日志分页查询
     *
     * @param array{
     *     merchant_id?: int,
     *     store_id?: string,
     *     status?: string,
     *     sync_type?: string,
     *     date_from?: string,
     *     date_to?: string,
     * } $filters
     * @param int $perPage
     */
    public function getRecentLogs(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = ProductSyncLog::with('store:id,store_name')
            ->latest('created_at');

        if (!empty($filters['merchant_id'])) {
            $query->ofMerchant((int) $filters['merchant_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->ofStore($filters['store_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['sync_type'])) {
            $query->where('sync_type', $filters['sync_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * 失败同步日志列表
     *
     * @param int $merchantId
     * @param int $limit
     */
    public function getFailedSyncs(int $merchantId, int $limit = 50): Collection
    {
        return ProductSyncLog::ofMerchant($merchantId)
            ->failed()
            ->with('store:id,store_name')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 同步趋势（按天分组）
     *
     * @param string $storeId
     * @param int    $days
     * @return array<int, array{date: string, total: int, completed: int, failed: int, success_rate: float}>
     */
    public function getSyncTrend(string $storeId, int $days = 7): array
    {
        $since = now()->subDays($days)->startOfDay();

        $rows = ProductSyncLog::ofStore($storeId)
            ->where('created_at', '>=', $since)
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($row) => [
            'date'         => $row->date,
            'total'        => (int) $row->total,
            'completed'    => (int) $row->completed,
            'failed'       => (int) $row->failed,
            'success_rate' => (int) $row->total > 0
                ? round((int) $row->completed / (int) $row->total * 100, 2)
                : 0.0,
        ])->toArray();
    }

    /**
     * 重试失败的同步
     *
     * 读取失败日志，重新分发 SyncProductToStoreJob。
     * 注意：该 Job 接受 masterProductId 参数，但同步日志是批量级别的，
     * 因此此方法会创建新的 pending 日志记录并重新触发同步。
     *
     * @param int $logId  同步日志 ID
     * @return array{success: bool, message: string}
     */
    public function retryFailedSync(int $logId): array
    {
        $log = ProductSyncLog::find($logId);

        if (!$log) {
            return ['success' => false, 'message' => 'Sync log not found.'];
        }

        if ($log->status !== 'failed') {
            return ['success' => false, 'message' => 'Only failed sync logs can be retried.'];
        }

        try {
            // 使用 BatchSyncProductsJob 做增量同步重试
            BatchSyncProductsJob::dispatch(
                merchantId: $log->merchant_id,
                storeId:    (int) $log->target_store_id,
                type:       'incremental',
            );

            $log->update(['status' => 'pending']);

            Log::info('[SyncMonitorService] Retry dispatched.', [
                'original_log_id' => $logId,
            ]);

            return ['success' => true, 'message' => 'Incremental sync retry dispatched.', 'log_id' => $logId];
        } catch (\Throwable $e) {
            Log::error('[SyncMonitorService] Retry failed.', [
                'log_id' => $logId,
                'error'  => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Retry failed: ' . $e->getMessage()];
        }
    }

    /**
     * 全局同步概览（管理端）
     *
     * @param string $period
     * @return array{
     *     total_syncs: int,
     *     completed: int,
     *     failed: int,
     *     success_rate: float,
     *     active_merchants: int,
     * }
     */
    public function getGlobalOverview(string $period = '24h'): array
    {
        $since = $this->parsePeriod($period);

        $stats = ProductSyncLog::where('created_at', '>=', $since)
            ->selectRaw("
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                COUNT(DISTINCT merchant_id) as active_merchants
            ")
            ->first();

        $total = (int) ($stats->total_syncs ?? 0);

        return [
            'total_syncs'      => $total,
            'completed'        => (int) ($stats->completed ?? 0),
            'failed'           => (int) ($stats->failed ?? 0),
            'success_rate'     => $total > 0
                ? round((int) ($stats->completed ?? 0) / $total * 100, 2)
                : 0.0,
            'active_merchants' => (int) ($stats->active_merchants ?? 0),
        ];
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 解析周期字符串为起始时间
     */
    protected function parsePeriod(string $period): Carbon
    {
        return match ($period) {
            '1h'  => now()->subHour(),
            '24h' => now()->subHours(24),
            '7d'  => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };
    }
}
