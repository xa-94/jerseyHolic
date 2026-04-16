<?php

namespace App\Http\Middleware;

use App\Models\Central\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 商户站点访问权限验证中间件
 *
 * 验证当前 MerchantUser 是否有权访问目标 Store：
 *  1. 确认 store 归属于该商户（store.merchant_id === user.merchant_id）
 *  2. 确认用户的 allowed_store_ids 包含该 store（null 表示可访问所有）
 *
 * store_id 来源优先级：路由参数 → X-Store-Id Header → query/body 参数
 * 若无 store_id（如 Dashboard 聚合接口），直接放行。
 */
class MerchantStoreAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. 验证商户用户认证
        $user = $request->user('merchant');
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // 2. 获取 store_id（路由参数 → Header → query/body）
        $storeId = $request->route('store_id')
            ?? $request->header('X-Store-Id')
            ?? $request->input('store_id');

        // 3. 无 store_id 时直接放行（Dashboard 等聚合接口）
        if (!$storeId) {
            return $next($request);
        }

        $storeId = (int) $storeId;

        // 4. 验证站点存在且归属于该商户
        $store = Store::find($storeId);
        if (!$store || (int) $store->merchant_id !== (int) $user->merchant_id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // 5. 验证用户对该站点的访问权限
        if (!$user->canAccessStore($storeId)) {
            return response()->json(['message' => 'Store access denied'], 403);
        }

        // 6. 将 store 和 merchant 注入 request attributes，供后续控制器使用
        $request->attributes->set('current_store', $store);
        $request->attributes->set('current_merchant', $user->merchant);

        return $next($request);
    }
}
