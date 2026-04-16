<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function __construct(private RbacService $rbacService)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED, '请先登录');
        }

        foreach ($roles as $role) {
            if ($this->rbacService->adminHasRole($user->id, $role)) {
                return $next($request);
            }
        }

        throw new BusinessException(ErrorCode::FORBIDDEN, '角色权限不足');
    }
}
