<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\ProductCategoryL1;
use App\Models\Central\ProductCategoryL2;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * 品类管理服务 — Central DB
 *
 * 提供 L1/L2 品类的 CRUD、品类树查询等业务逻辑。
 * Controller 保持精简，所有业务操作委托到本服务。
 */
class ProductCategoryManagementService
{
    /* ================================================================
     |  L1 一级品类
     | ================================================================ */

    /**
     * L1 列表（含子品类计数）
     */
    public function getL1List(array $params): LengthAwarePaginator
    {
        $query = ProductCategoryL1::query()->withCount('children');

        if (! empty($params['status'])) {
            $query->where('status', (int) $params['status']);
        }

        if (! empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$keyword}%"]);
            });
        }

        return $query->orderBy('sort_order')
                     ->orderByDesc('id')
                     ->paginate((int) ($params['per_page'] ?? 15));
    }

    /**
     * 创建 L1 品类
     */
    public function createL1(array $data): ProductCategoryL1
    {
        return ProductCategoryL1::create($data);
    }

    /**
     * L1 品类详情
     */
    public function getL1Detail(int $id): ProductCategoryL1
    {
        return ProductCategoryL1::withCount('children')->findOrFail($id);
    }

    /**
     * 更新 L1 品类
     */
    public function updateL1(int $id, array $data): ProductCategoryL1
    {
        $category = ProductCategoryL1::findOrFail($id);
        $category->update($data);

        return $category->fresh();
    }

    /**
     * 删除 L1 品类（仅无子品类时允许）
     *
     * @throws \RuntimeException
     */
    public function deleteL1(int $id): void
    {
        $category = ProductCategoryL1::findOrFail($id);

        if ($category->children()->exists()) {
            throw new \RuntimeException('该一级品类下有子品类，无法删除', 42200);
        }

        $category->delete();
    }

    /* ================================================================
     |  L2 二级品类
     | ================================================================ */

    /**
     * L2 列表（可按 l1_id 筛选）
     */
    public function getL2List(array $params): LengthAwarePaginator
    {
        $query = ProductCategoryL2::query()->with('parent');

        if (! empty($params['l1_id'])) {
            $query->where('l1_id', (int) $params['l1_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', (int) $params['status']);
        }

        if (! empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$keyword}%"]);
            });
        }

        return $query->orderBy('sort_order')
                     ->orderByDesc('id')
                     ->paginate((int) ($params['per_page'] ?? 15));
    }

    /**
     * 创建 L2 品类
     */
    public function createL2(array $data): ProductCategoryL2
    {
        return ProductCategoryL2::create($data);
    }

    /**
     * L2 品类详情
     */
    public function getL2Detail(int $id): ProductCategoryL2
    {
        return ProductCategoryL2::with('parent')->findOrFail($id);
    }

    /**
     * 更新 L2 品类
     */
    public function updateL2(int $id, array $data): ProductCategoryL2
    {
        $category = ProductCategoryL2::findOrFail($id);
        $category->update($data);

        return $category->fresh()->load('parent');
    }

    /**
     * 删除 L2 品类
     */
    public function deleteL2(int $id): void
    {
        $category = ProductCategoryL2::findOrFail($id);
        $category->delete();
    }

    /* ================================================================
     |  品类树
     | ================================================================ */

    /**
     * 完整品类树（L1 + L2 嵌套）
     *
     * 返回所有 active 的 L1 品类及其下 active 的 L2 品类。
     */
    public function getCategoryTree(): Collection
    {
        return ProductCategoryL1::query()
            ->active()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }
}
