<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreRequest;
use App\Models\Central\Merchant;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 站点管理 Controller（M2-003）
 *
 * 路由前缀：/api/v1/admin/stores
 * 中间件：auth:sanctum + force.json + central.only
 */
class StoreController extends BaseAdminController
{
    public function __construct(
        private readonly StoreService $storeService
    ) {}

    /**
     * 站点列表
     *
     * GET /api/v1/admin/stores
     *
     * Query params:
     *  - merchant_id  int     按商户筛选
     *  - status       int     状态筛选（0=inactive, 1=active, 2=maintenance）
     *  - per_page     int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['merchant_id', 'status', 'per_page']);
        $paginator = $this->storeService->listStores($filters);

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
     * 站点详情
     *
     * GET /api/v1/admin/stores/{id}
     */
    public function show(int $id): JsonResponse
    {
        $store = $this->storeService->getStore($id);

        return $this->success($store);
    }

    /**
     * 创建站点
     *
     * POST /api/v1/admin/stores
     *
     * Body: merchant_id, store_name, store_code, domain, [配置字段...]
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $data     = $request->validated();
        $merchant = Merchant::findOrFail($data['merchant_id']);

        $store = $this->storeService->createStore($merchant, $data);

        return $this->success($store, '站点创建成功');
    }

    /**
     * 更新站点信息
     *
     * PUT /api/v1/admin/stores/{id}
     *
     * Body: store_name, [配置字段...]（所有字段可选）
     */
    public function update(StoreRequest $request, int $id): JsonResponse
    {
        $store = $this->storeService->updateStore($id, $request->validated());

        return $this->success($store, '站点信息已更新');
    }

    /**
     * 变更站点状态
     *
     * PATCH /api/v1/admin/stores/{id}/status
     *
     * Body:
     *  - status  string  目标状态（active / maintenance / inactive）
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:active,maintenance,inactive',
        ]);

        $store = $this->storeService->updateStatus($id, $request->input('status'));

        return $this->success($store, '站点状态已更新');
    }

    /**
     * 删除站点（软删除 + 标记待清理）
     *
     * DELETE /api/v1/admin/stores/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->storeService->deleteStore($id);

        return $this->success(null, '站点已删除');
    }

    /* ================================================================
     |  配置管理
     | ================================================================ */

    /**
     * 更新产品分类
     *
     * PATCH /api/v1/admin/stores/{id}/categories
     *
     * Body:
     *  - categories  array  分类列表
     */
    public function updateCategories(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'categories' => 'required|array',
        ]);

        $store = $this->storeService->updateCategories($id, $request->input('categories'));

        return $this->success($store, '产品分类已更新');
    }

    /**
     * 更新目标市场
     *
     * PATCH /api/v1/admin/stores/{id}/markets
     *
     * Body:
     *  - markets  array  市场列表
     */
    public function updateMarkets(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'markets' => 'required|array',
        ]);

        $store = $this->storeService->updateMarkets($id, $request->input('markets'));

        return $this->success($store, '目标市场已更新');
    }

    /**
     * 更新支持语言
     *
     * PATCH /api/v1/admin/stores/{id}/languages
     *
     * Body:
     *  - languages  array  语言列表（如 ["en","zh"]）
     */
    public function updateLanguages(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'languages' => 'required|array',
        ]);

        $store = $this->storeService->updateLanguages($id, $request->input('languages'));

        return $this->success($store, '支持语言已更新');
    }

    /**
     * 更新支持货币
     *
     * PATCH /api/v1/admin/stores/{id}/currencies
     *
     * Body:
     *  - currencies  array  货币列表（如 ["USD","CNY"]）
     */
    public function updateCurrencies(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'currencies' => 'required|array',
        ]);

        $store = $this->storeService->updateCurrencies($id, $request->input('currencies'));

        return $this->success($store, '支持货币已更新');
    }

    /**
     * 更新关联支付账号
     *
     * PATCH /api/v1/admin/stores/{id}/payment-accounts
     *
     * Body:
     *  - account_ids  array  支付账号 ID 列表
     */
    public function updatePaymentAccounts(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'account_ids'   => 'required|array',
            'account_ids.*' => 'integer|exists:payment_accounts,id',
        ]);

        $store = $this->storeService->updatePaymentAccounts($id, $request->input('account_ids'));

        return $this->success($store, '支付账号关联已更新');
    }

    /**
     * 更新物流配置
     *
     * PATCH /api/v1/admin/stores/{id}/logistics
     *
     * Body:
     *  - config  array  物流配置对象
     */
    public function updateLogistics(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'config' => 'required|array',
        ]);

        $store = $this->storeService->updateLogistics($id, $request->input('config'));

        return $this->success($store, '物流配置已更新');
    }

    /* ================================================================
     |  域名管理
     | ================================================================ */

    /**
     * 为站点添加域名
     *
     * POST /api/v1/admin/stores/{id}/domains
     *
     * Body:
     *  - domain  string  域名（如 shop2.jerseyholic.com）
     */
    public function addDomain(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255|unique:domains,domain',
        ]);

        $domain = $this->storeService->addDomain($id, $request->input('domain'));

        return $this->success($domain, '域名已添加');
    }

    /**
     * 移除站点的某个域名
     *
     * DELETE /api/v1/admin/stores/{id}/domains/{domainId}
     */
    public function removeDomain(int $id, int $domainId): JsonResponse
    {
        $this->storeService->removeDomain($id, $domainId);

        return $this->success(null, '域名已移除');
    }
}
