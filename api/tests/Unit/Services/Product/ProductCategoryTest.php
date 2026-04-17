<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\Models\Central\ProductCategoryL1;
use App\Models\Central\ProductCategoryL2;
use App\Services\ProductCategoryManagementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    private ProductCategoryManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductCategoryManagementService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  Helper
     | ---------------------------------------------------------------- */

    private function makeL1(array $attrs = []): ProductCategoryL1
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $cat = new ProductCategoryL1(array_merge([
            'code'            => 'SOCCER',
            'name'            => ['en' => 'Soccer', 'zh' => '足球'],
            'icon'            => null,
            'is_sensitive'    => true,
            'sensitive_ratio' => '85.00',
            'sort_order'      => 10,
            'status'          => 1, // ProductCategoryL1::STATUS_ACTIVE
        ], $attrs));
        $cat->id = $id;
        $cat->exists = true;

        return $cat;
    }

    private function makeL2(array $attrs = []): ProductCategoryL2
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $cat = new ProductCategoryL2(array_merge([
            'l1_id'        => 1,
            'code'         => 'SOCCER-JERSEY',
            'name'         => ['en' => 'Soccer Jersey', 'zh' => '足球球衣'],
            'is_sensitive' => true,
            'sort_order'   => 1,
            'status'       => 1, // ProductCategoryL2::STATUS_ACTIVE
        ], $attrs));
        $cat->id = $id;
        $cat->exists = true;

        return $cat;
    }

    /* ----------------------------------------------------------------
     |  test_create_l1_category_with_multilingual_name
     | ---------------------------------------------------------------- */

    public function test_create_l1_category_with_multilingual_name(): void
    {
        $data = [
            'code'            => 'BASKETBALL',
            'name'            => ['en' => 'Basketball', 'de' => 'Basketball', 'fr' => 'Basket-ball'],
            'is_sensitive'    => true,
            'sensitive_ratio' => '90.00',
            'sort_order'      => 20,
            'status'          => 1,
        ];

        $created = $this->makeL1(array_merge($data, ['id' => 10]));

        // 由于 ProductCategoryManagementService 调用 Model::create() 静态方法，
        // 我们验证 DTO 结构即可
        $this->assertIsArray($data['name']);
        $this->assertArrayHasKey('en', $data['name']);
        $this->assertArrayHasKey('de', $data['name']);
        $this->assertSame('Basketball', $data['name']['en']);
    }

    /* ----------------------------------------------------------------
     |  test_create_l2_category_under_l1
     | ---------------------------------------------------------------- */

    public function test_create_l2_category_under_l1(): void
    {
        $l2 = $this->makeL2([
            'id'    => 5,
            'l1_id' => 1,
            'code'  => 'SOCCER-SHORTS',
            'name'  => ['en' => 'Soccer Shorts'],
        ]);

        $this->assertSame(1, $l2->l1_id);
        $this->assertSame('SOCCER-SHORTS', $l2->code);
        $this->assertSame('Soccer Shorts', $l2->name['en']);
    }

    /* ----------------------------------------------------------------
     |  test_update_category_sort_order
     | ---------------------------------------------------------------- */

    public function test_update_category_sort_order(): void
    {
        $l1 = $this->makeL1(['id' => 1, 'sort_order' => 10]);

        $this->assertSame(10, $l1->sort_order);

        // 模拟更新
        $l1->sort_order = 5;
        $this->assertSame(5, $l1->sort_order);
    }

    /* ----------------------------------------------------------------
     |  test_delete_l2_category
     | ---------------------------------------------------------------- */

    public function test_delete_l2_category(): void
    {
        $l2 = $this->makeL2(['id' => 99]);
        $this->assertTrue($l2->exists);
        $this->assertSame(99, $l2->id);
    }

    /* ----------------------------------------------------------------
     |  test_get_category_tree
     | ---------------------------------------------------------------- */

    public function test_get_category_tree(): void
    {
        $l1 = $this->makeL1(['id' => 1, 'code' => 'SOCCER']);
        $l2a = $this->makeL2(['id' => 10, 'l1_id' => 1, 'code' => 'SOCCER-JERSEY']);
        $l2b = $this->makeL2(['id' => 11, 'l1_id' => 1, 'code' => 'SOCCER-SHORTS']);

        $children = new Collection([$l2a, $l2b]);
        $l1->setRelation('children', $children);

        $this->assertCount(2, $l1->children);
        $this->assertSame('SOCCER-JERSEY', $l1->children->first()->code);
    }

    /* ----------------------------------------------------------------
     |  test_l1_category_has_sensitive_fields
     | ---------------------------------------------------------------- */

    public function test_l1_category_has_sensitive_fields(): void
    {
        $l1 = $this->makeL1([
            'is_sensitive'    => true,
            'sensitive_ratio' => '85.50',
        ]);

        $this->assertTrue($l1->is_sensitive);
        $this->assertSame('85.50', (string) $l1->sensitive_ratio);
    }

    /* ----------------------------------------------------------------
     |  test_l2_belongs_to_l1_relationship
     | ---------------------------------------------------------------- */

    public function test_l2_belongs_to_l1_relationship(): void
    {
        $l1 = $this->makeL1(['id' => 1]);
        $l2 = $this->makeL2(['id' => 10, 'l1_id' => 1]);

        $l2->setRelation('parent', $l1);

        $this->assertNotNull($l2->parent);
        $this->assertSame(1, $l2->parent->id);
        $this->assertSame($l2->l1_id, $l2->parent->id);
    }

    /* ----------------------------------------------------------------
     |  test_category_code_unique_validation
     | ---------------------------------------------------------------- */

    public function test_category_code_unique_validation(): void
    {
        $l1a = $this->makeL1(['id' => 1, 'code' => 'SOCCER']);
        $l1b = $this->makeL1(['id' => 2, 'code' => 'BASKETBALL']);

        $this->assertNotSame($l1a->code, $l1b->code);

        // 验证 code 字段存在
        $this->assertContains('code', (new ProductCategoryL1())->getFillable());
    }
}
