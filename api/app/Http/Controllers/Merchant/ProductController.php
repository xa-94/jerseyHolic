<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Merchant\BatchProductDeleteRequest;
use App\Http\Requests\Merchant\BatchProductStatusRequest;
use App\Http\Requests\Merchant\StoreProductRequest;
use App\Http\Requests\Merchant\UpdateProductRequest;
use App\Http\Resources\Merchant\MasterProductCollection;
use App\Http\Resources\Merchant\MasterProductResource;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Services\Product\MasterProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商户后台 — 主商品管理控制器
 *
 * 提供主商品 CRUD、批量操作 API。
 * 所有操作均限定在当前认证商户的独立数据库内。
 *
 * 端点前缀：/api/v1/merchant/products
 * Guard：merchant（Sanctum）
 */
class ProductController extends BaseController
{
    public function __construct(
        protected readonly MasterProductService $productService,
    ) {}

    /* ================================================================
     |  CRUD
     | ================================================================ */

    /**
     * 商品列表（分页 + 筛选）
     *
     * GET /api/v1/merchant/products
     *
     * Query 参数：
     *  - category_l1_id : int    一级品类筛选
     *  - category_l2_id : int    二级品类筛选
     *  - status         : int    状态（0=inactive, 1=active, 2=draft）
     *  - sync_status    : string 同步状态
     *  - keyword        : string SKU / 名称模糊搜索
     *  - per_page       : int    每页条数（默认 20，最大 100）
     *  - page           : int    页码
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $paginator = $this->productService->list($merchant, $request->query());

        return $this->success(new MasterProductCollection($paginator));
    }

    /**
     * 商品详情
     *
     * GET /api/v1/merchant/products/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $product = $this->productService->show($merchant, $id);

        return $this->success(new MasterProductResource($product));
    }

    /**
     * 创建商品
     *
     * POST /api/v1/merchant/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        // SKU 唯一性校验
        $sku = $request->validated('sku');
        if (!$this->productService->validateSku($merchant, $sku)) {
            return $this->error(42201, 'SKU 已存在');
        }

        $product = $this->productService->create($merchant, $request->validated());

        return $this->success(new MasterProductResource($product), '创建成功');
    }

    /**
     * 更新商品
     *
     * PUT /api/v1/merchant/products/{id}
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        // 若更新了 SKU，验证唯一性
        $sku = $request->validated('sku');
        if ($sku !== null && !$this->productService->validateSku($merchant, $sku, $id)) {
            return $this->error(42201, 'SKU 已存在');
        }

        $product = $this->productService->update($merchant, $id, $request->validated());

        return $this->success(new MasterProductResource($product), '更新成功');
    }

    /**
     * 删除商品
     *
     * DELETE /api/v1/merchant/products/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $this->productService->delete($merchant, $id);

        return $this->success(null, '删除成功');
    }

    /* ================================================================
     |  批量操作
     | ================================================================ */

    /**
     * 批量更新商品状态
     *
     * POST /api/v1/merchant/products/batch-status
     */
    public function batchStatus(BatchProductStatusRequest $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $affected = $this->productService->batchUpdateStatus(
            $merchant,
            $request->validated('ids'),
            (int) $request->validated('status'),
        );

        return $this->success(['affected' => $affected], '批量更新成功');
    }

    /**
     * 批量删除商品
     *
     * POST /api/v1/merchant/products/batch-delete
     */
    public function batchDelete(BatchProductDeleteRequest $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $affected = $this->productService->batchDelete(
            $merchant,
            $request->validated('ids'),
        );

        return $this->success(['affected' => $affected], '批量删除成功');
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
