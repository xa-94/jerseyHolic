<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SensitiveBrandCheckRequest;
use App\Http\Requests\Admin\SensitiveBrandRequest;
use App\Http\Resources\Admin\SensitiveBrandResource;
use App\Models\Central\SensitiveBrand;
use App\Services\Product\SensitiveGoodsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 敏感品牌管理 Controller（M4-003）
 *
 * 路由前缀：/api/v1/admin/sensitive-brands
 * 中间件：auth:sanctum + force.json + central.only（Batch 6 注册）
 *
 * 功能：品牌黑名单 CRUD + 特货检测测试接口
 */
class SensitiveBrandController extends BaseAdminController
{
    public function __construct(
        private readonly SensitiveGoodsService $sensitiveGoodsService,
    ) {}

    /**
     * 敏感品牌列表
     *
     * GET /api/v1/admin/sensitive-brands
     *
     * Query params:
     *  - category_l1_id  int     按品类筛选
     *  - risk_level      string  按风险等级筛选（high/medium/low）
     *  - keyword         string  按品牌名模糊搜索
     *  - status          int     按状态筛选（0/1）
     *  - per_page        int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = SensitiveBrand::query()->with('categoryL1');

        if ($request->filled('category_l1_id')) {
            $query->where('category_l1_id', (int) $request->input('category_l1_id'));
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        if ($request->filled('keyword')) {
            $query->where('brand_name', 'like', '%' . $request->input('keyword') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        $paginator = $query->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 15));

        $paginator->getCollection()->transform(
            fn ($item) => new SensitiveBrandResource($item)
        );

        return $this->paginate($paginator);
    }

    /**
     * 添加敏感品牌
     *
     * POST /api/v1/admin/sensitive-brands
     */
    public function store(SensitiveBrandRequest $request): JsonResponse
    {
        $data = $request->validated();

        $brand = SensitiveBrand::create($data);
        $brand->load('categoryL1');

        // 清除缓存
        $this->sensitiveGoodsService->clearCache();

        return $this->success(
            new SensitiveBrandResource($brand),
            '敏感品牌已添加'
        );
    }

    /**
     * 敏感品牌详情
     *
     * GET /api/v1/admin/sensitive-brands/{id}
     */
    public function show(int $id): JsonResponse
    {
        $brand = SensitiveBrand::with('categoryL1')->findOrFail($id);

        return $this->success(new SensitiveBrandResource($brand), '获取成功');
    }

    /**
     * 更新敏感品牌
     *
     * PUT /api/v1/admin/sensitive-brands/{id}
     */
    public function update(SensitiveBrandRequest $request, int $id): JsonResponse
    {
        $brand = SensitiveBrand::findOrFail($id);
        $brand->update($request->validated());
        $brand->load('categoryL1');

        // 清除缓存
        $this->sensitiveGoodsService->clearCache();

        return $this->success(
            new SensitiveBrandResource($brand),
            '敏感品牌已更新'
        );
    }

    /**
     * 删除敏感品牌
     *
     * DELETE /api/v1/admin/sensitive-brands/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $brand = SensitiveBrand::findOrFail($id);
        $brand->delete();

        // 清除缓存
        $this->sensitiveGoodsService->clearCache();

        return $this->success(null, '敏感品牌已删除');
    }

    /**
     * 测试特货检测
     *
     * POST /api/v1/admin/sensitive-brands/check
     *
     * 传入 sku + brand + category_l1_id，返回三级判定结果。
     */
    public function check(SensitiveBrandCheckRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->sensitiveGoodsService->identify(
            sku:          $data['sku'],
            brandName:    $data['brand'] ?? null,
            categoryL1Id: isset($data['category_l1_id']) ? (int) $data['category_l1_id'] : null,
        );

        return $this->success($result->toArray(), '检测完成');
    }
}
