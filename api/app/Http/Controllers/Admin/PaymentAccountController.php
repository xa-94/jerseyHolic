<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PaymentAccountRequest;
use App\Http\Resources\Admin\PaymentAccountResource;
use App\Services\Payment\PaymentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 支付账号管理 Controller（M3-003）
 *
 * 路由前缀：/api/v1/admin/payment-accounts
 * 中间件：auth:sanctum + force.json + central.only
 */
class PaymentAccountController extends BaseAdminController
{
    public function __construct(
        private readonly PaymentAccountService $accountService
    ) {}

    /**
     * 账号列表
     *
     * GET /api/v1/admin/payment-accounts
     *
     * Query params:
     *  - pay_method      string  按支付方式筛选
     *  - status           int     0=禁用, 1=启用
     *  - category_id      int     分组 ID 筛选
     *  - lifecycle_stage  string  生命周期阶段筛选
     *  - per_page         int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['pay_method', 'status', 'category_id', 'lifecycle_stage', 'per_page']);
        $paginator = $this->accountService->list($filters);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => PaymentAccountResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 创建支付账号
     *
     * POST /api/v1/admin/payment-accounts
     */
    public function store(PaymentAccountRequest $request): JsonResponse
    {
        $account = $this->accountService->create($request->validated());

        return $this->success(new PaymentAccountResource($account), '支付账号创建成功');
    }

    /**
     * 账号详情（含统计信息 + 健康度明细）
     *
     * GET /api/v1/admin/payment-accounts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->accountService->find($id);

        return $this->success([
            'account'          => new PaymentAccountResource($detail['account']),
            'health_breakdown' => $detail['health_breakdown'],
        ]);
    }

    /**
     * 更新支付账号
     *
     * PUT /api/v1/admin/payment-accounts/{id}
     */
    public function update(PaymentAccountRequest $request, int $id): JsonResponse
    {
        $account = $this->accountService->update($id, $request->validated());

        return $this->success(new PaymentAccountResource($account), '支付账号已更新');
    }

    /**
     * 切换启用/禁用状态
     *
     * PATCH /api/v1/admin/payment-accounts/{id}/status
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'     => 'sometimes|in:0,1',
            'permission' => 'sometimes|integer|in:1,2,3',
        ]);

        $account = $this->accountService->toggleStatus(
            $id,
            $request->only(['status', 'permission'])
        );

        return $this->success(new PaymentAccountResource($account), '账号状态已更新');
    }

    /**
     * 删除支付账号（软删除）
     *
     * DELETE /api/v1/admin/payment-accounts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->accountService->delete($id);

        return $this->success(null, '支付账号已删除');
    }
}
