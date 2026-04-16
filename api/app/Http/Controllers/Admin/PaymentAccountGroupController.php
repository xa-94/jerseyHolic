<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PaymentAccountGroupRequest;
use App\Http\Resources\Admin\PaymentAccountGroupResource;
use App\Services\Payment\PaymentAccountGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 支付账号分组管理 Controller（M3-003）
 *
 * 路由前缀：/api/v1/admin/payment-account-groups
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 分组策略类型：VIP_EXCLUSIVE / STANDARD_SHARED / LITE_SHARED / BLACKLIST_ISOLATED
 */
class PaymentAccountGroupController extends BaseAdminController
{
    public function __construct(
        private readonly PaymentAccountGroupService $groupService
    ) {}

    /**
     * 分组列表
     *
     * GET /api/v1/admin/payment-account-groups
     *
     * Query params:
     *  - type        string  按支付方式筛选（paypal/credit_card/stripe/antom）
     *  - group_type  string  按分组策略筛选
     *  - status      int     状态筛选（0=禁用, 1=启用）
     *  - per_page    int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['type', 'group_type', 'status', 'per_page']);
        $paginator = $this->groupService->list($filters);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => PaymentAccountGroupResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 创建分组
     *
     * POST /api/v1/admin/payment-account-groups
     */
    public function store(PaymentAccountGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->create($request->validated());

        return $this->success(new PaymentAccountGroupResource($group), '分组创建成功');
    }

    /**
     * 分组详情（含关联账号）
     *
     * GET /api/v1/admin/payment-account-groups/{id}
     */
    public function show(int $id): JsonResponse
    {
        $group = $this->groupService->find($id);

        return $this->success(new PaymentAccountGroupResource($group));
    }

    /**
     * 更新分组
     *
     * PUT /api/v1/admin/payment-account-groups/{id}
     */
    public function update(PaymentAccountGroupRequest $request, int $id): JsonResponse
    {
        $group = $this->groupService->update($id, $request->validated());

        return $this->success(new PaymentAccountGroupResource($group), '分组信息已更新');
    }

    /**
     * 删除分组（无关联账号才可删）
     *
     * DELETE /api/v1/admin/payment-account-groups/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->groupService->delete($id);

        return $this->success(null, '分组已删除');
    }
}
