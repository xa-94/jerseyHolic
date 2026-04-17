<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Merchant\StoreSyncRuleRequest;
use App\Http\Requests\Merchant\UpdateSyncRuleRequest;
use App\Http\Resources\Merchant\SyncRuleCollection;
use App\Http\Resources\Merchant\SyncRuleResource;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Services\Product\SyncRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商户后台 — 同步规则管理控制器
 *
 * 提供同步规则 CRUD API。
 * 所有操作均限定在当前认证商户的独立数据库内。
 *
 * 端点前缀：/api/v1/merchant/sync-rules
 * Guard：merchant（Sanctum）
 */
class SyncRuleController extends BaseController
{
    public function __construct(
        protected readonly SyncRuleService $syncRuleService,
    ) {}

    /* ================================================================
     |  CRUD
     | ================================================================ */

    /**
     * 同步规则列表（分页 + 筛选）
     *
     * GET /api/v1/merchant/sync-rules
     *
     * Query 参数：
     *  - name           : string  规则名称模糊搜索
     *  - price_strategy : string  价格策略（fixed/multiplier/custom）
     *  - auto_sync      : bool    是否自动同步
     *  - status         : int     状态（0=disabled, 1=enabled）
     *  - per_page       : int     每页条数（默认 15，最大 100）
     *  - page           : int     页码
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $paginator = $this->syncRuleService->list($merchant, $request->query());

        return $this->success(new SyncRuleCollection($paginator));
    }

    /**
     * 同步规则详情
     *
     * GET /api/v1/merchant/sync-rules/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $rule = $this->syncRuleService->show($merchant, $id);

        return $this->success(new SyncRuleResource($rule));
    }

    /**
     * 创建同步规则
     *
     * POST /api/v1/merchant/sync-rules
     */
    public function store(StoreSyncRuleRequest $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $rule = $this->syncRuleService->create($merchant, $request->validated());

        return $this->success(new SyncRuleResource($rule), '创建成功');
    }

    /**
     * 更新同步规则
     *
     * PUT /api/v1/merchant/sync-rules/{id}
     */
    public function update(UpdateSyncRuleRequest $request, int $id): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $rule = $this->syncRuleService->update($id, $merchant, $request->validated());

        return $this->success(new SyncRuleResource($rule), '更新成功');
    }

    /**
     * 删除同步规则
     *
     * DELETE /api/v1/merchant/sync-rules/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $this->syncRuleService->delete($id, $merchant);

        return $this->success(null, '删除成功');
    }

    /* ================================================================
     |  辅助
     | ================================================================ */

    /**
     * 从当前认证用户获取所属商户 Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function getMerchant(Request $request): Merchant
    {
        /** @var MerchantUser $user */
        $user = $request->user('merchant');

        return $user->merchant;
    }
}
