<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Merchant\SyncBatchRequest;
use App\Http\Requests\Merchant\SyncSingleRequest;
use App\Jobs\BatchSyncProductsJob;
use App\Jobs\SyncProductToStoreJob;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Services\MerchantDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商户后台 — 商品同步触发控制器
 *
 * 提供手动触发同步的 API 端点：单商品、批量、全量、增量。
 *
 * 端点前缀：/api/v1/merchant/sync
 * Guard：merchant（Sanctum）
 */
class SyncTriggerController extends BaseController
{
    public function __construct(
        protected readonly MerchantDatabaseService $merchantDb,
    ) {}

    /* ================================================================
     |  手动同步
     | ================================================================ */

    /**
     * 单商品同步
     *
     * POST /api/v1/merchant/sync/{masterProductId}/single
     *
     * 将指定 MasterProduct 同步到目标 Store。
     * 异步分发 SyncProductToStoreJob，立即返回 202。
     */
    public function syncSingle(SyncSingleRequest $request, int $masterProductId): JsonResponse
    {
        $merchant = $this->getMerchant($request);
        $storeId  = $request->validated('store_id');

        // 验证商品归属
        $this->validateProductOwnership($merchant, $masterProductId);

        // 验证店铺归属
        $this->validateStoreOwnership($merchant, $storeId);

        SyncProductToStoreJob::dispatch(
            merchantId:      $merchant->id,
            masterProductId: $masterProductId,
            storeId:         (int) $storeId,
            syncRuleId:      $request->validated('sync_rule_id') ? (int) $request->validated('sync_rule_id') : null,
        );

        return response()->json([
            'code'    => 0,
            'message' => 'Job dispatched',
            'data'    => [
                'master_product_id' => $masterProductId,
                'store_id'          => $storeId,
            ],
        ], 202);
    }

    /**
     * 批量同步
     *
     * POST /api/v1/merchant/sync/batch
     *
     * 将多个 MasterProduct 同步到目标 Store。
     * 异步分发 BatchSyncProductsJob，立即返回 202。
     */
    public function syncBatch(SyncBatchRequest $request): JsonResponse
    {
        $merchant        = $this->getMerchant($request);
        $storeId         = $request->validated('store_id');
        $masterProductIds = $request->validated('master_product_ids');

        // 验证所有商品归属
        $this->validateProductsOwnership($merchant, $masterProductIds);

        // 验证店铺归属
        $this->validateStoreOwnership($merchant, $storeId);

        // 逐个分发单商品同步 Job（BatchSyncProductsJob 用于 full/incremental）
        foreach ($masterProductIds as $productId) {
            SyncProductToStoreJob::dispatch(
                merchantId:      $merchant->id,
                masterProductId: (int) $productId,
                storeId:         (int) $storeId,
            );
        }

        return response()->json([
            'code'    => 0,
            'message' => 'Batch jobs dispatched',
            'data'    => [
                'total'    => count($masterProductIds),
                'store_id' => $storeId,
            ],
        ], 202);
    }

    /**
     * 全量同步
     *
     * POST /api/v1/merchant/sync/{storeId}/full
     *
     * 将商户所有商品全量同步到指定 Store。
     */
    public function syncFull(Request $request, int $storeId): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $this->validateStoreOwnership($merchant, (string) $storeId);

        BatchSyncProductsJob::dispatch(
            merchantId: $merchant->id,
            storeId:    $storeId,
            type:       'full',
        );

        return response()->json([
            'code'    => 0,
            'message' => 'Full sync job dispatched',
            'data'    => [
                'store_id' => $storeId,
                'type'     => 'full',
            ],
        ], 202);
    }

    /**
     * 增量同步
     *
     * POST /api/v1/merchant/sync/{storeId}/incremental
     *
     * 将商户最近变更的商品增量同步到指定 Store。
     */
    public function syncIncremental(Request $request, int $storeId): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $this->validateStoreOwnership($merchant, (string) $storeId);

        BatchSyncProductsJob::dispatch(
            merchantId: $merchant->id,
            storeId:    $storeId,
            type:       'incremental',
        );

        return response()->json([
            'code'    => 0,
            'message' => 'Incremental sync job dispatched',
            'data'    => [
                'store_id' => $storeId,
                'type'     => 'incremental',
            ],
        ], 202);
    }

    /* ================================================================
     |  验证辅助
     | ================================================================ */

    /**
     * 从当前认证用户获取所属商户 Model
     */
    protected function getMerchant(Request $request): Merchant
    {
        /** @var MerchantUser $user */
        $user = $request->user('merchant');

        return $user->merchant;
    }

    /**
     * 验证单个 MasterProduct 归属当前商户
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function validateProductOwnership(Merchant $merchant, int $masterProductId): void
    {
        $exists = $this->merchantDb->run($merchant, function () use ($masterProductId) {
            return MasterProduct::where('id', $masterProductId)->exists();
        });

        if (!$exists) {
            abort(404, '商品不存在或不属于当前商户');
        }
    }

    /**
     * 验证多个 MasterProduct 均归属当前商户
     *
     * @param array<string> $masterProductIds
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function validateProductsOwnership(Merchant $merchant, array $masterProductIds): void
    {
        $intIds = array_map('intval', $masterProductIds);

        $existCount = $this->merchantDb->run($merchant, function () use ($intIds) {
            return MasterProduct::whereIn('id', $intIds)->count();
        });

        if ($existCount !== count($intIds)) {
            abort(422, '部分商品不存在或不属于当前商户');
        }
    }

    /**
     * 验证 Store 归属当前商户
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function validateStoreOwnership(Merchant $merchant, string $storeId): void
    {
        $exists = Store::where('id', $storeId)
            ->where('merchant_id', $merchant->id)
            ->exists();

        if (!$exists) {
            abort(404, '店铺不存在或不属于当前商户');
        }
    }
}
