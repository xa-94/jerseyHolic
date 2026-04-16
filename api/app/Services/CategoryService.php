<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryDescription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * 获取完整分类树（含多语言描述）
     */
    public function getTree(): array
    {
        $categories = Category::with('descriptions')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($categories, 0);
    }

    /**
     * 获取分页列表（支持搜索、状态过滤）
     */
    public function getList(array $params): LengthAwarePaginator
    {
        $query = Category::with('descriptions')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->whereHas('descriptions', function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%");
            });
        }

        if (isset($params['parent_id'])) {
            $query->where('parent_id', $params['parent_id']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);

        return $query->paginate($perPage);
    }

    /**
     * 获取单个分类详情（含所有语言描述）
     */
    public function getById(int $id): Category
    {
        $category = Category::with(['descriptions', 'children.descriptions'])->find($id);

        if (!$category) {
            throw new \RuntimeException('分类不存在', 40400);
        }

        return $category;
    }

    /**
     * 创建分类（含多语言描述）
     */
    public function create(array $data): Category
    {
        DB::beginTransaction();
        try {
            $category = Category::create([
                'parent_id'  => $data['parent_id'] ?? 0,
                'image'      => $data['image'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'status'     => $data['status'] ?? 1,
            ]);

            if (!empty($data['descriptions'])) {
                $this->syncDescriptions($category->id, $data['descriptions']);
            }

            DB::commit();

            return $category->load('descriptions');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 更新分类
     */
    public function update(int $id, array $data): Category
    {
        $category = Category::find($id);
        if (!$category) {
            throw new \RuntimeException('分类不存在', 40400);
        }

        DB::beginTransaction();
        try {
            $fillable = array_intersect_key($data, array_flip(['parent_id', 'image', 'sort_order', 'status']));
            $category->fill($fillable)->save();

            if (isset($data['descriptions'])) {
                $this->syncDescriptions($category->id, $data['descriptions']);
            }

            DB::commit();

            return $category->load('descriptions');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除分类（检查子分类和关联商品）
     */
    public function delete(int $id): void
    {
        $category = Category::find($id);
        if (!$category) {
            throw new \RuntimeException('分类不存在', 40400);
        }

        // 检查是否有子分类
        $childCount = Category::where('parent_id', $id)->count();
        if ($childCount > 0) {
            throw new \RuntimeException('该分类下存在子分类，无法删除', 42201);
        }

        // 检查是否有关联商品
        $productCount = DB::table('jh_product_categories')
            ->where('category_id', $id)
            ->count();
        if ($productCount > 0) {
            throw new \RuntimeException('该分类下存在关联商品，无法删除', 42202);
        }

        DB::beginTransaction();
        try {
            CategoryDescription::where('category_id', $id)->delete();
            $category->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 批量排序
     * $data 格式: [['id' => 1, 'sort_order' => 10], ...]
     */
    public function reorder(array $data): void
    {
        DB::beginTransaction();
        try {
            foreach ($data as $item) {
                if (isset($item['id'], $item['sort_order'])) {
                    Category::where('id', $item['id'])
                        ->update(['sort_order' => $item['sort_order']]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 买家端分类树（仅启用，指定语言）
     */
    public function getBuyerTree(string $locale = 'en'): array
    {
        $categories = Category::active()
            ->with(['descriptions' => function ($q) use ($locale) {
                $q->where('locale', $locale);
            }])
            ->withCount(['products' => function ($q) {
                $q->where('jh_products.status', 1);
            }])
            ->orderBy('sort_order')
            ->get();

        return $this->buildBuyerTree($categories, 0, $locale);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * 递归构建树（后台，含多语言描述数组）
     */
    private function buildTree(Collection $categories, int $parentId): array
    {
        $branch = [];

        foreach ($categories as $category) {
            if ((int) $category->parent_id === $parentId) {
                $node = $this->formatCategoryNode($category);
                $node['children'] = $this->buildTree($categories, $category->id);
                $branch[] = $node;
            }
        }

        return $branch;
    }

    /**
     * 递归构建买家端树（含 product_count，单语言）
     */
    private function buildBuyerTree(Collection $categories, int $parentId, string $locale): array
    {
        $branch = [];

        foreach ($categories as $category) {
            if ((int) $category->parent_id === $parentId) {
                $desc = $category->descriptions->first();
                $node = [
                    'id'            => $category->id,
                    'parent_id'     => $category->parent_id,
                    'image'         => $category->image,
                    'sort_order'    => $category->sort_order,
                    'product_count' => $category->products_count ?? 0,
                    'name'          => $desc->name ?? '',
                    'description'   => $desc->description ?? '',
                    'slug'          => $desc->slug ?? '',
                    'children'      => $this->buildBuyerTree($categories, $category->id, $locale),
                ];
                $branch[] = $node;
            }
        }

        return $branch;
    }

    /**
     * 格式化后台分类节点（含 descriptions 数组）
     */
    private function formatCategoryNode(Category $category): array
    {
        $descriptions = [];
        foreach ($category->descriptions as $desc) {
            $descriptions[$desc->locale] = [
                'name'             => $desc->name,
                'description'      => $desc->description,
                'meta_title'       => $desc->meta_title,
                'meta_description' => $desc->meta_description,
                'meta_keywords'    => $desc->meta_keywords,
                'slug'             => $desc->slug,
            ];
        }

        return [
            'id'           => $category->id,
            'parent_id'    => $category->parent_id,
            'image'        => $category->image,
            'sort_order'   => $category->sort_order,
            'status'       => $category->status,
            'created_at'   => $category->created_at?->toISOString(),
            'updated_at'   => $category->updated_at?->toISOString(),
            'descriptions' => $descriptions,
        ];
    }

    /**
     * 同步多语言描述（upsert）
     */
    private function syncDescriptions(int $categoryId, array $descriptions): void
    {
        foreach ($descriptions as $locale => $desc) {
            if (empty($locale) || empty($desc['name'])) {
                continue;
            }

            $slugBase = $desc['slug'] ?? Str::slug($desc['name']);

            CategoryDescription::updateOrCreate(
                ['category_id' => $categoryId, 'locale' => $locale],
                [
                    'name'             => $desc['name'],
                    'description'      => $desc['description'] ?? null,
                    'meta_title'       => $desc['meta_title'] ?? null,
                    'meta_description' => $desc['meta_description'] ?? null,
                    'meta_keywords'    => $desc['meta_keywords'] ?? null,
                    'slug'             => $slugBase,
                ]
            );
        }
    }
}
