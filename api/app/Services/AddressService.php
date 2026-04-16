<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\Zone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * 获取用户所有地址，默认地址排前
     */
    public function getAddresses(int $customerId): Collection
    {
        return CustomerAddress::where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * 获取单个地址（数据隔离：只能访问自己的地址）
     *
     * @throws ModelNotFoundException
     */
    public function getById(int $customerId, int $addressId): CustomerAddress
    {
        return CustomerAddress::where('customer_id', $customerId)
            ->findOrFail($addressId);
    }

    /**
     * 创建地址，如果是第一个地址自动设为默认
     */
    public function create(int $customerId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customerId, $data) {
            $isFirst = !CustomerAddress::where('customer_id', $customerId)->exists();

            // 如果请求中指定为默认，或者是第一个地址，则先清除其他默认
            if (!empty($data['is_default']) || $isFirst) {
                CustomerAddress::where('customer_id', $customerId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }

            return CustomerAddress::create(array_merge($data, ['customer_id' => $customerId]));
        });
    }

    /**
     * 更新地址
     */
    public function update(int $customerId, int $addressId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customerId, $addressId, $data) {
            $address = $this->getById($customerId, $addressId);

            // 如果设置为默认，先清除其他默认
            if (!empty($data['is_default'])) {
                CustomerAddress::where('customer_id', $customerId)
                    ->where('id', '!=', $addressId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $address->update($data);
            return $address->fresh();
        });
    }

    /**
     * 删除地址，如果删除的是默认地址，自动将最早的地址设为默认
     */
    public function delete(int $customerId, int $addressId): bool
    {
        return DB::transaction(function () use ($customerId, $addressId) {
            $address = $this->getById($customerId, $addressId);
            $wasDefault = $address->is_default;

            $address->delete();

            // 如果删除的是默认地址，将最早创建的地址设为默认
            if ($wasDefault) {
                $earliest = CustomerAddress::where('customer_id', $customerId)
                    ->orderBy('id')
                    ->first();

                if ($earliest) {
                    $earliest->update(['is_default' => true]);
                }
            }

            return true;
        });
    }

    /**
     * 设置默认地址（事务包裹）
     */
    public function setDefault(int $customerId, int $addressId): CustomerAddress
    {
        return DB::transaction(function () use ($customerId, $addressId) {
            // 先清除旧默认
            CustomerAddress::where('customer_id', $customerId)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            // 设置新默认
            $address = $this->getById($customerId, $addressId);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }

    /**
     * 获取国家列表（仅启用的）
     */
    public function getCountries(): Collection
    {
        return Country::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code_2', 'iso_code_3', 'postcode_required']);
    }

    /**
     * 获取州/省列表
     */
    public function getZones(int $countryId): Collection
    {
        return Zone::where('country_id', $countryId)
            ->orderBy('name')
            ->get(['id', 'country_id', 'name', 'code']);
    }
}
