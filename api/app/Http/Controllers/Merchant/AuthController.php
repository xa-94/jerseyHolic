<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Merchant\LoginRequest;
use App\Models\Central\MerchantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    private const MAX_FAILURES  = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * 商户用户登录
     *
     * 支持 email 或 username 登录，登录失败 5 次锁定账户 15 分钟。
     *
     * POST /api/v1/merchant/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login    = $request->input('login');
        $password = $request->input('password');
        $remember = (bool) $request->input('remember', false);

        // 通过 email 或 username 查找用户（central 连接）
        $user = MerchantUser::on('central')
            ->where(function ($q) use ($login) {
                $q->where('email', $login)
                  ->orWhere('username', $login);
            })
            ->first();

        // 账户不存在
        if (! $user) {
            return $this->error('账号或密码错误', 401);
        }

        // 检查是否被锁定
        if ($user->isLocked()) {
            $minutes = (int) now()->diffInMinutes($user->locked_until, false);
            return $this->error("账号已被锁定，请 {$minutes} 分钟后再试", 423);
        }

        // 验证密码
        if (! Hash::check($password, $user->password)) {
            $this->handleLoginFailure($user);
            return $this->error('账号或密码错误', 401);
        }

        // 检查账户状态
        if (! $user->isActive()) {
            return $this->error('账号已被禁用，请联系平台管理员', 403);
        }

        // 登录成功：重置失败计数，更新登录信息
        $user->update([
            'login_failures' => 0,
            'locked_until'   => null,
            'last_login_at'  => now(),
            'last_login_ip'  => $request->ip(),
        ]);

        // 构建 Token abilities
        $abilities = $this->buildAbilities($user);

        // 生成 Sanctum Token
        $tokenName    = 'merchant-token';
        $tokenResult  = $user->createToken($tokenName, $abilities);
        $plainToken   = $tokenResult->plainTextToken;

        // 加载商户信息
        $user->load('merchant');

        return $this->success([
            'token'      => $plainToken,
            'token_type' => 'Bearer',
            'user'       => [
                'id'                => $user->id,
                'merchant_id'       => $user->merchant_id,
                'username'          => $user->username,
                'email'             => $user->email,
                'name'              => $user->name,
                'role'              => $user->role,
                'allowed_store_ids' => $user->allowed_store_ids,
                'last_login_at'     => $user->last_login_at?->toIso8601String(),
            ],
            'merchant'   => $user->merchant ? [
                'id'            => $user->merchant->id,
                'merchant_name' => $user->merchant->merchant_name,
                'level'         => $user->merchant->level,
                'status'        => $user->merchant->status,
            ] : null,
        ], '登录成功');
    }

    /**
     * 商户用户登出
     *
     * 撤销当前 Sanctum Token。
     * POST /api/v1/merchant/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, '已登出');
    }

    /**
     * 获取当前商户用户信息
     *
     * 返回用户信息 + 所属商户信息 + 可访问的站点列表。
     * GET /api/v1/merchant/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var MerchantUser $user */
        $user = $request->user();
        $user->load('merchant.stores');

        // 仅返回该用户有权访问的店铺
        $stores = $user->merchant?->stores
            ->filter(fn ($store) => $user->canAccessStore($store->id))
            ->values()
            ->map(fn ($store) => [
                'id'         => $store->id,
                'store_name' => $store->store_name ?? $store->name ?? null,
                'domain'     => $store->domain ?? null,
                'status'     => $store->status,
            ]);

        return $this->success([
            'user' => [
                'id'                => $user->id,
                'merchant_id'       => $user->merchant_id,
                'username'          => $user->username,
                'email'             => $user->email,
                'name'              => $user->name,
                'phone'             => $user->phone,
                'avatar'            => $user->avatar,
                'role'              => $user->role,
                'allowed_store_ids' => $user->allowed_store_ids,
                'last_login_at'     => $user->last_login_at?->toIso8601String(),
            ],
            'merchant' => $user->merchant ? [
                'id'            => $user->merchant->id,
                'merchant_name' => $user->merchant->merchant_name,
                'level'         => $user->merchant->level,
                'status'        => $user->merchant->status,
            ] : null,
            'stores' => $stores ?? [],
        ]);
    }

    /**
     * 刷新 Token
     *
     * 撤销旧 Token，生成新 Token。
     * POST /api/v1/merchant/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var MerchantUser $user */
        $user = $request->user();

        // 撤销旧 Token
        $user->currentAccessToken()->delete();

        // 构建新 Token abilities
        $abilities   = $this->buildAbilities($user);
        $tokenResult = $user->createToken('merchant-token', $abilities);

        return $this->success([
            'token'      => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Token 已刷新');
    }

    /* ----------------------------------------------------------------
     |  Private helpers
     | ---------------------------------------------------------------- */

    /**
     * 登录失败处理：累计失败次数，达到阈值则锁定账户。
     */
    private function handleLoginFailure(MerchantUser $user): void
    {
        $failures = $user->login_failures + 1;

        $update = ['login_failures' => $failures];

        if ($failures >= self::MAX_FAILURES) {
            $update['locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $user->update($update);
    }

    /**
     * 构建 Token abilities，包含角色和可访问的店铺 ID。
     *
     * @return string[]
     */
    private function buildAbilities(MerchantUser $user): array
    {
        $abilities = ["role:{$user->role}"];

        if ($user->allowed_store_ids !== null) {
            foreach ($user->allowed_store_ids as $storeId) {
                $abilities[] = "store:{$storeId}";
            }
        }

        return $abilities;
    }
}
