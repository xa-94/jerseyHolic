<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CartItemRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends BaseApiController
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    /**
     * 获取购物车内容
     *
     * 返回当前购物车的完整内容，包含每个商品条目的名称、SKU、单价、数量。
     * 支持登录用户（以 customer_id 关联）和游客（以 X-Session-ID 头关联）两种模式。
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart(
            $this->getCustomerId($request),
            $this->getSessionId($request)
        );

        return $this->success($cart);
    }

    /**
     * 添加商品到购物车
     *
     * 将指定商品（可选拉取 SKU 变体）加入购物车。
     * 若购物车中已存在相同商品+SKU，则在现有数量基础上女加。
     * 支持登录用户和游客共用。
     * 请求体参数：`product_id`（必填）、`sku_id`（可选）、`quantity`（必填，整数 ≥ 1）。
     */
    public function add(CartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            $this->getCustomerId($request),
            $this->getSessionId($request),
            (int)$request->validated('product_id'),
            $request->validated('sku_id') ? (int)$request->validated('sku_id') : null,
            (int)$request->validated('quantity')
        );

        return $this->success($cart, '已加入购物车');
    }

    /**
     * 更新购物车商品数量
     *
     * 根据 `item_key` 更新指定购物车条目的数量。
     * 当 `quantity` 为 0 时，自动移除该条目。
     * 请求体参数：`item_key`（必填）、`quantity`（必填，整数 ≥ 0）。
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'item_key' => 'required|string',
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = $this->cartService->updateQuantity(
            $this->getCustomerId($request),
            $this->getSessionId($request),
            $request->input('item_key'),
            (int)$request->input('quantity')
        );

        return $this->success($cart);
    }

    /**
     * 移除购物车商品
     *
     * 根据 `item_key` 从购物车中移除指定条目。
     * `item_key` 格式为 `{product_id}_{sku_id}`，由购物车服务自动生成。
     */
    public function remove(Request $request, string $itemKey): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            $this->getCustomerId($request),
            $this->getSessionId($request),
            $itemKey
        );

        return $this->success($cart);
    }

    /**
     * 清空购物车
     *
     * 移除当前用户（登录用户或游客）购物车中的全部商品条目。
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clear(
            $this->getCustomerId($request),
            $this->getSessionId($request)
        );

        return $this->success(null, '购物车已清空');
    }

    /**
     * 获取购物车金额汇总
     *
     * 返回购物车的金额汇总信息，包含：
     * - 商品总数量和总项数
     * - 商品小计金额
     * - 预估运费（如有地址信息）
     * - 应付总金额
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->cartService->getCartSummary(
            $this->getCustomerId($request),
            $this->getSessionId($request)
        );

        return $this->success($summary);
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * 获取当前登录买家 ID（游客返回 null）
     */
    private function getCustomerId(Request $request): ?int
    {
        $user = $request->user('sanctum');
        return $user?->id;
    }

    /**
     * 获取 Session ID（用于游客购物车）
     */
    private function getSessionId(Request $request): ?string
    {
        // 优先使用请求头 X-Session-ID，其次用 Laravel Session ID
        return $request->header('X-Session-ID') ?? $request->session()->getId();
    }
}
