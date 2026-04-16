<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends BaseApiController
{
    public function __construct(private readonly AddressService $addressService)
    {
    }

    /**
     * 获取收货地址列表
     *
     * 返回当前登录买家的所有收货地址，默认地址会标记 `is_default: true`。
     * 需要 Sanctum 认证。
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->getAddresses($request->user()->id);
        return $this->success(AddressResource::collection($addresses));
    }

    /**
     * 添加收货地址
     *
     * 为当前登录买家创建一条新的收货地址。
     * 如果设置 `is_default: true`，会自动将其他地址的默认标记取消。
     */
    public function store(AddressRequest $request): JsonResponse
    {
        $address = $this->addressService->create(
            $request->user()->id,
            $request->validated()
        );
        return $this->success(new AddressResource($address), '地址已添加');
    }

    /**
     * 获取地址详情
     *
     * 返回当前登录买家的指定收货地址详情。
     * 地址不属于当前用户时返回 404。
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $address = $this->addressService->getById($request->user()->id, $id);
        return $this->success(new AddressResource($address));
    }

    /**
     * 更新收货地址
     *
     * 更新当前登录买家的指定收货地址。
     * 如果设置 `is_default: true`，自动将其他地址的默认标记取消。
     * 地址不属于当前用户时返回 404。
     */
    public function update(AddressRequest $request, int $id): JsonResponse
    {
        $address = $this->addressService->update(
            $request->user()->id,
            $id,
            $request->validated()
        );
        return $this->success(new AddressResource($address), '地址已更新');
    }

    /**
     * 删除收货地址
     *
     * 删除当前登录买家的指定收货地址。
     * 地址不属于当前用户时返回 404。
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->addressService->delete($request->user()->id, $id);
        return $this->success(null, '地址已删除');
    }

    /**
     * 设置默认收货地址
     *
     * 将当前登录买家的指定地址设为默认地址，并自动取消其他地址的默认标记。
     * 地址不属于当前用户时返回 404。
     */
    public function setDefault(Request $request, int $id): JsonResponse
    {
        $address = $this->addressService->setDefault($request->user()->id, $id);
        return $this->success(new AddressResource($address), '默认地址已设置');
    }

    /**
     * 获取支持的国家列表
     *
     * 公开接口，无需认证。返回系统内置的国家列表，包含国家 ID、名称和国家代码。
     * 主要用于地址表单下拉选择。
     */
    public function countries(): JsonResponse
    {
        $countries = $this->addressService->getCountries();
        return $this->success($countries);
    }

    /**
     * 获取指定国家下的州/省列表
     *
     * 公开接口，无需认证。返回指定国家 ID 下属的州/省/地区列表。
     * 如该国家没有州/省划分，返回空数组。主要用于地址表单次级下拉。
     */
    public function zones(int $countryId): JsonResponse
    {
        $zones = $this->addressService->getZones($countryId);
        return $this->success($zones);
    }
}
