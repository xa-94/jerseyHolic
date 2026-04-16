<?php

namespace Database\Factories;

use App\Models\Central\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $storeCode = 'store_' . Str::random(6);

        return [
            'store_name'        => $this->faker->company() . ' Store',
            'store_code'        => $storeCode,
            'domain'            => $storeCode . '.jerseyholic.test',
            'status'            => 1,
            'database_password' => Str::random(32),
        ];
    }

    /**
     * 激活状态
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => 1]);
    }

    /**
     * 维护模式
     */
    public function maintenance(): static
    {
        return $this->state(fn () => ['status' => 'maintenance']);
    }

    /**
     * 停用状态
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }

    /**
     * 指定商户
     */
    public function forMerchant(int $merchantId): static
    {
        return $this->state(fn () => ['merchant_id' => $merchantId]);
    }
}
