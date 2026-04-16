<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PaymentGroupMappingRequest;
use App\Http\Resources\Admin\PaymentGroupMappingResource;
use App\Services\Payment\PaymentGroupMappingService;
use Illuminate\Http\JsonResponse;

/**
 * 平台管理端 — 商户支付分组映射管理 Controller（M3-004）
 *
 * 路由前缀：/api/v1/admin/merchants/{merchantId}/payment-group-mappings
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 管理商户与支付账号分组之间的映射关系，实现三层映射：
 * Domain → Store → Merchant → PaymentAccountGroup
 */
class PaymentGroupMappingController extends BaseAdminController
{
    public function __construct(
        private readonly PaymentGroupMappingService $mappingService
    ) {}

    /**
     * 获取商户的支付分组映射列表
     *
     * GET /api/v1/admin/merchants/{merchantId}/payment-group-mappings
     */
    public function index(int $merchantId): JsonResponse
    {
        $mappings = $this->mappingService->getMerchantMappings($merchantId);

        return $this->success(
            PaymentGroupMappingResource::collection($mappings)
        );
    }

    /**
     * 为商户创建支付分组映射
     *
     * POST /api/v1/admin/merchants/{merchantId}/payment-group-mappings
     */
    public function store(PaymentGroupMappingRequest $request, int $merchantId): JsonResponse
    {
        $validated = $request->validated();

        $mapping = $this->mappingService->setMapping(
            merchantId:    $merchantId,
            paymentMethod: $validated['pay_method'],
            groupId:       $validated['payment_group_id'],
            priority:      $validated['priority'] ?? 0,
        );

        return $this->success(
            new PaymentGroupMappingResource($mapping),
            '映射创建成功'
        );
    }

    /**
     * 更新映射
     *
     * PUT /api/v1/admin/merchants/{merchantId}/payment-group-mappings/{mappingId}
     */
    public function update(PaymentGroupMappingRequest $request, int $merchantId, int $mappingId): JsonResponse
    {
        $mapping = $this->mappingService->updateMapping($mappingId, $request->validated());

        return $this->success(
            new PaymentGroupMappingResource($mapping),
            '映射已更新'
        );
    }

    /**
     * 删除映射
     *
     * DELETE /api/v1/admin/merchants/{merchantId}/payment-group-mappings/{mappingId}
     */
    public function destroy(int $merchantId, int $mappingId): JsonResponse
    {
        $this->mappingService->deleteMapping($mappingId);

        return $this->success(null, '映射已删除');
    }
}
