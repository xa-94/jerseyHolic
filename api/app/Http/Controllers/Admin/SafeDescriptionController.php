<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SafeDescriptionRequest;
use App\Http\Resources\Admin\SafeDescriptionResource;
use App\Models\Central\PaypalSafeDescription;
use App\Services\Payment\SafeDescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 — 安全描述管理 Controller（M3-010）
 *
 * 路由前缀：/api/v1/admin/safe-descriptions
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 管理 PayPal/Stripe 支付场景中使用的安全商品描述映射。
 */
class SafeDescriptionController extends BaseAdminController
{
    public function __construct(
        private readonly SafeDescriptionService $safeDescriptionService
    ) {}

    /**
     * 安全描述列表
     *
     * GET /api/v1/admin/safe-descriptions
     *
     * Query params:
     *  - store_id          int|null  站点 ID（不传则返回全部）
     *  - product_category  string    商品分类标识
     *  - status            int       状态（0=禁用 / 1=启用）
     *  - per_page          int       每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaypalSafeDescription::query();

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }

        if ($request->filled('product_category')) {
            $query->where('product_category', $request->input('product_category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        $paginator = $query->with('store')
            ->orderByDesc('weight')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginate($paginator->through(
            fn ($item) => new SafeDescriptionResource($item)
        ));
    }

    /**
     * 创建安全描述
     *
     * POST /api/v1/admin/safe-descriptions
     */
    public function store(SafeDescriptionRequest $request): JsonResponse
    {
        $description = PaypalSafeDescription::create($request->validated());

        return $this->success(
            new SafeDescriptionResource($description),
            '安全描述创建成功'
        );
    }

    /**
     * 更新安全描述
     *
     * PUT /api/v1/admin/safe-descriptions/{id}
     */
    public function update(SafeDescriptionRequest $request, int $id): JsonResponse
    {
        $description = PaypalSafeDescription::findOrFail($id);
        $oldStoreId  = $description->store_id;
        $oldCategory = $description->product_category;

        $description->update($request->validated());

        // 清除旧缓存和新缓存
        $this->safeDescriptionService->clearRelatedCaches($oldStoreId, $oldCategory);
        if ($oldStoreId !== $description->store_id || $oldCategory !== $description->product_category) {
            $this->safeDescriptionService->clearRelatedCaches(
                $description->store_id,
                $description->product_category
            );
        }

        return $this->success(
            new SafeDescriptionResource($description->fresh()),
            '安全描述已更新'
        );
    }

    /**
     * 删除安全描述
     *
     * DELETE /api/v1/admin/safe-descriptions/{id}
     *
     * 约束：同一 store_id + product_category 至少保留 1 条 active 记录
     */
    public function destroy(int $id): JsonResponse
    {
        $description = PaypalSafeDescription::findOrFail($id);

        // 检查同组 active 记录数量
        if ($description->status === PaypalSafeDescription::STATUS_ENABLED) {
            $activeCount = PaypalSafeDescription::query()
                ->where('product_category', $description->product_category)
                ->where(function ($q) use ($description) {
                    if ($description->store_id !== null) {
                        $q->where('store_id', $description->store_id);
                    } else {
                        $q->whereNull('store_id');
                    }
                })
                ->enabled()
                ->count();

            if ($activeCount <= 1) {
                return $this->error(42200, '至少保留一条有效描述');
            }
        }

        // 清除缓存
        $this->safeDescriptionService->clearRelatedCaches(
            $description->store_id,
            $description->product_category
        );

        $description->delete();

        return $this->success(null, '安全描述已删除');
    }
}
