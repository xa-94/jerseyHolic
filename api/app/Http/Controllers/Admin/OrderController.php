<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderShippingStatus;
use App\Http\Requests\Admin\OrderListRequest;
use App\Http\Requests\Admin\RefundRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseAdminController
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * 获取订单列表
     *
     * 返回分页的订单列表，支持按订单号、支付状态、发货状态、创建日期范围、
     * 店馆域名、支付方式等多条件搜索，默认按创建时间倒序排列。
     * 列表包含订单基本信息、商品数量、总金额及状态标签。
     */
    public function index(OrderListRequest $request): JsonResponse
    {
        $params    = $request->validated();
        $paginator = $this->orderService->getAdminList($params);

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
     * 获取订单详情
     *
     * 返回指定订单的完整信息，包含：
     * - 订单商品明细（数量、单价、小计）
     * - 收货地址与账单地址
     * - 支付信息（支付方式、交易流水号）
     * - 物流信息（运单号、物流厂商）
     * - 操作历史记录
     *
     * 订单不存在时返回 404。
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->getById($id);
        return $this->success(new OrderResource($order));
    }

    /**
     * 更新订单支付状态
     *
     * 将订单支付状态更新为指定值（参考 `OrderPaymentStatus` 枚举）。
     * 支持附加备注，并自动记录操作人信息到订单历史。
     * 订单不存在时返回 404。
     */
    public function updatePayStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $status = OrderPaymentStatus::from((int)$request->validated('status'));
        $order  = $this->orderService->updatePaymentStatus(
            $id,
            $status,
            $request->validated('remark'),
            $request->user()?->id
        );
        return $this->success(new OrderListResource($order), '支付状态更新成功');
    }

    /**
     * 更新订单发货状态
     *
     * 将订单发货状态更新为指定值（参考 `OrderShippingStatus` 枚举）。
     * 支持附加备注，并自动记录操作人信息到订单历史。
     * 订单不存在时返回 404。
     */
    public function updateShipStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $status = OrderShippingStatus::from((int)$request->validated('status'));
        $order  = $this->orderService->updateShippingStatus(
            $id,
            $status,
            $request->validated('remark'),
            $request->user()?->id
        );
        return $this->success(new OrderListResource($order), '发货状态更新成功');
    }

    /**
     * 处理订单退款
     *
     * 支持全额退款和部分退款，退款金额不超过实际支付金额。
     * 退款请求自动记录操作人信息，并更新订单支付状态。
     * 订单不存在或金额超限时返回 422。
     */
    public function refund(RefundRequest $request, int $id): JsonResponse
    {
        $data          = $request->validated();
        $data['operator_id'] = $request->user()?->id;
        $order         = $this->orderService->processRefund($id, $data);
        return $this->success(new OrderListResource($order), '退款申请已提交');
    }

    /**
     * 添加订单操作备注
     *
     * 为订单添加一条操作历史备注，可选拉取是否需要通知买家。
     * 备注内容最多 500 字，自动关联操作管理员信息。
     * 请求体参数：`comment`（必填）、`notify_customer`（可选，默认 false）。
     */
    public function addHistory(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'comment'         => 'required|string|max:500',
            'notify_customer' => 'sometimes|boolean',
        ]);

        $history = $this->orderService->addHistory(
            $id,
            $request->input('comment'),
            $request->user()?->id,
            (bool)$request->input('notify_customer', false)
        );

        return $this->success($history, '备注添加成功');
    }

    /**
     * 导出订单数据
     *
     * 按当前筛选条件创建订单导出任务，返回任务 ID（当前为占位，实际导出待实现）。
     * 未来计划支持 CSV/Excel 格式导出，并提供异步下载链接。
     */
    public function export(Request $request): JsonResponse
    {
        return $this->success(['task_id' => null], '导出任务已创建，请稍后查看');
    }
}
