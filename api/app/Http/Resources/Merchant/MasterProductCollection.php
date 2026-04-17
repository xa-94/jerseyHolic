<?php

declare(strict_types=1);

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * 主商品列表分页集合
 *
 * 覆写 toArray 以统一分页响应格式：
 * { code: 0, message: "success", data: { list: [...], total, page, per_page } }
 */
class MasterProductCollection extends ResourceCollection
{
    /**
     * 指定内部使用的单项 Resource
     *
     * @var string
     */
    public $collects = MasterProductResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'list'     => $this->collection,
            'total'    => $this->resource->total(),
            'page'     => $this->resource->currentPage(),
            'per_page' => $this->resource->perPage(),
        ];
    }
}
