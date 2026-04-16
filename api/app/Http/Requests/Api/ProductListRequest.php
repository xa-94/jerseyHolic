<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 买家端商品列表查询参数
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
            'keyword'     => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|min:1',
            'price_min'   => 'nullable|numeric|min:0',
            'price_max'   => 'nullable|numeric|min:0',
            'attributes'  => 'nullable|array',
            'sort'        => 'nullable|string|in:price_asc,price_desc,newest,bestseller',
            'featured'    => 'nullable|boolean',
            'per_page'    => 'nullable|integer|min:1|max:100',
            'page'        => 'nullable|integer|min:1',
        ];
    }
}
