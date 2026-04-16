<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('id') ?? $this->route('product');

        return [
            // 核心字段（全部可选，支持部分更新）
            'sku'                 => ['sometimes', 'string', 'max:64', Rule::unique('jh_products', 'sku')->ignore($productId)],
            'model'               => 'sometimes|nullable|string|max:64',
            'price'               => 'sometimes|numeric|min:0',
            'cost_price'          => 'sometimes|nullable|numeric|min:0',
            'special_price'       => 'sometimes|nullable|numeric|min:0',
            'special_start_at'    => 'sometimes|nullable|date',
            'special_end_at'      => 'sometimes|nullable|date|after_or_equal:special_start_at',
            'quantity'            => 'sometimes|integer|min:0',
            'stock_status'        => 'sometimes|nullable|integer|in:0,1',
            'subtract_stock'      => 'sometimes|nullable|integer|in:0,1',
            'weight'              => 'sometimes|nullable|numeric|min:0',
            'length'              => 'sometimes|nullable|numeric|min:0',
            'width'               => 'sometimes|nullable|numeric|min:0',
            'height'              => 'sometimes|nullable|numeric|min:0',
            'image'               => 'sometimes|nullable|string|max:500',
            'minimum'             => 'sometimes|nullable|integer|min:1',
            'sort_order'          => 'sometimes|nullable|integer|min:0',
            'status'              => 'sometimes|integer|in:0,1,2',
            'is_featured'         => 'sometimes|nullable|boolean',
            'requires_shipping'   => 'sometimes|nullable|boolean',
            'upc'                 => 'sometimes|nullable|string|max:64',
            'ean'                 => 'sometimes|nullable|string|max:64',
            'isbn'                => 'sometimes|nullable|string|max:64',
            'mpn'                 => 'sometimes|nullable|string|max:64',

            // 分类
            'category_ids'        => 'sometimes|nullable|array',
            'category_ids.*'      => 'integer|exists:jh_categories,id',

            // 多语言描述
            'descriptions'              => 'sometimes|nullable|array',
            'descriptions.*.locale'     => 'required_with:descriptions|string|max:10',
            'descriptions.*.name'       => 'required_with:descriptions|string|max:255',
            'descriptions.*.description'       => 'nullable|string',
            'descriptions.*.short_description' => 'nullable|string|max:500',
            'descriptions.*.meta_title'        => 'nullable|string|max:255',
            'descriptions.*.meta_description'  => 'nullable|string|max:500',
            'descriptions.*.meta_keywords'     => 'nullable|string|max:500',
            'descriptions.*.slug'              => 'nullable|string|max:255',
            'descriptions.*.tag'               => 'nullable|string|max:255',

            // 图片
            'images'              => 'sometimes|nullable|array',
            'images.*.image'      => 'required_with:images|string|max:500',
            'images.*.is_main'    => 'nullable|boolean',
            'images.*.sort_order' => 'nullable|integer|min:0',

            // SKU 变体
            'skus'                 => 'sometimes|nullable|array',
            'skus.*.sku'           => 'required_with:skus|string|max:100',
            'skus.*.price'         => 'required_with:skus|numeric|min:0',
            'skus.*.cost_price'    => 'nullable|numeric|min:0',
            'skus.*.quantity'      => 'required_with:skus|integer|min:0',
            'skus.*.weight'        => 'nullable|numeric|min:0',
            'skus.*.image'         => 'nullable|string|max:500',
            'skus.*.option_values' => 'nullable|array',
            'skus.*.status'        => 'nullable|integer|in:0,1',
            'skus.*.sort_order'    => 'nullable|integer|min:0',

            // 属性值
            'attributes'                  => 'sometimes|nullable|array',
            'attributes.*.attribute_id'   => 'required_with:attributes|integer|exists:jh_product_attributes,id',
            'attributes.*.value'          => 'required_with:attributes|string|max:255',
            'attributes.*.locale'         => 'nullable|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique'                    => 'SKU 编码已被其他商品使用',
            'price.min'                     => '商品价格不能小于 0',
            'quantity.min'                  => '库存数量不能小于 0',
            'status.in'                     => '商品状态值不合法（0:下架 1:上架 2:草稿）',
            'special_end_at.after_or_equal' => '特价结束时间不能早于开始时间',
        ];
    }
}
