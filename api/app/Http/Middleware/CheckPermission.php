<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private RbacService $rbacService)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED, '请先登录');
        }

        if (!$this->rbacService->adminHasPermission($user->id, $permission)) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '没有操作权限');
        }

        return $next($request);
    }
}
