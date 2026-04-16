<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\BlacklistRequest;
use App\Http\Resources\Admin\BlacklistResource;
use App\Services\Payment\BlacklistService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 黑名单管理 Controller（M3-018）
 *
 * 路由前缀：/api/v1/admin/blacklist
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 支持 4 维度：ip / email / device / payment_account
 */
class BlacklistController extends BaseAdminController
{
    public function __construct(
        private readonly BlacklistService $blacklistService,
    ) {}

    /**
     * 黑名单列表
     *
     * GET /api/v1/admin/blacklist
     *
     * Query params:
     *  - dimension    string  维度筛选（ip/email/device/payment_account）
     *  - scope        string  作用范围（platform/merchant）
     *  - merchant_id  int     商户 ID 筛选
     *  - keyword      string  按值/原因模糊搜索
     *  - active_only  bool    仅显示有效条目
     *  - per_page     int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['dimension', 'scope', 'merchant_id', 'keyword', 'active_only', 'per_page']);
        $paginator = $this->blacklistService->list($filters);

        return $this->paginate($paginator);
    }

    /**
     * 添加黑名单条目
     *
     * POST /api/v1/admin/blacklist
     *
     * Body: dimension, value, reason, merchant_id?, expires_at?
     */
    public function store(BlacklistRequest $request): JsonResponse
    {
        $data = $request->validated();

        $expiresAt = !empty($data['expires_at'])
            ? Carbon::parse($data['expires_at'])
            : null;

        $entry = $this->blacklistService->add(
            dimension:  $data['dimension'],
            value:      $data['value'],
            reason:     $data['reason'],
            merchantId: $data['merchant_id'] ?? null,
            expiresAt:  $expiresAt,
        );

        return $this->success(
            new BlacklistResource($entry->load('merchant:id,merchant_name')),
            '黑名单条目已添加'
        );
    }

    /**
     * 更新黑名单条目
     *
     * PUT /api/v1/admin/blacklist/{id}
     *
     * Body: reason?, expires_at?
     */
    public function update(BlacklistRequest $request, int $id): JsonResponse
    {
        $entry = $this->blacklistService->update($id, $request->validated());

        return $this->success(
            new BlacklistResource($entry->load('merchant:id,merchant_name')),
            '黑名单条目已更新'
        );
    }

    /**
     * 删除黑名单条目
     *
     * DELETE /api/v1/admin/blacklist/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->blacklistService->remove($id);

        return $this->success(null, '黑名单条目已删除');
    }
}
