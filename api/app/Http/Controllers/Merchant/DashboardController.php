<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use App\Models\Tenant\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 商户后台仪表盘控制器
 *
 * 提供跨站点聚合数据，包含各站点订单/销售额统计汇总。
 */
class DashboardController extends Controller
{
    /**
     * 仪表盘概览
     *
     * GET /api/v1/merchant/dashboard
     *
     * 遍历当前用户可访问的站点，聚合各站点今日/本周/本月订单量与销售额。
     */
    public function index(Request $request): JsonResponse
    {
        /** @var MerchantUser $user */
        $user     = $request->user('merchant');
        $merchant = $user->merchant;

        $stores = $this->getAccessibleStores($user, $merchant);

        $dashboard = [
            'merchant'       => [
                'id'   => $merchant->id,
                'name' => $merchant->company_name ?? $merchant->name ?? '',
            ],
            'stores_summary' => [],
            'totals'         => [
                'orders_today'   => 0,
                'orders_week'    => 0,
                'orders_month'   => 0,
                'revenue_today'  => 0,
                'revenue_week'   => 0,
                'revenue_month'  => 0,
                'pending_orders' => 0,
            ],
        ];

        foreach ($stores as $store) {
            try {
                $storeData = $store->run(function () {
                    $today     = today();
                    $weekStart = now()->startOfWeek();
                    $monthStart = now()->startOfMonth();

                    return [
                        'orders_today'   => Order::whereDate('created_at', $today)->count(),
                        'orders_week'    => Order::where('created_at', '>=', $weekStart)->count(),
                        'orders_month'   => Order::where('created_at', '>=', $monthStart)->count(),
                        'revenue_today'  => (float) Order::whereDate('created_at', $today)
                            ->whereIn('pay_status', [2, 3, 4, 5]) // paid 及以上状态
                            ->sum('total'),
                        'revenue_week'   => (float) Order::where('created_at', '>=', $weekStart)
                            ->whereIn('pay_status', [2, 3, 4, 5])
                            ->sum('total'),
                        'revenue_month'  => (float) Order::where('created_at', '>=', $monthStart)
                            ->whereIn('pay_status', [2, 3, 4, 5])
                            ->sum('total'),
                        'pending_orders' => Order::where('pay_status', 1)->count(), // pending
                    ];
                });
            } catch (\Throwable $e) {
                // 某个站点查询失败不应导致整个接口报错，降级处理
                $storeData = [
                    'orders_today'   => 0,
                    'orders_week'    => 0,
                    'orders_month'   => 0,
                    'revenue_today'  => 0,
                    'revenue_week'   => 0,
                    'revenue_month'  => 0,
                    'pending_orders' => 0,
                    'error'          => 'Unable to fetch data for this store.',
                ];
            }

            $dashboard['stores_summary'][] = [
                'store_id'   => $store->id,
                'store_name' => $store->store_name,
                'store_code' => $store->store_code,
                'domain'     => $store->domain,
                'status'     => $store->status,
                ...$storeData,
            ];

            // 累计全局汇总（出错的站点数据为 0，不影响计算）
            $dashboard['totals']['orders_today']   += $storeData['orders_today'];
            $dashboard['totals']['orders_week']    += $storeData['orders_week'];
            $dashboard['totals']['orders_month']   += $storeData['orders_month'];
            $dashboard['totals']['revenue_today']  += $storeData['revenue_today'];
            $dashboard['totals']['revenue_week']   += $storeData['revenue_week'];
            $dashboard['totals']['revenue_month']  += $storeData['revenue_month'];
            $dashboard['totals']['pending_orders'] += $storeData['pending_orders'];
        }

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => $dashboard,
        ]);
    }

    /**
     * 站点列表（用于前端站点切换器）
     *
     * GET /api/v1/merchant/stores
     */
    public function stores(Request $request): JsonResponse
    {
        /** @var MerchantUser $user */
        $user     = $request->user('merchant');
        $merchant = $user->merchant;

        $stores = $this->getAccessibleStores($user, $merchant);

        $list = $stores->map(fn (Store $store) => [
            'store_id'   => $store->id,
            'store_name' => $store->store_name,
            'store_code' => $store->store_code,
            'domain'     => $store->domain,
            'status'     => $store->status,
        ])->values();

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'list'    => $list,
        ]);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 获取商户用户可访问的店铺列表
     *
     * - owner/admin 可访问该商户下所有店铺
     * - 普通操作员受 allowed_store_ids 约束
     */
    protected function getAccessibleStores(MerchantUser $user, Merchant $merchant): Collection
    {
        $query = Store::where('merchant_id', $merchant->id)
            ->where('status', '>=', 0) // 排除已删除/禁用（可视需求调整）
            ->withoutTrashed();

        // 非 owner/admin 角色需过滤可访问店铺
        if (!in_array($user->role, ['owner', 'admin'], true) && $user->allowed_store_ids !== null) {
            $query->whereIn('id', $user->allowed_store_ids);
        }

        return $query->get();
    }
}
