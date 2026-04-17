<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ProductCategoryL1Request;
use App\Http\Requests\Admin\ProductCategoryL2Request;
use App\Http\Resources\Admin\ProductCategoryL1Resource;
use App\Http\Resources\Admin\ProductCategoryL2Resource;
use App\Services\ProductCategoryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 — 品类体系管理 Controller（M4-001）
 *
 * 路由前缀：/api/v1/admin/categories
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 提供 L1 一级品类和 L2 二级品类的 CRUD 及品类树接口。
 */
class ProductCategoryController extends BaseAdminController
{
    public function __construct(
        private readonly ProductCategoryManagementService $categoryService
    ) {}

    /* ================================================================
     |  L1 一级品类
     | ================================================================ */

    /**
     * L1 列表（含子品类计数）
     *
     * GET /api/v1/admin/categories/l1
     */
    public function l1Index(Request $request): JsonResponse
    {
        $params    = $request->only(['keyword', 'status', 'per_page']);
        $paginator = $this->categoryService->getL1List($params);

        return $this->paginate($paginator->through(
            fn ($item) => new ProductCategoryL1Resource($item)
        ));
    }

    /**
     * 创建 L1 品类
     *
     * POST /api/v1/admin/categories/l1
     */
    public function l1Store(ProductCategoryL1Request $request): JsonResponse
    {
        $category = $this->categoryService->createL1($request->validated());

        return $this->success(
            new ProductCategoryL1Resource($category),
            '一级品类创建成功'
        );
    }

    /**
     * L1 品类详情
     *
     * GET /api/v1/admin/categories/l1/{id}
     */
    public function showL1(int $id): JsonResponse
    {
        $category = $this->categoryService->getL1Detail($id);

        return $this->success(
            new ProductCategoryL1Resource($category),
            '获取成功'
        );
    }

    /**
     * 更新 L1 品类
     *
     * PUT /api/v1/admin/categories/l1/{id}
     */
    public function l1Update(ProductCategoryL1Request $request, int $id): JsonResponse
    {
        $category = $this->categoryService->updateL1($id, $request->validated());

        return $this->success(
            new ProductCategoryL1Resource($category),
            '一级品类更新成功'
        );
    }

    /**
     * 删除 L1 品类（仅无子品类时允许）
     *
     * DELETE /api/v1/admin/categories/l1/{id}
     */
    public function l1Destroy(int $id): JsonResponse
    {
        try {
            $this->categoryService->deleteL1($id);
            return $this->success(null, '一级品类删除成功');
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }

    /* ================================================================
     |  L2 二级品类
     | ================================================================ */

    /**
     * L2 列表（可按 l1_id 筛选）
     *
     * GET /api/v1/admin/categories/l2
     */
    public function l2Index(Request $request): JsonResponse
    {
        $params    = $request->only(['l1_id', 'keyword', 'status', 'per_page']);
        $paginator = $this->categoryService->getL2List($params);

        return $this->paginate($paginator->through(
            fn ($item) => new ProductCategoryL2Resource($item)
        ));
    }

    /**
     * 创建 L2 品类
     *
     * POST /api/v1/admin/categories/l2
     */
    public function l2Store(ProductCategoryL2Request $request): JsonResponse
    {
        $category = $this->categoryService->createL2($request->validated());

        return $this->success(
            new ProductCategoryL2Resource($category->load('parent')),
            '二级品类创建成功'
        );
    }

    /**
     * L2 品类详情
     *
     * GET /api/v1/admin/categories/l2/{id}
     */
    public function showL2(int $id): JsonResponse
    {
        $category = $this->categoryService->getL2Detail($id);

        return $this->success(
            new ProductCategoryL2Resource($category),
            '获取成功'
        );
    }

    /**
     * 更新 L2 品类
     *
     * PUT /api/v1/admin/categories/l2/{id}
     */
    public function l2Update(ProductCategoryL2Request $request, int $id): JsonResponse
    {
        $category = $this->categoryService->updateL2($id, $request->validated());

        return $this->success(
            new ProductCategoryL2Resource($category),
            '二级品类更新成功'
        );
    }

    /**
     * 删除 L2 品类
     *
     * DELETE /api/v1/admin/categories/l2/{id}
     */
    public function l2Destroy(int $id): JsonResponse
    {
        $this->categoryService->deleteL2($id);

        return $this->success(null, '二级品类删除成功');
    }

    /* ================================================================
     |  品类树
     | ================================================================ */

    /**
     * 完整品类树（L1 + L2 嵌套）
     *
     * GET /api/v1/admin/categories/tree
     */
    public function tree(): JsonResponse
    {
        $tree = $this->categoryService->getCategoryTree();

        return $this->success(
            ProductCategoryL1Resource::collection($tree)
        );
    }
}
