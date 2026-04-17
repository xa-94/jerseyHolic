<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 站点商品配置更新请求验证
 *
 * PUT /api/v1/merchant/stores/{storeId}/product-config
 */
class UpdateStoreProductConfigRequest extends FormRequest
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
            'price_override_enabled'     => 'sometimes|boolean',
            'price_override_strategy'    => 'required_if:price_override_enabled,true|nullable|in:multiplier,fixed,markup',
            'price_override_value'       => 'required_if:price_override_enabled,true|nullable|numeric|min:0',
            'safe_name_override_enabled' => 'sometimes|boolean',
            'custom_placeholder_image'   => 'nullable|url|max:1000',
            'display_currency'           => 'nullable|string|size:3',
            'auto_translate'             => 'sometimes|boolean',
            'default_language'           => 'nullable|string|in:en,zh,de,fr,es,it,pt,nl,pl,sv,da,ar,tr,el,ja,ko',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_override_strategy.required_if' => '启用价格覆盖时必须选择策略',
            'price_override_strategy.in'          => '价格覆盖策略无效（可选：multiplier, fixed, markup）',
            'price_override_value.required_if'    => '启用价格覆盖时必须设置数值',
            'price_override_value.numeric'        => '价格覆盖数值必须为数字',
            'price_override_value.min'            => '价格覆盖数值不能小于 0',
            'custom_placeholder_image.url'        => '占位图必须为有效 URL',
            'display_currency.size'               => '展示币种必须为 3 位字符（如 USD）',
            'default_language.in'                 => '默认语言不在支持的语言列表中',
        ];
    }
}
