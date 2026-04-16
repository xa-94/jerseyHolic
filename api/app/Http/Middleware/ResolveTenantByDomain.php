<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * 租户识别中间件 — 基于请求域名识别租户（Store）。
 *
 * 流程：
 *  1. 从 HTTP Request 中提取 Host
 *  2. 去掉 port 和 www 前缀
 *  3. 判断是否为 Central 域名 → 是则跳过
 *  4. 查询 Central DB 的 jh_domains 表
 *  5. 根据 Store 状态返回对应响应
 *  6. 使用 stancl/tenancy 初始化租户上下文
 *  7. 将 store / merchant 信息注入 request 属性
 *
 * @package App\Http\Middleware
 */
class ResolveTenantByDomain
{
    /** @var Tenancy */
    protected Tenancy $tenancy;

    /** @var DomainTenantResolver */
    protected DomainTenantResolver $resolver;

    /**
     * @param Tenancy              $tenancy
     * @param DomainTenantResolver $resolver
     */
    public function __construct(Tenancy $tenancy, DomainTenantResolver $resolver)
    {
        $this->tenancy  = $tenancy;
        $this->resolver = $resolver;
    }

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

        // ── 1. Central 域名 → 跳过租户识别 ──
        if ($this->isCentralDomain($host)) {
            return $next($request);
        }

        // ── 2. 查询 Central DB 的 domains 表 ──
        $domainRecord = $this->findDomainRecord($host);

        if (!$domainRecord) {
            return $this->jsonError('Store not found.', 'STORE_NOT_FOUND', 404);
        }

        // ── 3. 获取关联的 Store（Tenant） ──
        $store = $this->findStore($domainRecord->tenant_id);

        if (!$store) {
            Log::warning('[Tenant] Domain exists but store missing', [
                'domain'    => $host,
                'tenant_id' => $domainRecord->tenant_id,
            ]);
            return $this->jsonError('Store not found.', 'STORE_NOT_FOUND', 404);
        }

        // ── 4. 检查 Store 状态 ──
        $statusResponse = $this->checkStoreStatus($store);
        if ($statusResponse !== null) {
            return $statusResponse;
        }

        // ── 5. 使用 stancl/tenancy 初始化租户上下文 ──
        try {
            $this->tenancy->initialize($store);
        } catch (\Throwable $e) {
            Log::error('[Tenant] Failed to initialize tenancy', [
                'store_id' => $store->getKey(),
                'error'    => $e->getMessage(),
            ]);
            return $this->jsonError(
                'Unable to load store. Please try again later.',
                'TENANCY_INIT_FAILED',
                500
            );
        }

        // ── 6. 注入 store 和 merchant 信息到 request ──
        $request->attributes->set('store', $store);
        $request->attributes->set('tenant_id', $store->getKey());

        // 如果 Store 模型有 merchant 关联，注入 merchant 信息
        if (method_exists($store, 'merchant')) {
            try {
                $merchant = $store->merchant;
                $request->attributes->set('merchant', $merchant);
                $request->attributes->set('merchant_id', $merchant?->getKey());
            } catch (\Throwable $e) {
                // merchant 信息为可选，不阻塞请求
                Log::warning('[Tenant] Failed to load merchant for store', [
                    'store_id' => $store->getKey(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $next($request);
    }

    /**
     * 从 Request 中提取干净的 Host（去 port、去 www）。
     *
     * @param  Request $request
     * @return string
     */
    protected function extractHost(Request $request): string
    {
        $host = $request->getHost(); // 已去除 port

        // 去除 www 前缀
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

    /**
     * 从 Central DB 查询域名记录。
     *
     * @param  string $host
     * @return object|null
     */
    protected function findDomainRecord(string $host): ?object
    {
        $centralConnection = config('tenancy.database.central_connection', 'central');

        return DB::connection($centralConnection)
            ->table('domains')
            ->where('domain', $host)
            ->first();
    }

    /**
     * 根据 tenant_id 获取 Store 模型实例。
     *
     * @param  mixed $tenantId
     * @return \Stancl\Tenancy\Contracts\Tenant|null
     */
    protected function findStore(mixed $tenantId)
    {
        $tenantModel = config('tenancy.tenant_model');

        return $tenantModel::find($tenantId);
    }

    /**
     * 检查 Store 状态，返回错误响应或 null（表示正常）。
     *
     * @param  mixed $store
     * @return Response|null
     */
    protected function checkStoreStatus(mixed $store): ?Response
    {
        // Store 模型需要有 status 属性
        $status = $store->status ?? 'active';

        return match ($status) {
            'active'      => null, // 正常通过
            'maintenance' => $this->jsonError(
                'This store is currently under maintenance. Please try again later.',
                'STORE_MAINTENANCE',
                503
            ),
            'suspended'   => $this->jsonError(
                'This store has been suspended.',
                'STORE_SUSPENDED',
                403
            ),
            default       => $this->jsonError(
                'Store not found.',
                'STORE_NOT_FOUND',
                404
            ),
        };
    }

    /**
     * 返回统一格式的 JSON 错误响应。
     *
     * @param  string $message
     * @param  string $errorCode
     * @param  int    $httpStatus
     * @return Response
     */
    protected function jsonError(string $message, string $errorCode, int $httpStatus): Response
    {
        return response()->json([
            'success'    => false,
            'message'    => $message,
            'error_code' => $errorCode,
        ], $httpStatus);
    }
}
