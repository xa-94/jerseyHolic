<?php

namespace App\Http\Controllers\Api;

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
     * 买家登录
     *
     * 验证买家邮筱和密码，登录成功后返回 Sanctum Token 及用户基本信息。
     * 请求体参数：`email`（必填）、`password`（必填，最少 6 位）。
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $result = $this->authService->buyerLogin(
            $request->input('email'),
            $request->input('password')
        );

        return $this->success($result, '登录成功');
    }

    /**
     * 买家注册
     *
     * 创建新买家账号，注册成功后自动登录并返回 Token 及用户信息。
     * 邮筱地址必须全局唯一，重复邮筱会返回 422 验证错误。
     * 请求体参数：`first_name`（必填）、`last_name`（可选）、`email`（必填）、`password`（必填，需 confirmed）。
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email' => 'required|email|unique:jh_customers,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $result = $this->authService->buyerRegister($request->only([
            'first_name', 'last_name', 'email', 'password',
        ]));

        return $this->success($result, '注册成功');
    }

    /**
     * 买家登出
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
     * 获取当前登录用户信息
     *
     * 返回当前 Sanctum Token 对应的登录买家的完整信息。
     * 需要 Sanctum 认证。
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }

    /**
     * 忽记密码
     *
     * 接收买家邮筱地址，发送密码重置邮件。
     * 公开接口，无需登录。尚未实现，当前返回占位响应。
     * 请求体参数：`email`（必填）。
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        // TODO: Implement password reset email
        return $this->success(null, '密码重置邮件已发送');
    }

    /**
     * 重置密码
     *
     * 使用有效的重置令牌和邮筱地址，将账户密码重置为新密码。
     * 公开接口，无需登录。尚未实现，当前返回占位响应。
     * 请求体参数：`token`（必填）、`email`（必填）、`password`（必填，需 confirmed）。
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);
        // TODO: Implement password reset
        return $this->success(null, '密码已重置');
    }
}
