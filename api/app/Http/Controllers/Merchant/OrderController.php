<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\OrderPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use App\Models\Tenant\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 商户后台订单控制器（只读）
 *
 * 支持单站点查询和跨站点聚合查询。
 * 所有 Tenant DB 查询均通过 Store::run() 封装。
 */
class OrderController extends Controller
{
    /**
     * 跨站点订单列表
     *
     * GET /api/v1/merchant/orders
     *
     * Query 参数：
     *  - store_id   : 限定单个站点（可选）
     *  - status     : 支付状态筛选，对应 OrderPaymentStatus 枚举值（可选）
     *  - date_from  : 开始日期 Y-m-d（可选）
     *  - date_to    : 结束日期 Y-m-d（可选）
     *  - page       : 页码（默认 1）
     *  - per_page   : 每页条数（默认 20，最大 100）
     */
    public function index(Request $request): JsonResponse
    {
        /** @var MerchantUser $user */
        $user    = $request->user('merchant');
        $storeId = $request->input('store_id');

        if ($storeId) {
            // 单站点查询
            $store = Store::find((int) $storeId);

            if (!$store) {
                return response()->json(['code' => 404, 'message' => 'Store not found'], 404);
            }

            // 验证权限：店铺必须属于同一商户，且用户有访问权限
            if ((int) $store->merchant_id !== (int) $user->merchant_id
                || !$user->canAccessStore((int) $storeId)
            ) {
                return response()->json(['code' => 403, 'message' => 'Access denied'], 403);
            }

            $result = $this->getStoreOrders($store, $request);
        } else {
            // 跨站点聚合查询
            $result = $this->getAggregatedOrders($user, $request);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'list'    => $result['list'],
            'meta'    => $result['meta'],
        ]);
    }

    /**
     * 订单详情
     *
     * GET /api/v1/merchant/orders/{id}?store_id={store_id}
     *
     * 必须传入 store_id，因为订单数据位于 Tenant DB。
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $user */
        $user    = $request->user('merchant');
        $storeId = $request->input('store_id');

        if (!$storeId) {
            return response()->json(['code' => 422, 'message' => 'store_id is required'], 422);
        }

        $store = Store::find((int) $storeId);

        if (!$store) {
            return response()->json(['code' => 404, 'message' => 'Store not found'], 404);
        }

        // 验证权限
        if ((int) $store->merchant_id !== (int) $user->merchant_id
            || !$user->canAccessStore((int) $storeId)
        ) {
            return response()->json(['code' => 403, 'message' => 'Access denied'], 403);
        }

        $order = $store->run(function () use ($id) {
            return Order::with(['items', 'shippingAddress', 'billingAddress', 'histories'])
                ->find($id);
        });

        if (!$order) {
            return response()->json(['code' => 404, 'message' => 'Order not found'], 404);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => array_merge(
                $order->toArray(),
                ['store_id' => $store->id, 'store_name' => $store->store_name]
            ),
        ]);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 在单个站点上下文中分页查询订单
     *
     * @return array{list: array, meta: array}
     */
    protected function getStoreOrders(Store $store, Request $request): array
    {
        $perPage  = min((int) $request->input('per_page', 20), 100);
        $page     = max((int) $request->input('page', 1), 1);
        $status   = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $result = $store->run(function () use ($perPage, $page, $status, $dateFrom, $dateTo, $store) {
            $query = Order::query()->orderByDesc('created_at');

            if ($status !== null) {
                $query->where('pay_status', (int) $status);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $list = collect($paginator->items())->map(fn (Order $order) => array_merge(
                $this->formatOrder($order),
                ['store_id' => $store->id, 'store_name' => $store->store_name]
            ))->all();

            return [
                'list' => $list,
                'meta' => [
                    'total'        => $paginator->total(),
                    'per_page'     => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ];
        });

        return $result;
    }

    /**
     * 遍历可访问站点聚合查询订单
     *
     * 聚合模式下不支持精确分页（跨库），返回各站点拼接后的结果并截取。
     *
     * @return array{list: array, meta: array}
     */
    protected function getAggregatedOrders(MerchantUser $user, Request $request): array
    {
        $perPage  = min((int) $request->input('per_page', 20), 100);
        $page     = max((int) $request->input('page', 1), 1);
        $status   = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $stores = $this->getAccessibleStores($user);
        $allOrders = [];

        foreach ($stores as $store) {
            try {
                $storeOrders = $store->run(function () use ($status, $dateFrom, $dateTo, $store) {
                    $query = Order::query()->orderByDesc('created_at')->limit(200); // 每店最多取 200 条防止过大

                    if ($status !== null) {
                        $query->where('pay_status', (int) $status);
                    }

                    if ($dateFrom) {
                        $query->whereDate('created_at', '>=', $dateFrom);
                    }

                    if ($dateTo) {
                        $query->whereDate('created_at', '<=', $dateTo);
                    }

                    return $query->get()->map(fn (Order $order) => array_merge(
                        $this->formatOrder($order),
                        ['store_id' => $store->id, 'store_name' => $store->store_name]
                    ))->all();
                });

                $allOrders = array_merge($allOrders, $storeOrders);
            } catch (\Throwable) {
                // 某个站点失败，跳过
            }
        }

        // 按 created_at 降序排序
        usort($allOrders, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $total  = count($allOrders);
        $offset = ($page - 1) * $perPage;
        $items  = array_slice($allOrders, $offset, $perPage);

        return [
            'list' => array_values($items),
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage) ?: 1,
            ],
        ];
    }

    /**
     * 获取当前用户可访问的店铺列表
     */
    protected function getAccessibleStores(MerchantUser $user): Collection
    {
        $query = Store::where('merchant_id', $user->merchant_id)
            ->withoutTrashed();

        if (!in_array($user->role, ['owner', 'admin'], true) && $user->allowed_store_ids !== null) {
            $query->whereIn('id', $user->allowed_store_ids);
        }

        return $query->get();
    }

    /**
     * 格式化订单数据，屏蔽敏感字段
     */
    protected function formatOrder(Order $order): array
    {
        return [
            'id'              => $order->id,
            'order_no'        => $order->order_no,
            'a_order_no'      => $order->a_order_no,
            'customer_name'   => $order->customer_name,
            'customer_email'  => $order->customer_email,
            'total'           => $order->total,
            'currency'        => $order->currency,
            'pay_status'      => $order->pay_status instanceof \BackedEnum
                ? $order->pay_status->value
                : $order->pay_status,
            'shipment_status' => $order->shipment_status instanceof \BackedEnum
                ? $order->shipment_status->value
                : $order->shipment_status,
            'pay_time'        => $order->pay_time?->toIso8601String(),
            'created_at'      => $order->created_at?->toIso8601String(),
        ];
    }
}
