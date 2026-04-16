<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(private AuthService $authService)
    {
    }

    /**
     * 管理员登录
     *
     * 验证管理员用户名和密码，登录成功后返回 Sanctum Token 及管理员基本信息。
     * 请求体参数：`username`（必填）、`password`（必填，最少 6 位）。
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $result = $this->authService->adminLogin(
            $request->input('username'),
            $request->input('password')
        );

        return $this->success($result, '登录成功');
    }

    /**
     * 管理员登出
     *
     * 处消当前请求使用的 Sanctum Token，完成登出。
     * 需要 Sanctum 认证。
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        return $this->success(null, '已登出');
    }

    /**
     * 获取当前管理员信息
     *
     * 返回当前 Sanctum Token 对应的登录管理员完整信息。
     * 需要 Sanctum 认证。
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }
}
