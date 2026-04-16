<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 防止从租户域名访问 Central（平台管理）路由。
 *
 * 仅允许 Central 域名（如 admin.jerseyholic.com、localhost）访问平台管理 API。
 * 租户域名（如 store1.jerseyholic.com）访问 Central 路由时返回 404。
 *
 * @package App\Http\Middleware
 */
class PreventAccessFromTenantDomains
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
        $host = $this->extractHost($request);

        if (!$this->isCentralDomain($host)) {
            return response()->json([
                'success'    => false,
                'message'    => 'This endpoint is not available on this domain.',
                'error_code' => 'CENTRAL_ONLY',
            ], 404);
        }

        return $next($request);
    }

    /**
     * 从 Request 中提取干净的 Host。
     *
     * @param  Request $request
     * @return string
     */
    protected function extractHost(Request $request): string
    {
        $host = $request->getHost();

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return strtolower($host);
    }

    /**
     * 判断是否为 Central 域名。
     *
     * @param  string $host
     * @return bool
     */
    protected function isCentralDomain(string $host): bool
    {
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $central) {
            if (strtolower($central) === $host) {
                return true;
            }
        }

        return false;
    }
}
