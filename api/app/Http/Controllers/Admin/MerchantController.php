<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MerchantRequest;
use App\Models\Central\Merchant;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 商户管理 Controller（M2-009）
 *
 * 路由前缀：/api/v1/admin/merchants
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 状态字符串映射（整型见 MerchantService::STATUS_MAP）：
 *  pending | active | rejected | info_required | suspended | banned
 */
class MerchantController extends BaseAdminController
{
    public function __construct(
        private readonly MerchantService $merchantService
    ) {}

    /**
     * 商户列表
     *
     * GET /api/v1/admin/merchants
     *
     * Query params:
     *  - keyword   string  按商户名 / 邮箱 / 联系人模糊搜索
     *  - status    string  状态筛选（pending/active/rejected/info_required/suspended/banned）
     *  - level     string  等级筛选（starter/standard/advanced/vip）
     *  - per_page  int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['keyword', 'status', 'level', 'per_page']);
        $paginator = $this->merchantService->listMerchants($filters);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => $paginator->items(),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 商户详情
     *
     * GET /api/v1/admin/merchants/{id}
     */
    public function show(int $id): JsonResponse
    {
        $merchant = $this->merchantService->getMerchant($id);

        return $this->success($merchant);
    }

    /**
     * 创建商户（管理员手动创建）
     *
     * POST /api/v1/admin/merchants
     *
     * Body: merchant_name, email, password, contact_name, phone?, level?
     */
    public function store(MerchantRequest $request): JsonResponse
    {
        $merchant = $this->merchantService->register($request->validated());

        return $this->success($merchant, '商户创建成功');
    }

    /**
     * 更新商户信息
     *
     * PUT /api/v1/admin/merchants/{id}
     *
     * Body: 同创建，所有字段可选
     */
    public function update(MerchantRequest $request, int $id): JsonResponse
    {
        $merchant = $this->merchantService->updateMerchant($id, $request->validated());

        return $this->success($merchant, '商户信息已更新');
    }

    /**
     * 变更商户状态（F-MCH-013）
     *
     * PATCH /api/v1/admin/merchants/{id}/status
     *
     * Body:
     *  - status  string  目标状态（pending/active/rejected/info_required/suspended/banned）
     *  - reason  string? 变更原因（可选）
     */
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,active,rejected,info_required,suspended,banned',
            'reason' => 'nullable|string|max:500',
        ]);

        $merchant = $this->merchantService->changeStatus(
            $id,
            $request->input('status'),
            $request->input('reason')
        );

        return $this->success($merchant, '商户状态已更新');
    }

    /**
     * 调整商户等级（F-MCH-014）
     *
     * PATCH /api/v1/admin/merchants/{id}/level
     *
     * Body:
     *  - level  string  目标等级（starter/standard/advanced/vip）
     */
    public function updateLevel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'level' => 'required|string|in:starter,standard,advanced,vip',
        ]);

        $merchant = $this->merchantService->updateLevel($id, $request->input('level'));

        $storeLimit = $this->merchantService->getLevelStoreLimit($merchant->level);
        $limitLabel = $storeLimit === -1 ? '无限制' : (string)$storeLimit;

        return $this->success(
            array_merge($merchant->toArray(), ['store_limit' => $limitLabel]),
            "商户等级已调整为 {$merchant->level}，站点上限：{$limitLabel}"
        );
    }

    /**
     * 审核商户
     *
     * POST /api/v1/admin/merchants/{id}/review
     *
     * Body:
     *  - action   string  审核操作（approve/reject/request_info）
     *  - comment  string? 审核意见（可选）
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action'  => 'required|string|in:approve,reject,request_info',
            'comment' => 'nullable|string|max:1000',
        ]);

        $merchant = $this->merchantService->review(
            $id,
            $request->input('action'),
            $request->input('comment')
        );

        $actionLabel = [
            'approve'      => '已审核通过',
            'reject'       => '已拒绝',
            'request_info' => '已要求补充信息',
        ][$request->input('action')];

        return $this->success($merchant, "审核操作成功：{$actionLabel}");
    }

    /**
     * 删除商户（软删除）
     *
     * DELETE /api/v1/admin/merchants/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->merchantService->deleteMerchant($id);

        return $this->success(null, '商户已删除');
    }
}
