<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 核心字段
            'sku'                 => 'required|string|max:64|unique:jh_products,sku',
            'model'               => 'nullable|string|max:64',
            'price'               => 'required|numeric|min:0',
            'cost_price'          => 'nullable|numeric|min:0',
            'special_price'       => 'nullable|numeric|min:0',
            'special_start_at'    => 'nullable|date',
            'special_end_at'      => 'nullable|date|after_or_equal:special_start_at',
            'quantity'            => 'required|integer|min:0',
            'stock_status'        => 'nullable|integer|in:0,1',
            'subtract_stock'      => 'nullable|integer|in:0,1',
            'weight'              => 'nullable|numeric|min:0',
            'length'              => 'nullable|numeric|min:0',
            'width'               => 'nullable|numeric|min:0',
            'height'              => 'nullable|numeric|min:0',
            'image'               => 'nullable|string|max:500',
            'minimum'             => 'nullable|integer|min:1',
            'sort_order'          => 'nullable|integer|min:0',
            'status'              => 'nullable|integer|in:0,1,2',
            'is_featured'         => 'nullable|boolean',
            'requires_shipping'   => 'nullable|boolean',
            'merchant_id'         => 'nullable|integer|exists:jh_merchants,id',
            'upc'                 => 'nullable|string|max:64',
            'ean'                 => 'nullable|string|max:64',
            'isbn'                => 'nullable|string|max:64',
            'mpn'                 => 'nullable|string|max:64',

            // 分类
            'category_ids'        => 'nullable|array',
            'category_ids.*'      => 'integer|exists:jh_categories,id',

            // 多语言描述
            'descriptions'              => 'nullable|array',
            'descriptions.*.locale'     => 'required_with:descriptions|string|size:5',
            'descriptions.*.name'       => 'required_with:descriptions|string|max:255',
            'descriptions.*.description'       => 'nullable|string',
            'descriptions.*.short_description' => 'nullable|string|max:500',
            'descriptions.*.meta_title'        => 'nullable|string|max:255',
            'descriptions.*.meta_description'  => 'nullable|string|max:500',
            'descriptions.*.meta_keywords'     => 'nullable|string|max:500',
            'descriptions.*.slug'              => 'nullable|string|max:255',
            'descriptions.*.tag'               => 'nullable|string|max:255',

            // 图片
            'images'              => 'nullable|array',
            'images.*.image'      => 'required_with:images|string|max:500',
            'images.*.is_main'    => 'nullable|boolean',
            'images.*.sort_order' => 'nullable|integer|min:0',

            // SKU 变体
            'skus'                     => 'nullable|array',
            'skus.*.sku'               => 'required_with:skus|string|max:100',
            'skus.*.price'             => 'required_with:skus|numeric|min:0',
            'skus.*.cost_price'        => 'nullable|numeric|min:0',
            'skus.*.quantity'          => 'required_with:skus|integer|min:0',
            'skus.*.weight'            => 'nullable|numeric|min:0',
            'skus.*.image'             => 'nullable|string|max:500',
            'skus.*.option_values'     => 'nullable|array',
            'skus.*.status'            => 'nullable|integer|in:0,1',
            'skus.*.sort_order'        => 'nullable|integer|min:0',

            // 属性值
            'attributes'                  => 'nullable|array',
            'attributes.*.attribute_id'   => 'required_with:attributes|integer|exists:jh_product_attributes,id',
            'attributes.*.value'          => 'required_with:attributes|string|max:255',
            'attributes.*.locale'         => 'nullable|string|size:5',
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required'           => 'SKU 编码不能为空',
            'sku.unique'             => 'SKU 编码已存在',
            'sku.max'                => 'SKU 编码不能超过 64 个字符',
            'price.required'         => '商品价格不能为空',
            'price.numeric'          => '商品价格必须为数字',
            'price.min'              => '商品价格不能小于 0',
            'quantity.required'      => '库存数量不能为空',
            'quantity.integer'       => '库存数量必须为整数',
            'quantity.min'           => '库存数量不能小于 0',
            'status.in'              => '商品状态值不合法（0:下架 1:上架 2:草稿）',
            'special_end_at.after_or_equal' => '特价结束时间不能早于开始时间',
            'descriptions.*.locale.required_with' => '语言描述的 locale 字段不能为空',
            'descriptions.*.name.required_with'   => '语言描述的名称不能为空',
            'images.*.image.required_with'         => '图片路径不能为空',
            'skus.*.sku.required_with'             => 'SKU 变体编码不能为空',
            'skus.*.price.required_with'           => 'SKU 变体价格不能为空',
            'skus.*.quantity.required_with'        => 'SKU 变体库存不能为空',
        ];
    }
}
