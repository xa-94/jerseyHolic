<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Tenant 数据库默认数据 Seeder
 *
 * 在新创建的 Tenant DB 中插入必要的初始数据：
 * - 默认语言
 * - 默认货币
 * - 默认站点设置
 * - 默认客户分组
 *
 * 此 Seeder 由 StoreProvisioningService 手动调用，
 * 也可通过 tenancy.seeder_parameters 配置自动执行。
 */
class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant database.
     */
    public function run(): void
    {
        $this->seedLanguages();
        $this->seedCurrencies();
        $this->seedSettings();
        $this->seedCustomerGroups();
    }

    /**
     * 插入默认语言
     */
    protected function seedLanguages(): void
    {
        $now = Carbon::now();

        DB::table('languages')->insert([
            [
                'name'       => 'English',
                'code'       => 'en',
                'locale'     => 'en_US',
                'sort_order' => 1,
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => '中文',
                'code'       => 'zh',
                'locale'     => 'zh_CN',
                'sort_order' => 2,
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * 插入默认货币
     */
    protected function seedCurrencies(): void
    {
        $now = Carbon::now();

        DB::table('currencies')->insert([
            [
                'title'           => 'US Dollar',
                'code'            => 'USD',
                'symbol_left'     => '$',
                'symbol_right'    => '',
                'decimal_place'   => 2,
                'exchange_rate'   => 1.00000000,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'title'           => 'Euro',
                'code'            => 'EUR',
                'symbol_left'     => '€',
                'symbol_right'    => '',
                'decimal_place'   => 2,
                'exchange_rate'   => 0.92000000,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'title'           => 'British Pound',
                'code'            => 'GBP',
                'symbol_left'     => '£',
                'symbol_right'    => '',
                'decimal_place'   => 2,
                'exchange_rate'   => 0.79000000,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ]);
    }

    /**
     * 插入默认站点设置
     */
    protected function seedSettings(): void
    {
        $now = Carbon::now();

        $settings = [
            ['group' => 'general', 'key' => 'store_name',       'value' => 'My Store',  'type' => 'string'],
            ['group' => 'general', 'key' => 'store_email',      'value' => '',           'type' => 'string'],
            ['group' => 'general', 'key' => 'store_phone',      'value' => '',           'type' => 'string'],
            ['group' => 'general', 'key' => 'store_address',    'value' => '',           'type' => 'text'],
            ['group' => 'general', 'key' => 'default_language', 'value' => 'en',         'type' => 'string'],
            ['group' => 'general', 'key' => 'default_currency', 'value' => 'USD',        'type' => 'string'],
            ['group' => 'general', 'key' => 'timezone',         'value' => 'UTC',        'type' => 'string'],
            ['group' => 'shop',    'key' => 'items_per_page',   'value' => '20',         'type' => 'integer'],
            ['group' => 'shop',    'key' => 'allow_guest_checkout', 'value' => '1',      'type' => 'boolean'],
            ['group' => 'email',   'key' => 'mail_driver',      'value' => 'smtp',       'type' => 'string'],
        ];

        foreach ($settings as &$setting) {
            $setting['is_serialized'] = 0;
            $setting['created_at']    = $now;
            $setting['updated_at']    = $now;
        }

        DB::table('settings')->insert($settings);
    }

    /**
     * 插入默认客户分组
     */
    protected function seedCustomerGroups(): void
    {
        $now = Carbon::now();

        DB::table('customer_groups')->insert([
            [
                'name'        => 'Default',
                'description' => 'Default customer group',
                'sort_order'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'VIP',
                'description' => 'VIP customers with special pricing',
                'sort_order'  => 2,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'Wholesale',
                'description' => 'Wholesale/bulk buyers',
                'sort_order'  => 3,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
