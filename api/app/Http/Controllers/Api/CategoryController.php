<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * 买家端分类树（公开接口，含商品计数）
     * GET /api/v1/categories
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->header('Accept-Language', 'en');
        // 只取主语言代码，例如 "zh-CN" -> "zh-CN", "en-US" -> "en"
        $locale = strtolower(explode(',', $locale)[0]);
        $locale = strtolower(str_replace('-', '_', $locale));

        $tree = $this->categoryService->getBuyerTree($locale);

        return $this->success($tree);
    }

    /**
     * 买家端单个分类详情（含子分类列表）
     * GET /api/v1/categories/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = app()->getLocale();

        try {
            $category = $this->categoryService->getById($id);

            // 只返回对应语言描述
            $desc = $category->descriptions->firstWhere('locale', $locale)
                ?? $category->descriptions->firstWhere('locale', 'en');

            // 过滤子分类只返回启用的
            $children = $category->children
                ->where('status', 1)
                ->sortBy('sort_order')
                ->values()
                ->map(function ($child) use ($locale) {
                    $childDesc = $child->descriptions->firstWhere('locale', $locale)
                        ?? $child->descriptions->firstWhere('locale', 'en');
                    return [
                        'id'          => $child->id,
                        'parent_id'   => $child->parent_id,
                        'image'       => $child->image,
                        'sort_order'  => $child->sort_order,
                        'name'        => $childDesc->name ?? '',
                        'slug'        => $childDesc->slug ?? '',
                    ];
                });

            return $this->success([
                'id'          => $category->id,
                'parent_id'   => $category->parent_id,
                'image'       => $category->image,
                'sort_order'  => $category->sort_order,
                'status'      => $category->status,
                'name'        => $desc->name ?? '',
                'description' => $desc->description ?? '',
                'slug'        => $desc->slug ?? '',
                'meta_title'       => $desc->meta_title ?? '',
                'meta_description' => $desc->meta_description ?? '',
                'children'    => $children,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getCode() ?: 50000, $e->getMessage());
        }
    }
}
