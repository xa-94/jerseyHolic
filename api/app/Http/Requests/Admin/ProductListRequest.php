<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 商品列表查询参数
 */
class ProductListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword'    => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|min:1',
            'status'     => 'nullable|integer|in:0,1,2',
            'sku_prefix' => 'nullable|string|max:64',
            'price_min'  => 'nullable|numeric|min:0',
            'price_max'  => 'nullable|numeric|min:0',
            'sort_field' => 'nullable|string|in:id,price,quantity,sort_order,created_at',
            'sort_dir'   => 'nullable|string|in:asc,desc',
            'per_page'   => 'nullable|integer|min:1|max:200',
            'page'       => 'nullable|integer|min:1',
        ];
    }
}
