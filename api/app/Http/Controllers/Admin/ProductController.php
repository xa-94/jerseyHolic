<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ProductListRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseAdminController
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    /**
     * 获取商品列表
     *
     * 返回分页的商品列表，支持按关键词、分类 ID、状态（0 下架/1 上架/2 草稿）、
     * 价格区间等多条件筛选，以及按价格、库存、创建时间等字段排序。
     * 每条记录同时包含商品真实名称与安全映射名称（safe_name）。
     */
    public function index(ProductListRequest $request): JsonResponse
    {
        $params = $request->validated();

        $paginator = $this->productService->getList($params);

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
     * 创建商品
     *
     * 创建新商品，支持同时提交多语言描述（最多 16 种语言）、SKU 变体及商品图片。
     * SKU 编码全局唯一，前 3 位为分类前缀（hic/WPZ/DIY/NBL）。
     * 操作成功后返回完整的商品资源对象（含变体、描述、图片）。
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());
        return $this->success(new ProductResource($product), '商品创建成功');
    }

    /**
     * 获取商品详情
     *
     * 返回指定商品的完整信息，包含所有语言描述、SKU 变体列表、商品图片、
     * 分类信息及库存状态。商品不存在时返回 404。
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->productService->getById($id);
        return $this->success(new ProductResource($product));
    }

    /**
     * 更新商品信息
     *
     * 更新指定商品的基本信息、多语言描述、SKU 变体及图片。
     * 支持部分字段更新，未传字段保持不变。
     * 商品不存在时返回 404，SKU 冲突时返回 422。
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productService->update($id, $request->validated());
        return $this->success(new ProductResource($product), '商品更新成功');
    }

    /**
     * 删除商品
     *
     * 对指定商品执行软删除（设置 deleted_at 时间戳），数据并不物理移除。
     * 已被订单引用的商品仍可查询历史记录。商品不存在时返回 404。
     */
    public function destroy(int $id): JsonResponse
    {
        $this->productService->delete($id);
        return $this->success(null, '商品删除成功');
    }

    /**
     * 更新商品库存
     *
     * 支持三种操作模式：
     * - `set`（默认）：直接将库存设置为指定数量
     * - `increment`：在现有库存基础上增加指定数量
     * - `decrement`：在现有库存基础上减少指定数量（不低于 0）
     *
     * 请求体参数：`quantity`（必填，整数 ≥ 0）、`operation`（可选，默认 set）。
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity'  => 'required|integer|min:0',
            'operation' => 'sometimes|string|in:set,increment,decrement',
        ]);

        $product = $this->productService->updateStock(
            $id,
            (int)$request->input('quantity'),
            $request->input('operation', 'set')
        );

        return $this->success(['id' => $product->id, 'quantity' => $product->quantity], '库存更新成功');
    }

    /**
     * 切换商品启用/禁用状态
     *
     * 将商品状态在「上架（1）」和「下架（0）」之间切换。
     * 返回商品 ID 与最新状态值。商品不存在时返回 404。
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $product = $this->productService->toggleStatus($id);
        return $this->success(['id' => $product->id, 'status' => $product->status], '状态切换成功');
    }

    /**
     * 批量删除商品
     *
     * 根据商品 ID 数组批量执行软删除，至少需传入 1 个 ID。
     * 返回实际删除的商品数量。
     * 请求体参数：`ids`（必填，整数数组）。
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|min:1']);
        $count = $this->productService->bulkDelete($request->input('ids'));
        return $this->success(['deleted' => $count], "已删除 {$count} 个商品");
    }

    /**
     * 批量更新商品状态
     *
     * 将多个商品的状态统一设置为指定值：0（下架）、1（上架）、2（草稿）。
     * 返回实际更新的商品数量。
     * 请求体参数：`ids`（必填，整数数组）、`status`（必填，0/1/2）。
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|min:1',
            'status' => 'required|integer|in:0,1,2',
        ]);

        $count = $this->productService->bulkUpdateStatus(
            $request->input('ids'),
            (int)$request->input('status')
        );

        return $this->success(['updated' => $count], "已更新 {$count} 个商品状态");
    }

    /**
     * 导出商品数据
     *
     * 按当前筛选条件导出商品数据（功能开发中，当前返回占位响应）。
     * 未来计划支持 CSV/Excel 格式，并以异步任务方式生成下载文件。
     */
    public function export(Request $request): JsonResponse
    {
        return $this->success(null, '导出功能开发中');
    }
}
