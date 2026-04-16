<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CartService
{
    /**
     * 购物车缓存 TTL（秒），7 天
     */
    private const CART_TTL = 7 * 24 * 3600;

    // =========================================================
    // Public API
    // =========================================================

    /**
     * 获取购物车（统一格式）
     */
    public function getCart(?int $customerId, ?string $sessionId): array
    {
        $items = $this->loadRawItems($customerId, $sessionId);
        return $this->buildCartResponse($items);
    }

    /**
     * 加购（含库存校验）
     */
    public function addItem(
        ?int    $customerId,
        ?string $sessionId,
        int     $productId,
        ?int    $skuId,
        int     $quantity
    ): array {
        // 校验商品
        $product = Product::active()->with([
            'skus'         => fn($q) => $q->active(),
            'description',
        ])->find($productId);

        if (!$product) {
            throw new \RuntimeException('商品不存在或已下架', 40401);
        }

        // 校验 SKU
        $sku = null;
        if ($skuId !== null) {
            $sku = $product->skus->firstWhere('id', $skuId);
            if (!$sku) {
                throw new \RuntimeException('商品规格不存在', 40402);
            }
        }

        // 可用库存
        $availableStock = $sku ? (int)$sku->quantity : (int)$product->quantity;
        if ($availableStock <= 0) {
            throw new \RuntimeException('商品库存不足', 40403);
        }

        $items = $this->loadRawItems($customerId, $sessionId);
        $key   = $this->buildItemKey($productId, $skuId);

        // 已存在则叠加数量
        $existingQty = isset($items[$key]) ? (int)$items[$key]['quantity'] : 0;
        $newQty      = $existingQty + $quantity;

        if ($newQty > $availableStock) {
            throw new \RuntimeException("加购数量超过库存上限（最多可购 {$availableStock} 件）", 40404);
        }

        // 写入/更新 item
        $items[$key] = $this->buildRawItem($product, $sku, $newQty);

        $this->saveRawItems($customerId, $sessionId, $items);

        return $this->buildCartResponse($items);
    }

    /**
     * 更新购物车项数量
     */
    public function updateQuantity(
        ?int    $customerId,
        ?string $sessionId,
        string  $itemKey,
        int     $quantity
    ): array {
        $items = $this->loadRawItems($customerId, $sessionId);

        if (!isset($items[$itemKey])) {
            throw new \RuntimeException('购物车项不存在', 40405);
        }

        if ($quantity <= 0) {
            unset($items[$itemKey]);
        } else {
            // 重新校验库存
            $item           = $items[$itemKey];
            $availableStock = $this->getAvailableStock((int)$item['product_id'], $item['sku_id'] ?? null);

            if ($quantity > $availableStock) {
                throw new \RuntimeException("数量超过库存上限（最多可购 {$availableStock} 件）", 40404);
            }

            $items[$itemKey]['quantity'] = $quantity;
            $items[$itemKey]['subtotal'] = round((float)$items[$itemKey]['price'] * $quantity, 2);
        }

        $this->saveRawItems($customerId, $sessionId, $items);

        return $this->buildCartResponse($items);
    }

    /**
     * 删除购物车项
     */
    public function removeItem(
        ?int    $customerId,
        ?string $sessionId,
        string  $itemKey
    ): array {
        $items = $this->loadRawItems($customerId, $sessionId);

        if (!isset($items[$itemKey])) {
            throw new \RuntimeException('购物车项不存在', 40405);
        }

        unset($items[$itemKey]);
        $this->saveRawItems($customerId, $sessionId, $items);

        return $this->buildCartResponse($items);
    }

    /**
     * 清空购物车
     */
    public function clear(?int $customerId, ?string $sessionId): bool
    {
        Cache::store('redis')->forget($this->buildCacheKey($customerId, $sessionId));
        return true;
    }

    /**
     * 登录后合并游客购物车到用户购物车
     */
    public function mergeCarts(int $customerId, string $sessionId): array
    {
        $guestItems = $this->loadRawItems(null, $sessionId);

        if (empty($guestItems)) {
            return $this->getCart($customerId, null);
        }

        $userItems = $this->loadRawItems($customerId, null);

        foreach ($guestItems as $key => $guestItem) {
            if (isset($userItems[$key])) {
                // 叠加数量，但不超库存
                $availableStock = $this->getAvailableStock(
                    (int)$guestItem['product_id'],
                    $guestItem['sku_id'] ?? null
                );
                $mergedQty = min(
                    (int)$userItems[$key]['quantity'] + (int)$guestItem['quantity'],
                    $availableStock
                );
                $userItems[$key]['quantity'] = $mergedQty;
                $userItems[$key]['subtotal'] = round((float)$userItems[$key]['price'] * $mergedQty, 2);
            } else {
                $userItems[$key] = $guestItem;
            }
        }

        $this->saveRawItems($customerId, null, $userItems);

        // 清除游客购物车
        Cache::store('redis')->forget($this->buildCacheKey(null, $sessionId));

        return $this->buildCartResponse($userItems);
    }

    /**
     * 购物车摘要（数量、小计、预估运费）
     */
    public function getCartSummary(?int $customerId, ?string $sessionId): array
    {
        $cart = $this->getCart($customerId, $sessionId);

        return [
            'item_count'      => $cart['item_count'],
            'subtotal'        => $cart['total'],
            'shipping_fee'    => 0.00,   // 预留：物流服务计算
            'tax'             => 0.00,   // 预留：税务计算
            'discount'        => 0.00,   // 预留：优惠券计算
            'estimated_total' => $cart['total'],
        ];
    }

    // =========================================================
    // Internal Helpers
    // =========================================================

    /**
     * 构建缓存 Key
     */
    private function buildCacheKey(?int $customerId, ?string $sessionId): string
    {
        if ($customerId) {
            return "cart:{$customerId}";
        }
        return "cart:guest:{$sessionId}";
    }

    /**
     * 构建购物车项唯一键
     */
    private function buildItemKey(int $productId, ?int $skuId): string
    {
        return $skuId ? "{$productId}_{$skuId}" : "{$productId}_0";
    }

    /**
     * 从缓存加载原始 items
     *
     * @return array<string, array>
     */
    private function loadRawItems(?int $customerId, ?string $sessionId): array
    {
        if (!$customerId && !$sessionId) {
            return [];
        }
        $cacheKey = $this->buildCacheKey($customerId, $sessionId);
        return Cache::store('redis')->get($cacheKey, []);
    }

    /**
     * 保存原始 items 到缓存
     */
    private function saveRawItems(?int $customerId, ?string $sessionId, array $items): void
    {
        $cacheKey = $this->buildCacheKey($customerId, $sessionId);
        Cache::store('redis')->put($cacheKey, $items, self::CART_TTL);
    }

    /**
     * 构建原始 item 数据（存入缓存的结构）
     */
    private function buildRawItem(Product $product, ?ProductSku $sku, int $quantity): array
    {
        $price = $sku
            ? (float)$sku->price
            : (float)$product->effective_price;

        $name    = $product->description?->name ?? $product->model ?? '';
        $image   = $sku?->image ?? $product->image;
        $skuName = $sku ? $this->buildSkuName($sku) : null;

        $availableStock = $sku ? (int)$sku->quantity : (int)$product->quantity;

        return [
            'key'          => $this->buildItemKey($product->id, $sku?->id),
            'product_id'   => $product->id,
            'sku_id'       => $sku?->id,
            'name'         => $name,
            'image'        => $image,
            'sku_name'     => $skuName,
            'price'        => round($price, 2),
            'quantity'     => $quantity,
            'subtotal'     => round($price * $quantity, 2),
            'in_stock'     => $availableStock > 0,
            'max_quantity' => $availableStock,
        ];
    }

    /**
     * 从 SKU option_values 构建展示名
     */
    private function buildSkuName(ProductSku $sku): ?string
    {
        if (empty($sku->option_values) || !is_array($sku->option_values)) {
            return $sku->sku;
        }
        // option_values 格式: [{'name': 'Color', 'value': 'Red'}, ...]
        $parts = [];
        foreach ($sku->option_values as $opt) {
            if (isset($opt['value'])) {
                $parts[] = $opt['value'];
            }
        }
        return implode(' / ', $parts) ?: $sku->sku;
    }

    /**
     * 构建统一返回格式的购物车
     */
    private function buildCartResponse(array $items): array
    {
        $list       = array_values($items);
        $itemCount  = array_sum(array_column($list, 'quantity'));
        $total      = round(array_sum(array_column($list, 'subtotal')), 2);

        return [
            'items'      => $list,
            'item_count' => $itemCount,
            'total'      => $total,
        ];
    }

    /**
     * 实时查询可用库存（用于更新时校验）
     */
    private function getAvailableStock(int $productId, ?int $skuId): int
    {
        if ($skuId) {
            $sku = ProductSku::find($skuId);
            return $sku ? (int)$sku->quantity : 0;
        }
        $product = Product::find($productId);
        return $product ? (int)$product->quantity : 0;
    }
}
