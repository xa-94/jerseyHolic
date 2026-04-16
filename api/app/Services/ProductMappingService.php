<?php

namespace App\Services;

use App\Enums\MappingScenario;
use App\Enums\MappingType;
use App\Enums\SkuCategory;
use App\Models\Product;
use App\Models\ProductSafeMapping;
use App\Models\SafeProduct;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProductMappingService
{
    private const CACHE_PREFIX = 'product_mapping:';
    private const CACHE_TTL = 3600;
    private const FALLBACK_SAFE_NAME = 'Sports Training Jersey';
    private const FALLBACK_SAFE_DESC = 'High quality sports training jersey for athletic activities.';

    public function getSafeProductInfo(Product $product, MappingScenario $scenario): array
    {
        if (in_array($scenario, [MappingScenario::STOREFRONT, MappingScenario::PIXEL])) {
            return [
                'name' => $product->name ?? '',
                'description' => $product->description ?? '',
                'should_replace' => false,
            ];
        }

        $cacheKey = self::CACHE_PREFIX . $product->id;
        $safeInfo = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($product) {
            return $this->resolveSafeInfo($product);
        });

        if ($scenario === MappingScenario::ADMIN) {
            return [
                'name' => $safeInfo['name'],
                'description' => $safeInfo['description'],
                'real_name' => $product->name ?? '',
                'real_description' => $product->description ?? '',
                'should_replace' => $safeInfo['should_replace'],
            ];
        }

        return $safeInfo;
    }

    private function resolveSafeInfo(Product $product): array
    {
        $exactMapping = ProductSafeMapping::where('product_id', $product->id)
            ->where('mapping_type', MappingType::EXACT)
            ->with('safeProduct')
            ->first();

        if ($exactMapping && $exactMapping->safeProduct) {
            return [
                'name' => $exactMapping->safeProduct->name,
                'description' => $exactMapping->safeProduct->description ?? self::FALLBACK_SAFE_DESC,
                'should_replace' => true,
            ];
        }

        $sku = $product->sku ?? '';
        $category = SkuCategory::fromSku($sku);

        if (!$category->needsSafeNameReplacement()) {
            return [
                'name' => $product->name ?? '',
                'description' => $product->description ?? '',
                'should_replace' => false,
            ];
        }

        $defaultName = $category->defaultSafeName();
        if (empty($defaultName)) {
            $defaultName = self::FALLBACK_SAFE_NAME;
        }

        return [
            'name' => $defaultName,
            'description' => self::FALLBACK_SAFE_DESC,
            'should_replace' => true,
        ];
    }

    public function identifySku(string $sku): SkuCategory
    {
        return SkuCategory::fromSku($sku);
    }

    public function createMapping(int $productId, int $safeProductId, string $type = 'exact'): ProductSafeMapping
    {
        $mapping = ProductSafeMapping::create([
            'product_id' => $productId,
            'safe_product_id' => $safeProductId,
            'mapping_type' => $type,
        ]);

        $this->clearProductCache($productId);
        return $mapping;
    }

    public function updateMapping(int $mappingId, array $data): ProductSafeMapping
    {
        $mapping = ProductSafeMapping::findOrFail($mappingId);
        $mapping->update($data);
        $this->clearProductCache($mapping->product_id);
        return $mapping->fresh();
    }

    public function deleteMapping(int $mappingId): bool
    {
        $mapping = ProductSafeMapping::findOrFail($mappingId);
        $productId = $mapping->product_id;
        $result = $mapping->delete();
        $this->clearProductCache($productId);
        return $result;
    }

    public function getSafeProducts(array $filters = []): LengthAwarePaginator
    {
        $query = SafeProduct::query();

        if (!empty($filters['keyword'])) {
            $query->where('name', 'like', '%' . $filters['keyword'] . '%');
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->orderBy('sort_order')->paginate($filters['per_page'] ?? 20);
    }

    public function createSafeProduct(array $data): SafeProduct
    {
        return SafeProduct::create($data);
    }

    public function updateSafeProduct(int $id, array $data): SafeProduct
    {
        $safeProduct = SafeProduct::findOrFail($id);
        $safeProduct->update($data);
        return $safeProduct->fresh();
    }

    public function deleteSafeProduct(int $id): bool
    {
        return SafeProduct::findOrFail($id)->delete();
    }

    public function clearProductCache(int $productId): void
    {
        Cache::forget(self::CACHE_PREFIX . $productId);
    }

    public function clearAllCache(): void
    {
        Cache::flush();
    }
}
