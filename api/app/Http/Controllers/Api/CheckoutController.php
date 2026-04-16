<?php

namespace App\Http\Controllers\Api;

use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends BaseApiController
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {}

    /**
     * 结账预览
     *
     * 不创建订单，仅计算并返回当前购物车的订单金额明细，供前端展示费用详情。
     * 计算内容包含：商品小计、运费（根据收货地址和配送方式计算）、优惠券折扣、应付总金额。
     * 可选传入 `coupon_code` 应用优惠券、`shipping_method` 选择配送方式。
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code'     => 'nullable|string|max:64',
            'shipping_method' => 'nullable|string|max:64',
        ]);

        $preview = $this->checkoutService->preview(
            $this->getCustomerId($request),
            $this->getSessionId($request),
            $request->only(['coupon_code', 'shipping_method', 'address'])
        );

        return $this->success($preview);
    }

    /**
     * 提交订单
     *
     * 基于当前购物车内容创建正式订单，同时锁定商品库存，清空购物车。
     * 支持登录用户和游客下单。成功后返回订单 ID、订单号、总金额和支付状态。
     * 请求体必须包含收货地址（`shipping_address`）：姓名、详细地址、城市、邮编、国家。
     * 可选包含：`billing_address`（账单地址）、`coupon_code`（优惠券）、`remark`（用户备注）。
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_address'                  => 'required|array',
            'shipping_address.firstname'        => 'required|string|max:64',
            'shipping_address.lastname'         => 'required|string|max:64',
            'shipping_address.address_1'        => 'required|string|max:255',
            'shipping_address.city'             => 'required|string|max:128',
            'shipping_address.postcode'         => 'required|string|max:32',
            'shipping_address.country'          => 'required|string|max:64',
            'shipping_address.phone'            => 'nullable|string|max:32',
            'shipping_address.email'            => 'nullable|email|max:128',
            'billing_address'                   => 'nullable|array',
            'coupon_code'                       => 'nullable|string|max:64',
            'remark'                            => 'nullable|string|max:500',
        ]);

        $order = $this->checkoutService->submit(
            $this->getCustomerId($request),
            $this->getSessionId($request),
            $request->only([
                'shipping_address',
                'billing_address',
                'coupon_code',
                'remark',
            ])
        );

        return $this->success([
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'total'    => $order->total,
            'status'   => $order->pay_status,
        ], '订单提交成功');
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
        return $request->header('X-Session-ID') ?? $request->session()->getId();
    }
}
