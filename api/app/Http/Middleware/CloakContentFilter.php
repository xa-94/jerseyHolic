<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 斗篷内容过滤中间件（M4-011 / F-PROD-040）
 *
 * 根据请求头 / Session / Cookie 中的 cloak_mode 标记，
 * 在 request->attributes 中设置 'cloak_mode' 属性，
 * 后续 Service 层据此决定展示真实内容或安全内容。
 *
 * 模式说明：
 *  - safe：检测到风险流量，展示安全（普货）内容
 *  - real：真实买家流量，展示原始（特货）内容
 *  - null/空：按默认模式处理
 *
 * 配置项（config/product-sync.php → cloak.*）：
 *  - cloak.enabled       总开关（默认 true）
 *  - cloak.default_mode  默认模式（默认 'real'）
 *  - cloak.header_name   自定义请求头名（默认 'X-Cloak-Mode'）
 */
class CloakContentFilter
{
    /** 允许的模式值 */
    private const ALLOWED_MODES = ['safe', 'real'];

    public function handle(Request $request, Closure $next): Response
    {
        // 总开关：未启用则跳过
        if (!$this->isEnabled()) {
            return $next($request);
        }

        $mode = $this->resolveMode($request);
        $request->attributes->set('cloak_mode', $mode);

        $this->logCloakMode($request, $mode);

        return $next($request);
    }

    /* ----------------------------------------------------------------
     |  私有方法
     | ---------------------------------------------------------------- */

    /**
     * 斗篷功能是否启用
     */
    private function isEnabled(): bool
    {
        return (bool) config('product-sync.cloak.enabled', true);
    }

    /**
     * 从请求中解析 cloak 模式
     *
     * 优先级：请求头 > Session > Cookie > 默认配置
     */
    private function resolveMode(Request $request): string
    {
        $headerName = (string) config('product-sync.cloak.header_name', 'X-Cloak-Mode');

        // 1. 请求头
        $mode = $request->header($headerName);
        if ($this->isValidMode($mode)) {
            return $mode;
        }

        // 2. Session
        if ($request->hasSession()) {
            $sessionMode = $request->session()->get('cloak_mode');
            if ($this->isValidMode($sessionMode)) {
                return $sessionMode;
            }
        }

        // 3. Cookie
        $cookieMode = $request->cookie('cloak_mode');
        if ($this->isValidMode($cookieMode)) {
            return $cookieMode;
        }

        // 4. 默认配置
        return (string) config('product-sync.cloak.default_mode', 'real');
    }

    /**
     * 校验模式值是否合法
     */
    private function isValidMode(mixed $mode): bool
    {
        return is_string($mode) && in_array($mode, self::ALLOWED_MODES, true);
    }

    /**
     * 记录斗篷模式日志
     */
    private function logCloakMode(Request $request, string $mode): void
    {
        Log::debug('[CloakContentFilter] Mode resolved.', [
            'cloak_mode' => $mode,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url'        => $request->fullUrl(),
        ]);
    }
}
