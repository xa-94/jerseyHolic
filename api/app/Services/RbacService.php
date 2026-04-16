<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RbacService
{
    private const CACHE_PREFIX = 'rbac:';
    private const CACHE_TTL = 3600;

    // === Role Management ===

    public function getRoles(array $filters = []): LengthAwarePaginator
    {
        $query = Role::with('permissions');

        if (!empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['keyword'] . '%')
                  ->orWhere('display_name', 'like', '%' . $filters['keyword'] . '%');
            });
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('sort_order')->paginate($filters['per_page'] ?? 20);
    }

    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 1,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            if (!empty($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role->load('permissions');
        });
    }

    public function updateRole(int $id, array $data): Role
    {
        return DB::transaction(function () use ($id, $data) {
            $role = Role::findOrFail($id);

            if ($role->name === 'super_admin') {
                throw new BusinessException(ErrorCode::FORBIDDEN, '超级管理员角色不可修改');
            }

            $role->update(array_filter([
                'display_name' => $data['display_name'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'sort_order' => $data['sort_order'] ?? null,
            ], fn($v) => $v !== null));

            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            $this->clearRoleCache();
            return $role->load('permissions');
        });
    }

    public function deleteRole(int $id): bool
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'super_admin') {
            throw new BusinessException(ErrorCode::FORBIDDEN, '超级管理员角色不可删除');
        }

        if ($role->admins()->count() > 0) {
            throw new BusinessException(ErrorCode::PARAM_ERROR, '该角色下仍有管理员，无法删除');
        }

        $role->permissions()->detach();
        $this->clearRoleCache();
        return $role->delete();
    }

    // === Permission Management ===

    public function getPermissions(array $filters = []): LengthAwarePaginator
    {
        $query = Permission::query();

        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['keyword'] . '%')
                  ->orWhere('display_name', 'like', '%' . $filters['keyword'] . '%');
            });
        }

        return $query->orderBy('module')->orderBy('sort_order')->paginate($filters['per_page'] ?? 50);
    }

    public function getPermissionTree(): array
    {
        $permissions = Permission::orderBy('module')->orderBy('sort_order')->get();

        $tree = [];
        foreach ($permissions as $perm) {
            $tree[$perm->module][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'display_name' => $perm->display_name,
                'action' => $perm->action,
            ];
        }

        return $tree;
    }

    // === Admin Role Assignment ===

    public function assignRolesToAdmin(int $adminId, array $roleIds): void
    {
        $admin = Admin::findOrFail($adminId);
        $admin->roles()->sync($roleIds);
        $this->clearAdminPermissionCache($adminId);
    }

    public function getAdminPermissions(int $adminId): array
    {
        $cacheKey = self::CACHE_PREFIX . 'admin_perms:' . $adminId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($adminId) {
            $admin = Admin::with('roles.permissions')->findOrFail($adminId);

            if ($admin->roles->contains('name', 'super_admin')) {
                return ['*'];
            }

            return $admin->roles
                ->flatMap(fn($role) => $role->permissions)
                ->pluck('name')
                ->unique()
                ->values()
                ->toArray();
        });
    }

    public function adminHasPermission(int $adminId, string $permission): bool
    {
        $permissions = $this->getAdminPermissions($adminId);

        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    public function adminHasRole(int $adminId, string $roleName): bool
    {
        $admin = Admin::with('roles')->findOrFail($adminId);
        return $admin->roles->contains('name', $roleName);
    }

    // === Cache ===

    public function clearAdminPermissionCache(int $adminId): void
    {
        Cache::forget(self::CACHE_PREFIX . 'admin_perms:' . $adminId);
    }

    public function clearRoleCache(): void
    {
        // Clear all admin permission caches when roles change
        $adminIds = Admin::pluck('id');
        foreach ($adminIds as $id) {
            $this->clearAdminPermissionCache($id);
        }
    }
}
