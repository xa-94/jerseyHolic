<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Models\Central\MerchantUser;
use App\Services\MerchantUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 商户子账号管理控制器
 *
 * 需要 auth:merchant 中间件保护。
 * 权限控制：owner 管理所有用户；manager 只能管理 operator；operator 无权管理用户。
 *
 * 路由前缀：/api/v1/merchant/users
 */
class UserController extends BaseController
{
    public function __construct(
        private readonly MerchantUserService $userService
    ) {}

    /* ----------------------------------------------------------------
     |  权限常量
     | ---------------------------------------------------------------- */

    /** 可以管理用户的角色 */
    private const MANAGER_ROLES = ['owner', 'manager'];

    /* ----------------------------------------------------------------
     |  CRUD 端点
     | ---------------------------------------------------------------- */

    /**
     * 列出商户下所有用户
     *
     * GET /api/v1/merchant/users
     */
    public function index(Request $request): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足，仅 owner/manager 可查看用户列表', 403);
        }

        $filters = $request->only(['role', 'status', 'per_page']);

        $paginator = $this->userService->listUsers($operator->merchant_id, $filters);

        return $this->success([
            'list'       => $paginator->items(),
            'total'      => $paginator->total(),
            'per_page'   => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'  => $paginator->lastPage(),
        ]);
    }

    /**
     * 创建商户子账号
     *
     * POST /api/v1/merchant/users
     */
    public function store(Request $request): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足，仅 owner/manager 可创建用户', 403);
        }

        $validated = $request->validate([
            'username'          => 'required|string|max:64',
            'email'             => 'required|email|max:255',
            'password'          => 'required|string|min:8|max:128',
            'name'              => 'required|string|max:100',
            'phone'             => 'nullable|string|max:32',
            'role'              => 'nullable|string|in:owner,manager,operator',
            'status'            => 'nullable|integer|in:0,1',
            'allowed_store_ids' => 'nullable|array',
            'allowed_store_ids.*' => 'integer',
        ]);

        // manager 只能创建 operator
        $newRole = $validated['role'] ?? 'operator';
        if ($operator->role === 'manager' && $newRole !== 'operator') {
            return $this->error('manager 只能创建 operator 角色的用户', 403);
        }

        try {
            $user = $this->userService->createUser($operator->merchant, $validated);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success($this->formatUser($user), '用户创建成功', 201);
    }

    /**
     * 获取用户详情
     *
     * GET /api/v1/merchant/users/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足', 403);
        }

        try {
            $user = $this->userService->getUser($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('用户不存在', 404);
        }

        if ($user->merchant_id !== $operator->merchant_id) {
            return $this->error('用户不存在', 404);
        }

        return $this->success($this->formatUser($user));
    }

    /**
     * 更新用户信息
     *
     * PUT /api/v1/merchant/users/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足', 403);
        }

        $validated = $request->validate([
            'username'          => 'sometimes|string|max:64',
            'email'             => 'sometimes|email|max:255',
            'name'              => 'sometimes|string|max:100',
            'phone'             => 'nullable|string|max:32',
            'role'              => 'sometimes|string|in:owner,manager,operator',
            'status'            => 'sometimes|integer|in:0,1',
            'allowed_store_ids' => 'nullable|array',
            'allowed_store_ids.*' => 'integer',
        ]);

        // manager 只能操作 operator，不能修改为非 operator 角色
        if ($operator->role === 'manager') {
            try {
                $target = $this->userService->getUser($id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->error('用户不存在', 404);
            }

            if ($target->merchant_id !== $operator->merchant_id) {
                return $this->error('用户不存在', 404);
            }

            if ($target->role !== 'operator') {
                return $this->error('manager 只能管理 operator 角色的用户', 403);
            }

            if (isset($validated['role']) && $validated['role'] !== 'operator') {
                return $this->error('manager 不能将用户提升为非 operator 角色', 403);
            }
        }

        try {
            $user = $this->userService->updateUser($id, $validated, $operator);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('用户不存在', 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        if ($user->merchant_id !== $operator->merchant_id) {
            return $this->error('用户不存在', 404);
        }

        return $this->success($this->formatUser($user), '用户更新成功');
    }

    /**
     * 删除用户（软删除）
     *
     * DELETE /api/v1/merchant/users/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足', 403);
        }

        // manager 只能删除 operator
        if ($operator->role === 'manager') {
            try {
                $target = $this->userService->getUser($id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->error('用户不存在', 404);
            }

            if ($target->merchant_id !== $operator->merchant_id) {
                return $this->error('用户不存在', 404);
            }

            if ($target->role !== 'operator') {
                return $this->error('manager 只能删除 operator 角色的用户', 403);
            }
        }

        try {
            $this->userService->deleteUser($id, $operator);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('用户不存在', 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success(null, '用户已删除');
    }

    /**
     * 修改用户密码
     *
     * PATCH /api/v1/merchant/users/{id}/password
     */
    public function changePassword(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足', 403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|max:128',
        ]);

        try {
            $user = $this->userService->changePassword($id, $validated['password'], $operator);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('用户不存在', 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        if ($user->merchant_id !== $operator->merchant_id) {
            return $this->error('用户不存在', 404);
        }

        return $this->success(null, '密码已更新');
    }

    /**
     * 更新站点访问权限
     *
     * PATCH /api/v1/merchant/users/{id}/permissions
     */
    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足', 403);
        }

        $validated = $request->validate([
            'allowed_store_ids'   => 'nullable|array',
            'allowed_store_ids.*' => 'integer',
        ]);

        // allowed_store_ids 键必须存在于请求中
        if (! $request->has('allowed_store_ids')) {
            return $this->error('请提供 allowed_store_ids 字段（null 表示全站点访问）', 422);
        }

        try {
            $user = $this->userService->updateStorePermissions(
                $id,
                $validated['allowed_store_ids'] ?? null,
                $operator
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('用户不存在', 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        if ($user->merchant_id !== $operator->merchant_id) {
            return $this->error('用户不存在', 404);
        }

        return $this->success([
            'id'                => $user->id,
            'allowed_store_ids' => $user->allowed_store_ids,
        ], '站点权限已更新');
    }

    /**
     * 解锁用户（重置登录失败次数）
     *
     * POST /api/v1/merchant/users/{id}/unlock
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        /** @var MerchantUser $operator */
        $operator = $request->user();

        if (! $this->canManage($operator)) {
            return $this->error('权限不足', 403);
        }

        try {
            $this->userService->resetLoginFailures($id, $operator);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('用户不存在', 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success(null, '用户已解锁');
    }

    /* ----------------------------------------------------------------
     |  Private helpers
     | ---------------------------------------------------------------- */

    /**
     * 判断操作员是否有用户管理权限（owner 或 manager）
     */
    private function canManage(MerchantUser $operator): bool
    {
        return in_array($operator->role, self::MANAGER_ROLES, true);
    }

    /**
     * 格式化用户数据返回
     *
     * @return array<string, mixed>
     */
    private function formatUser(MerchantUser $user): array
    {
        return [
            'id'                => $user->id,
            'merchant_id'       => $user->merchant_id,
            'username'          => $user->username,
            'email'             => $user->email,
            'name'              => $user->name,
            'phone'             => $user->phone,
            'avatar'            => $user->avatar,
            'role'              => $user->role,
            'status'            => $user->status,
            'allowed_store_ids' => $user->allowed_store_ids,
            'login_failures'    => $user->login_failures,
            'locked_until'      => $user->locked_until?->toIso8601String(),
            'last_login_at'     => $user->last_login_at?->toIso8601String(),
            'created_at'        => $user->created_at?->toIso8601String(),
            'updated_at'        => $user->updated_at?->toIso8601String(),
        ];
    }
}
