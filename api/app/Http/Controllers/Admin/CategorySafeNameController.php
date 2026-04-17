<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CategorySafeNameRequest;
use App\Http\Resources\Admin\CategorySafeNameResource;
use App\Models\Central\CategorySafeName;
use App\Services\Product\CategoryMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 — 品类安全映射名称 Controller（M4-002）
 *
 * 路由前缀：/api/v1/admin/category-safe-names
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 管理品类级安全名称映射，用于支付/物流场景的商品名称脱敏。
 */
class CategorySafeNameController extends BaseAdminController
{
    public function __construct(
        private readonly CategoryMappingService $categoryMappingService,
    ) {}

    /**
     * 安全映射名称列表
     *
     * GET /api/v1/admin/category-safe-names
     *
     * Query params:
     *  - category_l1_id  int|null   L1 品类 ID
     *  - category_l2_id  int|null   L2 品类 ID
     *  - store_id        int|null   站点 ID
     *  - sku_prefix      string     SKU 前缀
     *  - status          int        状态（0=禁用 / 1=启用）
     *  - per_page        int        每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = CategorySafeName::query();

        if ($request->filled('category_l1_id')) {
            $query->where('category_l1_id', $request->integer('category_l1_id'));
        }

        if ($request->filled('category_l2_id')) {
            $query->where('category_l2_id', $request->integer('category_l2_id'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }

        if ($request->filled('sku_prefix')) {
            $query->where('sku_prefix', $request->input('sku_prefix'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        $paginator = $query->with(['categoryL1', 'categoryL2', 'store'])
            ->orderByDesc('weight')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginate($paginator->through(
            fn ($item) => new CategorySafeNameResource($item)
        ));
    }

    /**
     * 创建安全映射名称
     *
     * POST /api/v1/admin/category-safe-names
     */
    public function store(CategorySafeNameRequest $request): JsonResponse
    {
        $safeName = CategorySafeName::create($request->validated());

        $this->categoryMappingService->clearCache($safeName->store_id);

        return $this->success(
            new CategorySafeNameResource($safeName->load(['categoryL1', 'categoryL2', 'store'])),
            '安全映射名称创建成功'
        );
    }

    /**
     * 更新安全映射名称
     *
     * PUT /api/v1/admin/category-safe-names/{id}
     */
    public function update(CategorySafeNameRequest $request, int $id): JsonResponse
    {
        $safeName    = CategorySafeName::findOrFail($id);
        $oldStoreId  = $safeName->store_id;

        $safeName->update($request->validated());

        // 清除旧缓存和新缓存
        $this->categoryMappingService->clearCache($oldStoreId);
        if ($oldStoreId !== $safeName->store_id) {
            $this->categoryMappingService->clearCache($safeName->store_id);
        }

        return $this->success(
            new CategorySafeNameResource($safeName->fresh(['categoryL1', 'categoryL2', 'store'])),
            '安全映射名称已更新'
        );
    }

    /**
     * 删除安全映射名称
     *
     * DELETE /api/v1/admin/category-safe-names/{id}
     *
     * 约束：同一品类维度至少保留 1 条 active 记录
     */
    public function destroy(int $id): JsonResponse
    {
        $safeName = CategorySafeName::findOrFail($id);

        // 检查同组 active 记录数量
        if ($safeName->status === CategorySafeName::STATUS_ACTIVE) {
            $activeCount = CategorySafeName::query()
                ->active()
                ->where(function ($q) use ($safeName) {
                    if ($safeName->category_l1_id !== null) {
                        $q->where('category_l1_id', $safeName->category_l1_id);
                    } else {
                        $q->whereNull('category_l1_id');
                    }
                })
                ->where(function ($q) use ($safeName) {
                    if ($safeName->store_id !== null) {
                        $q->where('store_id', $safeName->store_id);
                    } else {
                        $q->whereNull('store_id');
                    }
                })
                ->count();

            if ($activeCount <= 1) {
                return $this->error(42200, '至少保留一条有效安全映射名称');
            }
        }

        $this->categoryMappingService->clearCache($safeName->store_id);
        $safeName->delete();

        return $this->success(null, '安全映射名称已删除');
    }

    /**
     * 手动清除缓存
     *
     * POST /api/v1/admin/category-safe-names/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $storeId = $request->filled('store_id') ? $request->integer('store_id') : null;
        $this->categoryMappingService->clearCache($storeId);

        return $this->success(null, '缓存已清除');
    }

    /**
     * 预览安全名称解析结果
     *
     * GET /api/v1/admin/category-safe-names/preview
     *
     * Query params:
     *  - store_id         int       站点 ID（必填）
     *  - sku              string    商品 SKU
     *  - category_l1_id   int       L1 品类 ID
     *  - category_l2_id   int       L2 品类 ID
     *  - locale           string    语言代码，默认 en
     */
    public function preview(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id', 0);
        $sku     = $request->input('sku');
        $l1Id    = $request->filled('category_l1_id') ? $request->integer('category_l1_id') : null;
        $l2Id    = $request->filled('category_l2_id') ? $request->integer('category_l2_id') : null;
        $locale  = $request->input('locale', 'en');

        $safeName = $this->categoryMappingService->resolve(
            storeId: $storeId,
            sku: $sku,
            categoryL1Id: $l1Id,
            categoryL2Id: $l2Id,
            locale: $locale,
        );

        return $this->success([
            'safe_name'      => $safeName,
            'sku'            => $sku,
            'category_l1_id' => $l1Id,
            'category_l2_id' => $l2Id,
            'store_id'       => $storeId,
            'locale'         => $locale,
        ]);
    }
}
