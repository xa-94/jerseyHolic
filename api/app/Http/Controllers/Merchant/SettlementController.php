<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Resources\Admin\SettlementRecordResource;
use App\Services\Payment\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商户端 — 结算管理 Controller（M3-013）
 *
 * 路由前缀：/api/v1/merchant/settlements
 * 中间件：auth:sanctum（merchant guard）
 *
 * 商户仅能查看自己的结算单，权限隔离通过 merchant_id 过滤实现。
 */
class SettlementController extends BaseController
{
    public function __construct(
        private readonly SettlementService $settlementService,
    ) {}

    /**
     * 当前商户的结算单列表
     *
     * GET /api/v1/merchant/settlements
     *
     * Query params:
     *  - status       int     状态筛选（0=draft, 1=confirmed, 2=paid, 3=cancelled）
     *  - period_start string  周期开始（Y-m-d）
     *  - period_end   string  周期结束（Y-m-d）
     *  - per_page     int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;
        $filters    = $request->only(['status', 'period_start', 'period_end']);
        $perPage    = (int) $request->input('per_page', 15);

        $paginator = $this->settlementService->listForMerchant($merchantId, $filters, $perPage);

        return $this->paginate($paginator);
    }

    /**
     * 结算单详情（含明细）
     *
     * GET /api/v1/merchant/settlements/{id}
     *
     * 权限校验：仅能查看属于当前商户的结算单。
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;
        $record     = $this->settlementService->getDetail($id);

        // 权限隔离：确保结算单属于当前商户
        if ($record->merchant_id !== $merchantId) {
            return $this->error(40300, '无权查看该结算单');
        }

        return $this->success(new SettlementRecordResource($record));
    }
}
