<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\DTOs\BatchSyncResult;
use App\DTOs\SyncResult;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Models\Merchant\SyncRule;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductDescription;
use App\Services\MerchantDatabaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 商品同步引擎核心服务
 *
 * 负责将 Merchant DB（主商品库）中的 MasterProduct 同步到
 * 各 Tenant DB（站点商品表 jh_products / jh_product_descriptions）。
 *
 * 设计要点：
 * - 幂等性：通过 sync_source_id（= masterProduct.id）做 updateOrCreate
 * - 价格策略：根据 SyncRule 的 price_strategy 应用乘数/固定价格
 * - 多语言：遍历 MasterProductTranslation 写入 product_descriptions
 * - 批量优化：预加载关联、分批处理（每批 50 条）
 *
 * @see MasterProduct       来源模型（Merchant DB）
 * @see Product             目标模型（Tenant DB）
 * @see SyncRule            价格策略与同步规则
 */
class ProductSyncService
{
    /** 每批处理的商品数量 */
    protected const BATCH_SIZE = 50;

    public function __construct(
        protected readonly MerchantDatabaseService $merchantDb,
    ) {}

    /* ================================================================
     |  单商品同步
     | ================================================================ */

    /**
     * 将单个 MasterProduct 同步到指定 Store
     *
     * 流程：
     * 1. 从 Merchant DB 读取 MasterProduct（含 translations）
     * 2. 根据 SyncRule 计算同步价格
     * 3. 初始化 Tenant 上下文
     * 4. 使用 sync_source_id 做 updateOrCreate 写入 jh_products
     * 5. 遍历 translations 写入 jh_product_descriptions
     * 6. 更新 MasterProduct 的 sync_status
     *
     * @param  Merchant       $merchant         当前商户
     * @param  int            $masterProductId  MasterProduct ID
     * @param  Store          $store            目标站点
     * @param  SyncRule|null  $syncRule         同步规则（null 时使用固定价格策略）
     * @return SyncResult
     */
    public function syncToStore(
        Merchant $merchant,
        int $masterProductId,
        Store $store,
        ?SyncRule $syncRule = null,
    ): SyncResult {
        $storeId = $store->getTenantKey();

        try {
            // 1. 从 Merchant DB 读取商品（含翻译）
            $masterProduct = $this->merchantDb->run($merchant, function () use ($masterProductId) {
                return MasterProduct::with('translations')->findOrFail($masterProductId);
            });

            // 校验商品状态
            if (!$masterProduct->isActive()) {
                return SyncResult::failure($masterProductId, (int) $storeId, [
                    'MasterProduct is not active (status=' . $masterProduct->status . ')',
                ]);
            }

            // 2. 更新 MasterProduct 同步状态为 syncing
            $this->merchantDb->run($merchant, function () use ($masterProduct) {
                $masterProduct->update(['sync_status' => MasterProduct::SYNC_SYNCING]);
            });

            // 3. 计算同步后的价格
            $syncedPrice = $this->calculateSyncPrice($masterProduct->base_price, $syncRule);

            // 4. 在 Tenant 上下文中写入商品数据
            $tenantProductId = $store->run(function () use ($masterProduct, $syncedPrice, $merchant) {
                return DB::transaction(function () use ($masterProduct, $syncedPrice, $merchant) {
                    // 幂等写入 jh_products
                    $product = $this->upsertTenantProduct($masterProduct, $syncedPrice, $merchant);

                    // 同步多语言描述
                    $this->syncTenantDescriptions($product, $masterProduct);

                    return $product->id;
                });
            });

            // 5. 更新 MasterProduct 同步状态为 synced
            $this->merchantDb->run($merchant, function () use ($masterProduct) {
                $masterProduct->update([
                    'sync_status'   => MasterProduct::SYNC_SYNCED,
                    'last_synced_at' => now(),
                ]);
            });

            Log::info('[ProductSync] Synced successfully.', [
                'master_product_id' => $masterProductId,
                'store_id'          => $storeId,
                'tenant_product_id' => $tenantProductId,
            ]);

            return SyncResult::success($masterProductId, (int) $storeId, $tenantProductId);

        } catch (\Throwable $e) {
            Log::error('[ProductSync] Sync failed.', [
                'master_product_id' => $masterProductId,
                'store_id'          => $storeId,
                'error'             => $e->getMessage(),
                'trace'             => $e->getTraceAsString(),
            ]);

            // 回滚 MasterProduct 同步状态为 failed
            try {
                $this->merchantDb->run($merchant, function () use ($masterProductId) {
                    MasterProduct::where('id', $masterProductId)
                        ->update(['sync_status' => MasterProduct::SYNC_FAILED]);
                });
            } catch (\Throwable) {
                // 状态回滚失败不影响主流程
            }

            return SyncResult::failure($masterProductId, (int) $storeId, [$e->getMessage()]);
        }
    }

    /* ================================================================
     |  批量同步
     | ================================================================ */

    /**
     * 批量同步多个 MasterProduct 到指定 Store
     *
     * 预加载关联减少查询，分批处理（每批 50 条），记录成功/失败统计。
     *
     * @param  Merchant      $merchant
     * @param  array<int>    $masterProductIds
     * @param  Store         $store
     * @param  SyncRule|null $syncRule
     * @return BatchSyncResult
     */
    public function batchSync(
        Merchant $merchant,
        array $masterProductIds,
        Store $store,
        ?SyncRule $syncRule = null,
    ): BatchSyncResult {
        $startTime = microtime(true);
        $results   = [];
        $skipped   = 0;

        // 分批处理
        $chunks = array_chunk($masterProductIds, self::BATCH_SIZE);

        foreach ($chunks as $chunkIds) {
            // 预加载本批次的 MasterProduct（含 translations）
            $masterProducts = $this->merchantDb->run($merchant, function () use ($chunkIds) {
                return MasterProduct::with('translations')
                    ->whereIn('id', $chunkIds)
                    ->get();
            });

            // 检查是否有 ID 不存在的商品
            $foundIds = $masterProducts->pluck('id')->all();
            $missingIds = array_diff($chunkIds, $foundIds);
            foreach ($missingIds as $missingId) {
                $results[] = SyncResult::failure($missingId, (int) $store->getTenantKey(), [
                    'MasterProduct not found',
                ]);
            }

            // 逐个同步
            foreach ($masterProducts as $masterProduct) {
                if (!$masterProduct->isActive()) {
                    $skipped++;
                    continue;
                }

                $results[] = $this->syncToStore($merchant, $masterProduct->id, $store, $syncRule);
            }
        }

        $duration = microtime(true) - $startTime;

        $batchResult = BatchSyncResult::fromResults($results, $skipped, $duration);

        Log::info('[ProductSync] Batch sync completed.', [
            'store_id'  => $store->getTenantKey(),
            'total'     => $batchResult->total,
            'succeeded' => $batchResult->succeeded,
            'failed'    => $batchResult->failed,
            'skipped'   => $batchResult->skipped,
            'duration'  => $batchResult->duration,
        ]);

        return $batchResult;
    }

    /**
     * 全量同步：获取该商户所有 active MasterProduct，分批同步到指定 Store
     *
     * @param  Merchant      $merchant
     * @param  Store         $store
     * @param  SyncRule|null $syncRule
     * @return BatchSyncResult
     */
    public function fullSync(
        Merchant $merchant,
        Store $store,
        ?SyncRule $syncRule = null,
    ): BatchSyncResult {
        Log::info('[ProductSync] Starting full sync.', [
            'merchant_id' => $merchant->id,
            'store_id'    => $store->getTenantKey(),
        ]);

        // 获取所有 active 的 MasterProduct ID
        $productIds = $this->merchantDb->run($merchant, function () {
            return MasterProduct::active()->pluck('id')->all();
        });

        if (empty($productIds)) {
            Log::info('[ProductSync] No active products to sync.');
            return BatchSyncResult::fromResults([], 0, 0.0);
        }

        return $this->batchSync($merchant, $productIds, $store, $syncRule);
    }

    /**
     * 增量同步：只同步 updated_at > $since 的商品
     *
     * @param  Merchant            $merchant
     * @param  Store               $store
     * @param  SyncRule|null       $syncRule
     * @param  \Carbon\Carbon|null $since     截止时间（null 时从 SyncRule.last_synced_at 推断）
     * @return BatchSyncResult
     */
    public function incrementalSync(
        Merchant $merchant,
        Store $store,
        ?SyncRule $syncRule = null,
        ?\Carbon\Carbon $since = null,
    ): BatchSyncResult {
        // 推断增量起点
        if ($since === null && $syncRule !== null) {
            $since = $syncRule->last_synced_at ?? null;
        }

        Log::info('[ProductSync] Starting incremental sync.', [
            'merchant_id' => $merchant->id,
            'store_id'    => $store->getTenantKey(),
            'since'       => $since?->toIso8601String(),
        ]);

        // 获取自 $since 以来更新过的 active MasterProduct ID
        $productIds = $this->merchantDb->run($merchant, function () use ($since) {
            $query = MasterProduct::active();

            if ($since !== null) {
                $query->where('updated_at', '>', $since);
            }

            return $query->pluck('id')->all();
        });

        if (empty($productIds)) {
            Log::info('[ProductSync] No products updated since cutoff.');
            return BatchSyncResult::fromResults([], 0, 0.0);
        }

        $result = $this->batchSync($merchant, $productIds, $store, $syncRule);

        // 更新 SyncRule 的 last_synced_at（如果有 SyncRule）
        if ($syncRule !== null) {
            $this->merchantDb->run($merchant, function () use ($syncRule) {
                $syncRule->update(['last_synced_at' => now()]);
            });
        }

        return $result;
    }

    /* ================================================================
     |  内部方法
     | ================================================================ */

    /**
     * 幂等写入 Tenant DB 的 jh_products 表
     *
     * 通过 merchant_id + sync_source_id 做 updateOrCreate：
     * - 已存在 → 更新字段
     * - 不存在 → 创建新记录
     *
     * @param  MasterProduct $master
     * @param  string        $syncedPrice  同步后的价格
     * @param  Merchant      $merchant
     * @return Product
     */
    protected function upsertTenantProduct(
        MasterProduct $master,
        string $syncedPrice,
        Merchant $merchant,
    ): Product {
        $dimensions = $master->dimensions ?? [];

        $productData = [
            'sku'        => $master->sku,
            'sku_prefix' => $this->extractSkuPrefix($master->sku),
            'model'      => $master->sku,
            'price'      => $syncedPrice,
            'cost_price' => $master->base_price,
            'weight'     => $master->weight ?? '0.00',
            'length'     => $dimensions['length'] ?? '0.00',
            'width'      => $dimensions['width'] ?? '0.00',
            'height'     => $dimensions['height'] ?? '0.00',
            'image'      => $master->images[0] ?? null,
            'status'     => $master->isActive() ? 1 : 0,
            'synced_at'  => now(),
        ];

        return Product::updateOrCreate(
            [
                'merchant_id'    => $merchant->id,
                'sync_source_id' => $master->id,
            ],
            $productData,
        );
    }

    /**
     * 同步多语言描述到 Tenant DB 的 jh_product_descriptions 表
     *
     * 以 MasterProduct 的 translations 为准：
     * - 已有 locale → 更新
     * - 新 locale → 创建
     * - 不在列表中的 locale → 删除
     *
     * 若无翻译，则用 master 默认名称和描述写入 en 记录。
     *
     * @param  Product       $product
     * @param  MasterProduct $master
     * @return void
     */
    protected function syncTenantDescriptions(Product $product, MasterProduct $master): void
    {
        $translations = $master->translations;

        // 若无翻译，用默认英文记录
        if ($translations->isEmpty()) {
            ProductDescription::updateOrCreate(
                ['product_id' => $product->id, 'locale' => 'en'],
                [
                    'name'             => $master->name,
                    'description'      => $master->description,
                    'short_description' => '',
                    'meta_title'       => $master->name,
                    'meta_description' => Str::limit($master->description ?? '', 200),
                    'slug'             => Str::slug($master->name),
                ],
            );

            // 清理其他 locale
            ProductDescription::where('product_id', $product->id)
                ->where('locale', '!=', 'en')
                ->delete();

            return;
        }

        $incomingLocales = [];

        foreach ($translations as $trans) {
            $locale = $trans->locale;
            $incomingLocales[] = $locale;

            ProductDescription::updateOrCreate(
                ['product_id' => $product->id, 'locale' => $locale],
                [
                    'name'             => $trans->name,
                    'description'      => $trans->description,
                    'short_description' => '',
                    'meta_title'       => $trans->meta_title ?: $trans->name,
                    'meta_description' => $trans->meta_description ?: Str::limit($trans->description ?? '', 200),
                    'slug'             => Str::slug($trans->name),
                ],
            );
        }

        // 清除不在列表中的旧 locale
        if (!empty($incomingLocales)) {
            ProductDescription::where('product_id', $product->id)
                ->whereNotIn('locale', $incomingLocales)
                ->delete();
        }
    }

    /**
     * 根据 SyncRule 计算同步后价格
     *
     * 策略：
     * - multiplier: base_price × price_multiplier
     * - fixed / custom / null: 使用原价
     *
     * 中间精度 4 位，输出 2 位。
     *
     * @param  string        $basePrice
     * @param  SyncRule|null $syncRule
     * @return string
     */
    protected function calculateSyncPrice(string $basePrice, ?SyncRule $syncRule): string
    {
        if ($syncRule === null) {
            return bcadd($basePrice, '0', 2);
        }

        return match ($syncRule->price_strategy) {
            SyncRule::PRICE_MULTIPLIER => bcmul(
                $basePrice,
                (string) $syncRule->price_multiplier,
                2,
            ),
            default => bcadd($basePrice, '0', 2),
        };
    }

    /**
     * 从 SKU 中提取前缀分类
     *
     * 例：hic-ABC-001 → hic, WPZ12345 → WPZ, DIY-XXX → DIY
     *
     * @param  string $sku
     * @return string
     */
    protected function extractSkuPrefix(string $sku): string
    {
        // 尝试匹配常见前缀模式
        if (preg_match('/^(hic|WPZ|DIY|NBL)/i', $sku, $matches)) {
            return strtolower($matches[1]) === 'hic' ? 'hic' : strtoupper($matches[1]);
        }

        return '';
    }
}
