<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 更新主商品请求验证
 *
 * PUT /api/v1/merchant/products/{id}
 */
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku'                         => 'sometimes|string|max:100',
            'name'                        => 'sometimes|string|max:255',
            'description'                 => 'nullable|string|max:5000',
            'category_l1_id'              => 'nullable|integer|min:1',
            'category_l2_id'              => 'nullable|integer|min:1',
            'is_sensitive'                => 'nullable|boolean',
            'base_price'                  => 'sometimes|numeric|min:0',
            'currency'                    => 'sometimes|string|size:3',
            'images'                      => 'nullable|array',
            'images.*'                    => 'string|url|max:1000',
            'attributes'                  => 'nullable|array',
            'variants'                    => 'nullable|array',
            'weight'                      => 'nullable|numeric|min:0',
            'dimensions'                  => 'nullable|array',
            'status'                      => 'nullable|integer|in:0,1,2',
            'translations'                => 'nullable|array',
            'translations.*.locale'       => 'required|string|max:10',
            'translations.*.name'         => 'required|string|max:255',
            'translations.*.description'  => 'nullable|string|max:5000',
            'translations.*.meta_title'   => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.max'                         => 'SKU 长度不能超过 100 个字符',
            'name.max'                        => '商品名称长度不能超过 255 个字符',
            'base_price.numeric'              => '基础价格必须为数字',
            'base_price.min'                  => '基础价格不能小于 0',
            'currency.size'                   => '币种必须为 3 位字符（如 USD）',
            'status.in'                       => '状态值无效（0=禁用, 1=启用, 2=草稿）',
            'translations.*.locale.required'  => '翻译语言代码不能为空',
            'translations.*.name.required'    => '翻译名称不能为空',
        ];
    }
}
