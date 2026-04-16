<?php

namespace App\Services;

use App\Enums\MappingScenario;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductDescription;
use App\Models\ProductImage;
use App\Models\ProductSku;
use App\Models\ProductAttributeValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    public function __construct(
        private readonly ProductMappingService $mappingService
    ) {}

    // =========================================================
    // Admin Methods
    // =========================================================

    /**
     * 后台商品分页列表（含安全映射名）
     */
    public function getList(array $params): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['description', 'images' => fn($q) => $q->where('is_main', 1)])
            ->withCount('skus');

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $kw = $params['keyword'];
            $query->whereHas('descriptions', function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('description', 'like', "%{$kw}%");
            })->orWhere('sku', 'like', "%{$kw}%")
              ->orWhere('model', 'like', "%{$kw}%");
        }

        // 分类过滤
        if (!empty($params['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('jh_categories.id', $params['category_id']));
        }

        // 状态过滤
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        // SKU 前缀过滤
        if (!empty($params['sku_prefix'])) {
            $query->where('sku_prefix', $params['sku_prefix']);
        }

        // 价格区间
        if (!empty($params['price_min'])) {
            $query->where('price', '>=', $params['price_min']);
        }
        if (!empty($params['price_max'])) {
            $query->where('price', '<=', $params['price_max']);
        }

        // 排序
        $sortField = $params['sort_field'] ?? 'created_at';
        $sortDir   = $params['sort_dir'] ?? 'desc';
        $allowedSort = ['price', 'created_at', 'sort_order', 'sold', 'quantity'];
        if (!in_array($sortField, $allowedSort)) {
            $sortField = 'created_at';
        }
        $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');

        $perPage = min((int)($params['per_page'] ?? 20), 100);
        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        // 注入安全映射名（Admin 场景）
        $paginator->through(function (Product $product) {
            $safeInfo = $this->mappingService->getSafeProductInfo($product, MappingScenario::ADMIN);
            $product->setAttribute('safe_name', $safeInfo['name']);
            $product->setAttribute('safe_description', $safeInfo['description']);
            $product->setAttribute('should_replace', $safeInfo['should_replace']);
            return $product;
        });

        return $paginator;
    }

    /**
     * 后台商品详情（含所有语言描述、图片、SKU、属性）
     */
    public function getById(int $id): Product
    {
        $product = Product::with([
            'descriptions',
            'images',
            'skus',
            'attributeValues.attribute',
            'categories',
            'safeMapping.safeProduct',
        ])->findOrFail($id);

        $safeInfo = $this->mappingService->getSafeProductInfo($product, MappingScenario::ADMIN);
        $product->setAttribute('safe_name', $safeInfo['name']);
        $product->setAttribute('safe_description', $safeInfo['description']);
        $product->setAttribute('should_replace', $safeInfo['should_replace']);

        return $product;
    }

    /**
     * 创建商品（含多语言描述、图片、SKU、属性，事务包裹）
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // 自动识别 SKU 前缀
            if (!empty($data['sku'])) {
                $category = $this->mappingService->identifySku($data['sku']);
                $data['sku_prefix'] = $category->value;
            }

            $productData = $this->extractProductFields($data);
            $product = Product::create($productData);

            // 处理分类关联
            if (!empty($data['category_ids'])) {
                $product->categories()->sync($data['category_ids']);
            }

            // 处理多语言描述
            if (!empty($data['descriptions'])) {
                $this->syncDescriptions($product->id, $data['descriptions']);
            }

            // 处理图片
            if (!empty($data['images'])) {
                $this->syncImages($product->id, $data['images']);
            }

            // 处理 SKU 变体
            if (!empty($data['skus'])) {
                $this->syncSkus($product->id, $data['skus']);
            }

            // 处理属性值
            if (!empty($data['attributes'])) {
                $this->syncAttributeValues($product->id, $data['attributes']);
            }

            return $product->load(['descriptions', 'images', 'skus', 'attributeValues.attribute', 'categories']);
        });
    }

    /**
     * 更新商品（支持部分更新）
     */
    public function update(int $id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);

            // 如果更新了 SKU 则重新识别前缀
            if (!empty($data['sku'])) {
                $category = $this->mappingService->identifySku($data['sku']);
                $data['sku_prefix'] = $category->value;
            }

            $productData = $this->extractProductFields($data);
            $product->update($productData);

            if (array_key_exists('category_ids', $data)) {
                $product->categories()->sync($data['category_ids'] ?? []);
            }

            if (!empty($data['descriptions'])) {
                $this->syncDescriptions($product->id, $data['descriptions']);
            }

            if (array_key_exists('images', $data)) {
                $this->syncImages($product->id, $data['images'] ?? []);
            }

            if (array_key_exists('skus', $data)) {
                $this->syncSkus($product->id, $data['skus'] ?? []);
            }

            if (array_key_exists('attributes', $data)) {
                $this->syncAttributeValues($product->id, $data['attributes'] ?? []);
            }

            $this->mappingService->clearProductCache($product->id);

            return $product->fresh(['descriptions', 'images', 'skus', 'attributeValues.attribute', 'categories']);
        });
    }

    /**
     * 删除商品（软删除，检查未完成订单）
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $product = Product::findOrFail($id);

            // 检查是否有未完成订单
            $pendingOrders = DB::table('jh_order_items')
                ->join('jh_orders', 'jh_order_items.order_id', '=', 'jh_orders.id')
                ->where('jh_order_items.product_id', $id)
                ->whereNotIn('jh_orders.status', [4, 5]) // 4=完成 5=取消
                ->exists();

            if ($pendingOrders) {
                throw new \RuntimeException('商品存在未完成的订单，无法删除');
            }

            $product->status = ProductStatus::DISABLED;
            $product->save();

            $this->mappingService->clearProductCache($id);
            return $product->delete();
        });
    }

    /**
     * 批量删除
     */
    public function bulkDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            try {
                $this->delete((int)$id);
                $count++;
            } catch (\Exception $e) {
                Log::warning("批量删除商品 {$id} 失败: " . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * 更新库存
     *
     * @param string $operation  set|increment|decrement
     */
    public function updateStock(int $id, int $quantity, string $operation = 'set'): Product
    {
        return DB::transaction(function () use ($id, $quantity, $operation) {
            $product = Product::lockForUpdate()->findOrFail($id);

            match ($operation) {
                'increment' => $product->increment('quantity', $quantity),
                'decrement' => $product->decrement('quantity', max($quantity, 0)),
                default     => $product->update(['quantity' => max($quantity, 0)]),
            };

            return $product->fresh();
        });
    }

    /**
     * 切换商品启用/禁用状态
     */
    public function toggleStatus(int $id): Product
    {
        return DB::transaction(function () use ($id) {
            $product = Product::findOrFail($id);
            $newStatus = $product->status === ProductStatus::ENABLED
                ? ProductStatus::DISABLED
                : ProductStatus::ENABLED;
            $product->update(['status' => $newStatus]);
            $this->mappingService->clearProductCache($id);
            return $product->fresh();
        });
    }

    /**
     * 批量更新状态
     */
    public function bulkUpdateStatus(array $ids, int $status): int
    {
        return Product::whereIn('id', $ids)->update(['status' => $status]);
    }

    // =========================================================
    // Buyer / Storefront Methods
    // =========================================================

    /**
     * 买家端商品列表（仅启用商品，多语言）
     */
    public function getBuyerProducts(array $params, string $locale = 'en'): LengthAwarePaginator
    {
        $query = Product::active()
            ->with([
                'images' => fn($q) => $q->where('is_main', 1),
                'descriptions' => fn($q) => $q->where('locale', $locale),
            ]);

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $kw = $params['keyword'];
            $query->whereHas('descriptions', function ($q) use ($kw, $locale) {
                $q->where('locale', $locale)
                  ->where(function ($q2) use ($kw) {
                      $q2->where('name', 'like', "%{$kw}%")
                         ->orWhere('description', 'like', "%{$kw}%");
                  });
            });
        }

        // 分类过滤
        if (!empty($params['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('jh_categories.id', $params['category_id']));
        }

        // 价格区间
        if (!empty($params['price_min'])) {
            $query->where('price', '>=', $params['price_min']);
        }
        if (!empty($params['price_max'])) {
            $query->where('price', '<=', $params['price_max']);
        }

        // 属性过滤（格式：attribute_id:value）
        if (!empty($params['attributes'])) {
            foreach ((array)$params['attributes'] as $attrFilter) {
                [$attrId, $value] = explode(':', $attrFilter, 2) + [null, null];
                if ($attrId && $value) {
                    $query->whereHas('attributeValues', fn($q) =>
                        $q->where('attribute_id', $attrId)->where('value', $value)
                    );
                }
            }
        }

        // 是否精选
        if (!empty($params['featured'])) {
            $query->featured();
        }

        // 排序
        $sort = $params['sort'] ?? 'latest';
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'best_seller' => $query->orderBy('sold', 'desc'),
            default       => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min((int)($params['per_page'] ?? 20), 100);
        return $query->paginate($perPage);
    }

    /**
     * 买家端商品详情
     */
    public function getBuyerProductDetail(int $id, string $locale = 'en'): Product
    {
        $product = Product::active()
            ->with([
                'descriptions' => fn($q) => $q->where('locale', $locale),
                'images',
                'skus' => fn($q) => $q->active()->orderBy('sort_order'),
                'attributeValues.attribute',
                'categories.descriptions' => fn($q) => $q->where('locale', $locale),
            ])
            ->findOrFail($id);

        // 相关商品（同分类，最多 8 个）
        $categoryIds = $product->categories->pluck('id');
        if ($categoryIds->isNotEmpty()) {
            $related = Product::active()
                ->whereHas('categories', fn($q) => $q->whereIn('jh_categories.id', $categoryIds))
                ->where('id', '!=', $id)
                ->with(['images' => fn($q) => $q->where('is_main', 1), 'descriptions' => fn($q) => $q->where('locale', $locale)])
                ->limit(8)
                ->get();
            $product->setAttribute('related_products', $related);
        } else {
            $product->setAttribute('related_products', collect());
        }

        // 增加浏览量
        $product->increment('viewed');

        return $product;
    }

    /**
     * 搜索（关键词 + 分面过滤）
     */
    public function search(string $keyword, array $filters = [], string $locale = 'en'): LengthAwarePaginator
    {
        $filters['keyword'] = $keyword;
        return $this->getBuyerProducts($filters, $locale);
    }

    /**
     * 按分类查询买家端商品
     */
    public function getByCategory(int $categoryId, array $params = [], string $locale = 'en'): LengthAwarePaginator
    {
        $params['category_id'] = $categoryId;
        return $this->getBuyerProducts($params, $locale);
    }

    // =========================================================
    // Internal Helper Methods
    // =========================================================

    /**
     * 提取 Product 模型字段（过滤掉关联数据）
     */
    private function extractProductFields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'model', 'sku', 'sku_prefix', 'price', 'cost_price',
            'special_price', 'special_start_at', 'special_end_at',
            'quantity', 'stock_status', 'subtract_stock',
            'weight', 'length', 'width', 'height', 'image',
            'minimum', 'sort_order', 'status', 'is_featured',
            'requires_shipping', 'merchant_id', 'upc', 'ean', 'isbn', 'mpn',
        ]));
    }

    /**
     * 同步多语言描述（upsert 逻辑）
     */
    private function syncDescriptions(int $productId, array $descriptions): void
    {
        foreach ($descriptions as $desc) {
            if (empty($desc['locale'])) {
                continue;
            }
            ProductDescription::updateOrCreate(
                ['product_id' => $productId, 'locale' => $desc['locale']],
                array_intersect_key($desc, array_flip([
                    'name', 'description', 'short_description',
                    'meta_title', 'meta_description', 'meta_keywords',
                    'slug', 'tag',
                ]))
            );
        }
    }

    /**
     * 同步商品图片（先清除再重建）
     */
    private function syncImages(int $productId, array $images): void
    {
        ProductImage::where('product_id', $productId)->delete();

        $records = [];
        foreach ($images as $index => $img) {
            if (empty($img['image'])) {
                continue;
            }
            $records[] = [
                'product_id' => $productId,
                'image'      => $img['image'],
                'is_main'    => (int)($img['is_main'] ?? ($index === 0 ? 1 : 0)),
                'sort_order' => (int)($img['sort_order'] ?? $index),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($records)) {
            ProductImage::insert($records);
        }
    }

    /**
     * 同步 SKU 变体（先清除再重建）
     */
    private function syncSkus(int $productId, array $skus): void
    {
        ProductSku::where('product_id', $productId)->delete();

        $records = [];
        foreach ($skus as $index => $sku) {
            if (empty($sku['sku'])) {
                continue;
            }
            $records[] = [
                'product_id'   => $productId,
                'sku'          => $sku['sku'],
                'price'        => $sku['price'] ?? 0,
                'cost_price'   => $sku['cost_price'] ?? 0,
                'quantity'     => $sku['quantity'] ?? 0,
                'weight'       => $sku['weight'] ?? 0,
                'image'        => $sku['image'] ?? null,
                'option_values' => isset($sku['option_values']) ? json_encode($sku['option_values']) : null,
                'status'       => $sku['status'] ?? 1,
                'sort_order'   => $sku['sort_order'] ?? $index,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        if (!empty($records)) {
            ProductSku::insert($records);
        }
    }

    /**
     * 同步属性值（先清除再重建）
     */
    private function syncAttributeValues(int $productId, array $attributes): void
    {
        ProductAttributeValue::where('product_id', $productId)->delete();

        $records = [];
        foreach ($attributes as $attr) {
            if (empty($attr['attribute_id']) || !isset($attr['value'])) {
                continue;
            }
            $records[] = [
                'product_id'   => $productId,
                'attribute_id' => $attr['attribute_id'],
                'value'        => $attr['value'],
                'locale'       => $attr['locale'] ?? 'en',
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        if (!empty($records)) {
            ProductAttributeValue::insert($records);
        }
    }
}
