<?php

namespace App\Services;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 商户子账号管理服务
 *
 * 提供商户用户的 CRUD、角色分配、站点权限管理等功能。
 * 所有操作均在 central 数据库连接下执行。
 */
class MerchantUserService
{
    /** 合法角色枚举 */
    private const VALID_ROLES = ['owner', 'manager', 'operator'];

    /* ----------------------------------------------------------------
     |  查询
     | ---------------------------------------------------------------- */

    /**
     * 按商户列出用户，支持 role/status 筛选
     *
     * @param  array{role?: string, status?: int, per_page?: int} $filters
     */
    public function listUsers(int $merchantId, array $filters = []): LengthAwarePaginator
    {
        $query = MerchantUser::on('central')
            ->where('merchant_id', $merchantId);

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * 获取单个用户（确认属于该商户）
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getUser(int $id): MerchantUser
    {
        return MerchantUser::on('central')->findOrFail($id);
    }

    /* ----------------------------------------------------------------
     |  创建 / 更新 / 删除
     | ---------------------------------------------------------------- */

    /**
     * 创建商户子账号
     *
     * @param  array{username: string, email: string, password: string, name: string,
     *               phone?: string, role?: string, status?: int,
     *               allowed_store_ids?: int[]|null} $data
     * @throws ValidationException
     */
    public function createUser(Merchant $merchant, array $data): MerchantUser
    {
        $role = $data['role'] ?? 'operator';
        $this->assertValidRole($role);

        // 验证 email/username 在同一商户下唯一
        $this->assertEmailUnique($merchant->id, $data['email']);
        $this->assertUsernameUnique($merchant->id, $data['username']);

        // 验证 allowed_store_ids 属于该商户
        if (isset($data['allowed_store_ids']) && is_array($data['allowed_store_ids'])) {
            $this->assertStoresBelongToMerchant($merchant->id, $data['allowed_store_ids']);
        }

        return MerchantUser::on('central')->create([
            'merchant_id'       => $merchant->id,
            'username'          => $data['username'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'name'              => $data['name'],
            'phone'             => $data['phone'] ?? null,
            'role'              => $role,
            'status'            => $data['status'] ?? 1,
            'allowed_store_ids' => $data['allowed_store_ids'] ?? null,
            'login_failures'    => 0,
        ]);
    }

    /**
     * 更新用户信息
     *
     * @param  array{username?: string, email?: string, name?: string, phone?: string,
     *               role?: string, status?: int, allowed_store_ids?: int[]|null} $data
     * @param  MerchantUser $operator 执行操作的当前用户
     * @throws ValidationException
     */
    public function updateUser(int $id, array $data, MerchantUser $operator): MerchantUser
    {
        $user = $this->getUser($id);
        $this->assertSameMerchant($user, $operator);

        // owner 角色不可被降级（除非由另一个 owner 操作）
        if ($user->role === 'owner' && isset($data['role']) && $data['role'] !== 'owner') {
            if ($operator->role !== 'owner') {
                throw ValidationException::withMessages([
                    'role' => ['owner 角色不可被降级，仅允许其他 owner 执行此操作。'],
                ]);
            }
        }

        if (isset($data['role'])) {
            $this->assertValidRole($data['role']);
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $this->assertEmailUnique($user->merchant_id, $data['email'], $user->id);
        }

        if (isset($data['username']) && $data['username'] !== $user->username) {
            $this->assertUsernameUnique($user->merchant_id, $data['username'], $user->id);
        }

        if (array_key_exists('allowed_store_ids', $data) && is_array($data['allowed_store_ids'])) {
            $this->assertStoresBelongToMerchant($user->merchant_id, $data['allowed_store_ids']);
        }

        $fillable = array_filter(
            array_intersect_key($data, array_flip([
                'username', 'email', 'name', 'phone', 'role', 'status', 'allowed_store_ids',
            ])),
            fn ($v) => $v !== null || array_key_exists('allowed_store_ids', $data)
        );

        // allowed_store_ids 允许显式设置为 null（全站点访问）
        if (array_key_exists('allowed_store_ids', $data)) {
            $fillable['allowed_store_ids'] = $data['allowed_store_ids'];
        }

        $user->update($fillable);

        return $user->fresh();
    }

    /**
     * 删除（软删除）商户用户
     *
     * owner 用户不可被删除。
     *
     * @throws ValidationException
     */
    public function deleteUser(int $id, MerchantUser $operator): bool
    {
        $user = $this->getUser($id);
        $this->assertSameMerchant($user, $operator);

        if ($user->role === 'owner') {
            throw ValidationException::withMessages([
                'id' => ['owner 账号不可被删除。'],
            ]);
        }

        return (bool) $user->delete();
    }

    /* ----------------------------------------------------------------
     |  密码 & 权限
     | ---------------------------------------------------------------- */

    /**
     * 修改用户密码
     */
    public function changePassword(int $id, string $newPassword, MerchantUser $operator): MerchantUser
    {
        $user = $this->getUser($id);
        $this->assertSameMerchant($user, $operator);

        $user->update(['password' => Hash::make($newPassword)]);

        return $user->fresh();
    }

    /**
     * 更新站点访问权限
     *
     * @param  int[]|null $storeIds  null = 全站点，[] = 无访问权
     * @throws ValidationException
     */
    public function updateStorePermissions(int $id, ?array $storeIds, MerchantUser $operator): MerchantUser
    {
        $user = $this->getUser($id);
        $this->assertSameMerchant($user, $operator);

        // 非 null 时验证 storeIds 属于该商户
        if ($storeIds !== null && count($storeIds) > 0) {
            $this->assertStoresBelongToMerchant($user->merchant_id, $storeIds);
        }

        $user->update(['allowed_store_ids' => $storeIds]);

        return $user->fresh();
    }

    /**
     * 重置登录失败次数（管理员手动解锁）
     */
    public function resetLoginFailures(int $id, MerchantUser $operator): MerchantUser
    {
        $user = $this->getUser($id);
        $this->assertSameMerchant($user, $operator);

        $user->update([
            'login_failures' => 0,
            'locked_until'   => null,
        ]);

        return $user->fresh();
    }

    /* ----------------------------------------------------------------
     |  Private helpers
     | ---------------------------------------------------------------- */

    /**
     * 验证角色合法性
     *
     * @throws ValidationException
     */
    private function assertValidRole(string $role): void
    {
        if (! in_array($role, self::VALID_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['角色必须为 owner、manager 或 operator 之一。'],
            ]);
        }
    }

    /**
     * 验证 email 在同一商户下唯一
     *
     * @throws ValidationException
     */
    private function assertEmailUnique(int $merchantId, string $email, ?int $excludeId = null): void
    {
        $query = MerchantUser::on('central')
            ->where('merchant_id', $merchantId)
            ->where('email', $email);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'email' => ['该邮箱在当前商户下已被使用。'],
            ]);
        }
    }

    /**
     * 验证 username 在同一商户下唯一
     *
     * @throws ValidationException
     */
    private function assertUsernameUnique(int $merchantId, string $username, ?int $excludeId = null): void
    {
        $query = MerchantUser::on('central')
            ->where('merchant_id', $merchantId)
            ->where('username', $username);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'username' => ['该用户名在当前商户下已被使用。'],
            ]);
        }
    }

    /**
     * 验证 storeIds 全部属于指定商户
     *
     * @param  int[] $storeIds
     * @throws ValidationException
     */
    private function assertStoresBelongToMerchant(int $merchantId, array $storeIds): void
    {
        if (empty($storeIds)) {
            return;
        }

        $validCount = Store::on('central')
            ->where('merchant_id', $merchantId)
            ->whereIn('id', $storeIds)
            ->count();

        if ($validCount !== count($storeIds)) {
            throw ValidationException::withMessages([
                'allowed_store_ids' => ['部分站点不属于该商户，请检查站点 ID。'],
            ]);
        }
    }

    /**
     * 确认被操作用户与当前操作员属于同一商户
     *
     * @throws ValidationException
     */
    private function assertSameMerchant(MerchantUser $target, MerchantUser $operator): void
    {
        if ($target->merchant_id !== $operator->merchant_id) {
            throw ValidationException::withMessages([
                'id' => ['无权操作其他商户的用户。'],
            ]);
        }
    }
}
