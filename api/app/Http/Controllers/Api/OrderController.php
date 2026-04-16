<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * 买家订单列表
     *
     * 返回当前登录买家的订单列表，支持按支付状态筛选分页查询。
     * 默认按创建时间倒序排列。
     * 可选传入 `pay_status` 筛选支付状态、`per_page` 控制分页条数。
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;
        $params     = $request->only(['pay_status', 'per_page']);
        $paginator  = $this->orderService->getBuyerOrders($customerId, $params);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => OrderListResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 创建订单
     *
     * 直接提交订单数据创建订单（运费、商品明细、收货地址均需客户端自行计算提交）。
     * 订单与当前登录买家关联，并自动记录客户 IP 和 User-Agent。
     * 请求体必须包含：`items`（商品明细数组）和 `addresses`（收货/账单地址数组）。
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|integer|min:1',
            'items.*.sku'            => 'required|string|max:100',
            'items.*.name'           => 'required|string|max:255',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.price'          => 'required|numeric|min:0',
            'items.*.total'          => 'required|numeric|min:0',
            'addresses'              => 'required|array|min:1',
            'addresses.*.type'       => 'required|string|in:shipping,billing',
            'addresses.*.firstname'  => 'required|string|max:100',
            'addresses.*.lastname'   => 'required|string|max:100',
            'addresses.*.address_1'  => 'required|string|max:255',
            'addresses.*.city'       => 'required|string|max:100',
            'addresses.*.postcode'   => 'required|string|max:20',
            'addresses.*.country'    => 'required|string|max:100',
            'addresses.*.country_code' => 'required|string|max:10',
            'shipping_fee'           => 'sometimes|numeric|min:0',
            'tax_amount'             => 'sometimes|numeric|min:0',
            'discount_amount'        => 'sometimes|numeric|min:0',
            'coupon_code'            => 'sometimes|nullable|string|max:50',
        ], [
            'items.required'              => '订单商品不能为空',
            'items.min'                   => '订单至少包含一件商品',
            'addresses.required'          => '收货地址不能为空',
            'addresses.*.firstname.required' => '收件人名字不能为空',
            'addresses.*.address_1.required' => '地址不能为空',
            'addresses.*.city.required'   => '城市不能为空',
            'addresses.*.postcode.required' => '邮编不能为空',
            'addresses.*.country_code.required' => '国家代码不能为空',
        ]);

        $data                 = $request->all();
        $data['customer_id']  = $request->user()->id;
        $data['customer_email'] = $request->user()->email ?? null;
        $data['customer_name']  = $request->user()->name ?? null;
        $data['ip']           = $request->ip();
        $data['user_agent']   = $request->userAgent();

        $order = $this->orderService->createOrder($data);
        return $this->success(new OrderResource($order), '订单创建成功');
    }

    /**
     * 订单详情
     *
     * 返回当前买家的指定订单详情。
     * 严格验证订单归属，非本人订单一律返回 404。
     */
    public function show(int $id): JsonResponse
    {
        $customerId = request()->user()->id;
        $order      = $this->orderService->getBuyerOrderDetail($customerId, $id);
        return $this->success(new OrderResource($order));
    }

    /**
     * 取消订单
     *
     * 买家主动取消指定订单。
     * 仅允许取消未支付或待处理状态的订单，超过可取消状态将返回错误。
     * 操作同时验证订单归属，非本人订单一律返回 404。
     */
    public function cancel(int $id): JsonResponse
    {
        $customerId = request()->user()->id;

        // 先验证订单归属
        $this->orderService->getBuyerOrderDetail($customerId, $id);

        $order = $this->orderService->cancelOrder($id);
        return $this->success(new OrderListResource($order), '订单已取消');
    }
}
