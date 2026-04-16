<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\RefundImpactResource;
use App\Services\Payment\RefundImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 退款影响管理 Controller（M3-015）
 *
 * 路由前缀：/api/v1/admin/refund-impact
 * 中间件：auth:sanctum + force.json + central.only
 */
class RefundImpactController extends BaseAdminController
{
    public function __construct(
        private readonly RefundImpactService $refundImpactService,
    ) {}

    /**
     * 查看商户退款影响汇总
     *
     * GET /api/v1/admin/refund-impact/merchant/{merchantId}
     *
     * @param  int $merchantId
     * @return JsonResponse
     */
    public function summary(int $merchantId): JsonResponse
    {
        $summary = $this->refundImpactService->getRefundSummary($merchantId);

        return $this->success(new RefundImpactResource((object) array_merge(
            $summary,
            ['merchant_id' => $merchantId],
        )));
    }

    /**
     * 处理退款对结算的影响
     *
     * POST /api/v1/admin/refund-impact/process
     *
     * Body:
     *  - order_id      int     订单 ID（必填）
     *  - refund_amount string  退款金额（必填）
     *  - store_id      int     店铺 ID（必填）
     */
    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'      => 'required|integer',
            'refund_amount' => 'required|numeric|min:0.01',
            'store_id'      => 'required|integer',
        ]);

        $result = $this->refundImpactService->processRefund(
            orderId:      (int) $validated['order_id'],
            refundAmount: (string) $validated['refund_amount'],
            storeId:      (int) $validated['store_id'],
        );

        return $this->success($result, '退款影响处理完成');
    }
}
