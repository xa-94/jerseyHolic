<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductListRequest;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Http\Traits\ApiResponse;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProductService $productService
    ) {}

    /**
     * 商品列表（公开，分页、过滤、排序、多语言）
     */
    public function index(ProductListRequest $request): JsonResponse
    {
        $locale = app()->getLocale() ?: 'en';
        $params = $request->validated();

        $paginator = $this->productService->getBuyerProducts($params, $locale);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => ProductListResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 商品详情（含 SKU、图片、属性、相关商品）
     */
    public function show(int $id): JsonResponse
    {
        $locale  = app()->getLocale() ?: 'en';
        $product = $this->productService->getBuyerProductDetail($id, $locale);
        return $this->success(new ProductResource($product));
    }

    /**
     * 商品搜索（关键词 + 分面过滤）
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['keyword' => 'required|string|min:1|max:100']);

        $locale  = app()->getLocale() ?: 'en';
        $keyword = $request->input('keyword');
        $filters = $request->only([
            'category_id', 'price_min', 'price_max',
            'attributes', 'sort', 'per_page',
        ]);

        $paginator = $this->productService->search($keyword, $filters, $locale);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'keyword'  => $keyword,
                'list'     => ProductListResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 按分类查询商品
     */
    public function byCategory(Request $request, int $categoryId): JsonResponse
    {
        $locale  = app()->getLocale() ?: 'en';
        $params  = $request->only(['price_min', 'price_max', 'attributes', 'sort', 'per_page']);
        $paginator = $this->productService->getByCategory($categoryId, $params, $locale);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => ProductListResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}
