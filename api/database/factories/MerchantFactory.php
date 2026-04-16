<?php

namespace Database\Factories;

use App\Models\Central\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        return [
            'merchant_name' => $this->faker->company(),
            'email'         => $this->faker->unique()->safeEmail(),
            'password'      => bcrypt('password'),
            'contact_name'  => $this->faker->name(),
            'phone'         => $this->faker->phoneNumber(),
            'level'         => 'starter',
            'status'        => 1,
            'login_failures' => 0,
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
     * 未激活状态
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }

    /**
     * 指定等级
     */
    public function level(string $level): static
    {
        return $this->state(fn () => ['level' => $level]);
    }

    /**
     * VIP 等级（不限站点数量）
     */
    public function vip(): static
    {
        return $this->state(fn () => ['level' => 'vip']);
    }
}
