<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SettlementGenerateRequest;
use App\Http\Requests\Admin\SettlementReviewRequest;
use App\Http\Resources\Admin\SettlementRecordResource;
use App\Jobs\GenerateSettlementJob;
use App\Services\Payment\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 结算管理 Controller（M3-013 / M3-014）
 *
 * 路由前缀：/api/v1/admin/settlements
 * 中间件：auth:sanctum + force.json + central.only
 */
class SettlementController extends BaseAdminController
{
    public function __construct(
        private readonly SettlementService $settlementService,
    ) {}

    /**
     * 结算单列表
     *
     * GET /api/v1/admin/settlements
     *
     * Query params:
     *  - merchant_id  int     按商户筛选
     *  - status       int     状态筛选（0=draft, 1=confirmed, 2=paid, 3=cancelled）
     *  - period_start string  周期开始（Y-m-d）
     *  - period_end   string  周期结束（Y-m-d）
     *  - keyword      string  结算单号 / 商户名模糊搜索
     *  - per_page     int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['merchant_id', 'status', 'period_start', 'period_end', 'keyword']);
        $perPage   = (int) $request->input('per_page', 15);
        $paginator = $this->settlementService->list($filters, $perPage);

        return $this->paginate($paginator);
    }

    /**
     * 结算单详情（含明细）
     *
     * GET /api/v1/admin/settlements/{id}
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->settlementService->getDetail($id);

        return $this->success(new SettlementRecordResource($record));
    }

    /**
     * 手动触发结算单生成（异步）
     *
     * POST /api/v1/admin/settlements/generate
     *
     * Body:
     *  - merchant_id   int|null  指定商户（不传则全部活跃商户）
     *  - period_start  string    结算周期开始（Y-m-d）
     *  - period_end    string    结算周期结束（Y-m-d）
     *
     * 返回 202 Accepted，任务在后台异步执行。
     */
    public function generate(SettlementGenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        GenerateSettlementJob::dispatch(
            merchantId:  $validated['merchant_id'] ?? null,
            periodStart: $validated['period_start'],
            periodEnd:   $validated['period_end'],
        );

        return response()->json([
            'code'    => 0,
            'message' => '结算单生成任务已提交，请稍后查看结果',
            'data'    => [
                'merchant_id'  => $validated['merchant_id'] ?? 'all',
                'period_start' => $validated['period_start'],
                'period_end'   => $validated['period_end'],
            ],
        ], 202);
    }

    /* ================================================================
     |  审核流程接口（M3-014）
     | ================================================================ */

    /**
     * 提交结算单审核
     *
     * POST /api/v1/admin/settlements/{id}/submit-review
     */
    public function submitReview(int $id): JsonResponse
    {
        $record = $this->settlementService->submitForReview($id);

        return $this->success(
            new SettlementRecordResource($record),
            '结算单已提交审核'
        );
    }

    /**
     * 审核通过结算单
     *
     * POST /api/v1/admin/settlements/{id}/approve
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        /** @var int $adminId */
        $adminId = $request->user()->id;

        $record = $this->settlementService->approve($id, $adminId);

        return $this->success(
            new SettlementRecordResource($record),
            '结算单审核通过'
        );
    }

    /**
     * 审核拒绝结算单
     *
     * POST /api/v1/admin/settlements/{id}/reject
     *
     * Body: { "reason": "拒绝原因" }
     */
    public function reject(int $id, SettlementReviewRequest $request): JsonResponse
    {
        /** @var int $adminId */
        $adminId = $request->user()->id;

        $record = $this->settlementService->reject(
            $id,
            $adminId,
            $request->validated('reason'),
        );

        return $this->success(
            new SettlementRecordResource($record),
            '结算单已拒绝'
        );
    }

    /**
     * 标记结算单已打款
     *
     * POST /api/v1/admin/settlements/{id}/mark-paid
     *
     * Body: { "transaction_ref": "TXN-xxx" }（可选）
     */
    public function markPaid(int $id, SettlementReviewRequest $request): JsonResponse
    {
        /** @var int $adminId */
        $adminId = $request->user()->id;

        $record = $this->settlementService->markAsPaid(
            $id,
            $adminId,
            $request->validated('transaction_ref'),
        );

        return $this->success(
            new SettlementRecordResource($record),
            '结算单已标记打款'
        );
    }

    /**
     * 取消结算单
     *
     * POST /api/v1/admin/settlements/{id}/cancel
     *
     * Body: { "reason": "取消原因" }
     */
    public function cancel(int $id, SettlementReviewRequest $request): JsonResponse
    {
        /** @var int $adminId */
        $adminId = $request->user()->id;

        $record = $this->settlementService->cancel(
            $id,
            $adminId,
            $request->validated('reason'),
        );

        return $this->success(
            new SettlementRecordResource($record),
            '结算单已取消'
        );
    }
}
