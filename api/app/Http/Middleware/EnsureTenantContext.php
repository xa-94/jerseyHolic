<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 确保当前请求处于租户上下文中。
 *
 * 用于 tenant 路由组，防止在未初始化租户的情况下访问租户资源。
 *
 * @package App\Http\Middleware
 */
class EnsureTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!tenancy()->initialized) {
            return response()->json([
                'success'    => false,
                'message'    => 'Tenant context is required to access this resource.',
                'error_code' => 'TENANT_CONTEXT_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
