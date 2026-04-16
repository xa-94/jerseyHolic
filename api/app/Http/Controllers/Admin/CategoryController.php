<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\BaseAdminController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseAdminController
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * 获取分类列表
     *
     * 返回分页的分类列表，支持按关键词、状态、父分类 ID 筛选。
     * 可用于管理后台分类列表展示，与树形接口不同，该接口返回平铺分页数据。
     * GET /api/admin/categories
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->only(['keyword', 'status', 'parent_id', 'per_page', 'page']);
        $paginator = $this->categoryService->getList($params);

        return $this->paginate($paginator);
    }

    /**
     * 创建分类
     *
     * 创建新的商品分类，支持设置父分类实现多级分类结构。
     * 分类名称在同一父层级下应具有唯一性。
     * POST /api/admin/categories
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->create($request->validated());
            return $this->success($category, '分类创建成功');
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }

    /**
     * 获取分类详情
     *
     * 返回单个分类的完整信息，包含父分类信息、居拉取商品数等。
     * 分类不存在时返回 404。
     * GET /api/admin/categories/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->getById($id);
            return $this->success($category);
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }

    /**
     * 更新分类
     *
     * 更新指定分类的名称、父分类、状态、排序等信息。
     * 不允许将分类设置为自身的后代分类作为父分类（防止循环）。
     * PUT /api/admin/categories/{id}
     */
    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->update($id, $request->validated());
            return $this->success($category, '分类更新成功');
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }

    /**
     * 删除分类
     *
     * 删除指定分类。如该分类下有子分类或关联商品，将返回错误拒绝删除。
     * DELETE /api/admin/categories/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->categoryService->delete($id);
            return $this->success(null, '分类删除成功');
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }

    /**
     * 获取分类树形结构
     *
     * 返回完整的分类树形结构，每个分类包含居拉取的 `children` 子分类数组。
     * 主要用于前端分类选择器、导航菜单、面包屑等场景。
     * GET /api/admin/categories/tree
     */
    public function tree(): JsonResponse
    {
        $tree = $this->categoryService->getTree();
        return $this->success($tree);
    }

    /**
     * 分类排序
     *
     * 批量更新多个分类的排序权重，默认按 `sort_order` 升序展示分类列表和树形。
     * 请求体：`{ "items": [{"id": 1, "sort_order": 10}, ...] }`。
     * POST /api/admin/categories/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.id'        => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        try {
            $this->categoryService->reorder($request->input('items'));
            return $this->success(null, '排序更新成功');
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }
}
