<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Merchant\UpdateStoreProductConfigRequest;
use App\Http\Resources\Merchant\StoreProductConfigResource;
use App\Models\Central\Store;
use App\Services\Product\StoreProductConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商户端 — 站点商品配置控制器
 *
 * 管理站点级的商品展示差异化配置：价格覆盖、安全名称、展示语言等。
 *
 * 端点前缀：/api/v1/merchant/stores/{storeId}/product-config
 * Guard：merchant（Sanctum）
 */
class StoreProductConfigController extends BaseController
{
    public function __construct(
        protected readonly StoreProductConfigService $configService,
    ) {}

    /**
     * 获取站点商品配置
     *
     * GET /api/v1/merchant/stores/{storeId}/product-config
     */
    public function show(string $storeId): JsonResponse
    {
        $this->authorizeStoreAccess($storeId);

        $config = $this->configService->getConfig($storeId);

        if (!$config) {
            return $this->success(null, '该站点暂无自定义配置，将使用默认设置');
        }

        $config->load('store');

        return $this->success(new StoreProductConfigResource($config));
    }

    /**
     * 更新站点商品配置
     *
     * PUT /api/v1/merchant/stores/{storeId}/product-config
     */
    public function update(UpdateStoreProductConfigRequest $request, string $storeId): JsonResponse
    {
        $this->authorizeStoreAccess($storeId);

        $config = $this->configService->updateConfig($storeId, $request->validated());

        $config->load('store');

        return $this->success(
            new StoreProductConfigResource($config),
            '站点商品配置已更新',
        );
    }

    /**
     * 预览价格覆盖效果（不修改数据库）
     *
     * POST /api/v1/merchant/stores/{storeId}/product-config/preview
     */
    public function preview(Request $request, string $storeId): JsonResponse
    {
        $this->authorizeStoreAccess($storeId);

        $request->validate([
            'base_price' => 'required|numeric|min:0',
        ], [
            'base_price.required' => '基础价格不能为空',
            'base_price.numeric'  => '基础价格必须为数字',
            'base_price.min'      => '基础价格不能小于 0',
        ]);

        $basePrice     = (string) $request->input('base_price');
        $overridePrice = $this->configService->applyPriceOverride($storeId, $basePrice);

        return $this->success([
            'base_price'     => bcadd($basePrice, '0', 2),
            'override_price' => $overridePrice,
            'has_override'   => bccomp($basePrice, $overridePrice, 2) !== 0,
        ]);
    }

    /* ================================================================
     |  私有方法
     | ================================================================ */

    /**
     * 验证站点归属当前商户
     *
     * @throws \App\Exceptions\BusinessException
     */
    private function authorizeStoreAccess(string $storeId): void
    {
        /** @var \App\Models\Central\MerchantUser $user */
        $user = auth('merchant')->user();

        $store = Store::where('id', $storeId)
            ->where('merchant_id', $user->merchant_id)
            ->first();

        if (!$store) {
            throw new \App\Exceptions\BusinessException(
                \App\Enums\ErrorCode::FORBIDDEN,
                '无权访问该站点',
            );
        }
    }
}
