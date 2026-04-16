<?php

namespace App\Http\Controllers\Admin;

use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RbacController extends BaseAdminController
{
    public function __construct(
        private readonly RbacService $rbacService
    ) {}

    // === Roles ===

    public function roleIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['keyword', 'status', 'per_page']);
        $roles = $this->rbacService->getRoles($filters);

        return $this->paginate($roles);
    }

    public function roleStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:50|unique:jh_roles,name',
            'display_name'     => 'nullable|string|max:100',
            'description'      => 'nullable|string|max:255',
            'status'           => 'nullable|boolean',
            'sort_order'       => 'nullable|integer',
            'permission_ids'   => 'nullable|array',
            'permission_ids.*' => 'integer|exists:jh_permissions,id',
        ]);

        $role = $this->rbacService->createRole($data);

        return $this->success($role, '角色创建成功');
    }

    public function roleUpdate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'display_name'     => 'nullable|string|max:100',
            'description'      => 'nullable|string|max:255',
            'status'           => 'nullable|boolean',
            'sort_order'       => 'nullable|integer',
            'permission_ids'   => 'nullable|array',
            'permission_ids.*' => 'integer|exists:jh_permissions,id',
        ]);

        $role = $this->rbacService->updateRole($id, $data);

        return $this->success($role, '角色更新成功');
    }

    public function roleDestroy(int $id): JsonResponse
    {
        $this->rbacService->deleteRole($id);

        return $this->success(null, '角色删除成功');
    }

    // === Permissions ===

    public function permissionIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['module', 'keyword', 'per_page']);
        $permissions = $this->rbacService->getPermissions($filters);

        return $this->paginate($permissions);
    }

    public function permissionTree(): JsonResponse
    {
        $tree = $this->rbacService->getPermissionTree();

        return $this->success($tree);
    }

    // === Admin Role Assignment ===

    public function assignRoles(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'role_ids'   => 'required|array',
            'role_ids.*' => 'integer|exists:jh_roles,id',
        ]);

        $this->rbacService->assignRolesToAdmin($id, $data['role_ids']);

        return $this->success(null, '角色分配成功');
    }

    public function adminPermissions(int $id): JsonResponse
    {
        $permissions = $this->rbacService->getAdminPermissions($id);

        return $this->success($permissions);
    }
}
