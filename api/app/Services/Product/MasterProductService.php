<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Models\Central\Merchant;
use App\Models\Merchant\MasterProduct;
use App\Models\Merchant\MasterProductTranslation;
use App\Services\MerchantDatabaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 主商品 CRUD 服务
 *
 * 封装商户主商品库（Merchant DB）的增删改查、多语言翻译管理。
 * 所有数据库操作通过 MerchantDatabaseService::run() 切入商户库上下文。
 */
class MasterProductService
{
    public function __construct(
        protected readonly MerchantDatabaseService $merchantDb,
    ) {}

    /* ================================================================
     |  查询
     | ================================================================ */

    /**
     * 主商品列表（分页 + 筛选）
     *
     * @param  Merchant $merchant  当前商户
     * @param  array    $filters   可选筛选项：
     *   - category_l1_id  int    一级品类
     *   - category_l2_id  int    二级品类
     *   - status          int    状态（0=inactive,1=active,2=draft）
     *   - sync_status     string 同步状态
     *   - keyword         string SKU 或名称模糊搜索
     *   - per_page        int    每页条数（默认 20，最大 100）
     *   - page            int    页码
     * @return LengthAwarePaginator
     */
    public function list(Merchant $merchant, array $filters = []): LengthAwarePaginator
    {
        return $this->merchantDb->run($merchant, function () use ($filters) {
            $query = MasterProduct::query()
                ->with('translations')
                ->orderByDesc('id');

            // 一级品类筛选
            if (isset($filters['category_l1_id'])) {
                $query->where('category_l1_id', (int) $filters['category_l1_id']);
            }

            // 二级品类筛选
            if (isset($filters['category_l2_id'])) {
                $query->where('category_l2_id', (int) $filters['category_l2_id']);
            }

            // 状态筛选
            if (isset($filters['status']) && $filters['status'] !== '') {
                $query->where('status', (int) $filters['status']);
            }

            // 同步状态筛选
            if (!empty($filters['sync_status'])) {
                $query->where('sync_status', $filters['sync_status']);
            }

            // 关键词搜索（SKU / 名称）
            if (!empty($filters['keyword'])) {
                $keyword = $filters['keyword'];
                $query->where(function ($q) use ($keyword) {
                    $q->where('sku', 'LIKE', "%{$keyword}%")
                      ->orWhere('name', 'LIKE', "%{$keyword}%");
                });
            }

            $perPage = min((int) ($filters['per_page'] ?? 20), 100);

            return $query->paginate($perPage);
        });
    }

    /**
     * 主商品详情
     *
     * @param  Merchant $merchant
     * @param  int      $productId
     * @return MasterProduct
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show(Merchant $merchant, int $productId): MasterProduct
    {
        return $this->merchantDb->run($merchant, function () use ($productId) {
            return MasterProduct::with('translations')->findOrFail($productId);
        });
    }

    /* ================================================================
     |  创建 / 更新
     | ================================================================ */

    /**
     * 创建主商品（含多语言翻译）
     *
     * @param  Merchant $merchant
     * @param  array    $data  商品字段 + 可选 translations 数组
     * @return MasterProduct
     */
    public function create(Merchant $merchant, array $data): MasterProduct
    {
        return $this->merchantDb->run($merchant, function () use ($data, $merchant) {
            return DB::connection('merchant')->transaction(function () use ($data, $merchant) {
                $translations = $data['translations'] ?? [];
                unset($data['translations']);

                // 创建主商品
                $product = MasterProduct::create($data);

                // 创建翻译记录
                if (!empty($translations)) {
                    $this->syncTranslations($product, $translations);
                }

                Log::info('[MasterProduct] Created.', [
                    'merchant_id' => $merchant->id,
                    'product_id'  => $product->id,
                    'sku'         => $product->sku,
                ]);

                return $product->load('translations');
            });
        });
    }

    /**
     * 更新主商品（含多语言翻译同步）
     *
     * translations 采用 sync 策略：以传入的 locale 列表为准，
     * 新增不存在的、更新已有的、删除不在列表中的。
     *
     * @param  Merchant $merchant
     * @param  int      $productId
     * @param  array    $data
     * @return MasterProduct
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(Merchant $merchant, int $productId, array $data): MasterProduct
    {
        return $this->merchantDb->run($merchant, function () use ($data, $productId, $merchant) {
            return DB::connection('merchant')->transaction(function () use ($data, $productId, $merchant) {
                $product = MasterProduct::findOrFail($productId);

                $translations = $data['translations'] ?? null;
                unset($data['translations']);

                // 更新基础字段
                if (!empty($data)) {
                    $product->update($data);
                }

                // 同步翻译（仅当传入 translations 字段时）
                if ($translations !== null) {
                    $this->syncTranslations($product, $translations);
                }

                // 标记需要重新同步
                if ($product->sync_status === MasterProduct::SYNC_SYNCED) {
                    $product->markForSync();
                }

                Log::info('[MasterProduct] Updated.', [
                    'merchant_id' => $merchant->id,
                    'product_id'  => $product->id,
                ]);

                return $product->fresh('translations');
            });
        });
    }

    /* ================================================================
     |  删除
     | ================================================================ */

    /**
     * 删除单个主商品
     *
     * 若商品已同步（sync_status = synced），执行软删除（标记 inactive）；
     * 否则直接硬删除（translations 通过外键 CASCADE 自动删除）。
     *
     * @param  Merchant $merchant
     * @param  int      $productId
     * @return bool
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(Merchant $merchant, int $productId): bool
    {
        return $this->merchantDb->run($merchant, function () use ($productId, $merchant) {
            $product = MasterProduct::findOrFail($productId);

            if ($product->sync_status === MasterProduct::SYNC_SYNCED) {
                // 已同步的商品不硬删，改为禁用
                $product->update(['status' => MasterProduct::STATUS_INACTIVE]);

                Log::info('[MasterProduct] Soft-deleted (set inactive).', [
                    'merchant_id' => $merchant->id,
                    'product_id'  => $product->id,
                ]);

                return true;
            }

            // 未同步的直接硬删除（translations 通过 FK CASCADE 删除）
            $product->translations()->delete();
            $product->delete();

            Log::info('[MasterProduct] Hard-deleted.', [
                'merchant_id' => $merchant->id,
                'product_id'  => $productId,
            ]);

            return true;
        });
    }

    /* ================================================================
     |  批量操作
     | ================================================================ */

    /**
     * 批量更新商品状态
     *
     * @param  Merchant $merchant
     * @param  array    $productIds
     * @param  int      $status
     * @return int  受影响行数
     */
    public function batchUpdateStatus(Merchant $merchant, array $productIds, int $status): int
    {
        return $this->merchantDb->run($merchant, function () use ($productIds, $status) {
            return MasterProduct::whereIn('id', $productIds)
                ->update(['status' => $status]);
        });
    }

    /**
     * 批量删除商品
     *
     * @param  Merchant $merchant
     * @param  array    $productIds
     * @return int  受影响行数
     */
    public function batchDelete(Merchant $merchant, array $productIds): int
    {
        return $this->merchantDb->run($merchant, function () use ($productIds) {
            // 先删翻译
            MasterProductTranslation::whereIn('master_product_id', $productIds)->delete();

            return MasterProduct::whereIn('id', $productIds)->delete();
        });
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 验证 SKU 在当前商户库内的唯一性
     *
     * @param  Merchant  $merchant
     * @param  string    $sku
     * @param  int|null  $excludeId  排除的商品 ID（更新时使用）
     * @return bool  true = SKU 可用
     */
    public function validateSku(Merchant $merchant, string $sku, ?int $excludeId = null): bool
    {
        return $this->merchantDb->run($merchant, function () use ($sku, $excludeId) {
            $query = MasterProduct::where('sku', $sku);

            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }

            return !$query->exists();
        });
    }

    /**
     * 同步商品的多语言翻译记录
     *
     * 以传入的 locale 列表为准：
     * - 已存在的 locale → 更新
     * - 新的 locale → 创建
     * - 不在列表中的 locale → 删除
     *
     * @param  MasterProduct $product
     * @param  array         $translations  [['locale'=>'en','name'=>'...', ...], ...]
     * @return void
     */
    protected function syncTranslations(MasterProduct $product, array $translations): void
    {
        $incomingLocales = [];

        foreach ($translations as $trans) {
            $locale = $trans['locale'];
            $incomingLocales[] = $locale;

            $product->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name'             => $trans['name'],
                    'description'      => $trans['description'] ?? null,
                    'meta_title'       => $trans['meta_title'] ?? null,
                    'meta_description' => $trans['meta_description'] ?? null,
                ]
            );
        }

        // 删除不在传入列表中的旧翻译
        if (!empty($incomingLocales)) {
            $product->translations()
                ->whereNotIn('locale', $incomingLocales)
                ->delete();
        }
    }
}
