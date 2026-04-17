<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Central\CategorySafeName;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 品类安全映射名称表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：safe_name_en 必填
 *  - PUT / PATCH（update）：所有字段均为 sometimes
 */
class CategorySafeNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return $isUpdate ? $this->updateRules() : $this->createRules();
    }

    /**
     * 创建规则（POST）
     */
    private function createRules(): array
    {
        $localeRules = $this->buildLocaleRules(isUpdate: false);

        return array_merge([
            'category_l1_id' => 'nullable|integer|exists:central.product_categories_l1,id',
            'category_l2_id' => 'nullable|integer|exists:central.product_categories_l2,id',
            'sku_prefix'     => 'nullable|string|max:50',
            'store_id'       => 'nullable|integer|exists:central.stores,id',
            'weight'         => 'sometimes|integer|min:1|max:100',
            'status'         => 'sometimes|integer|in:0,1',
        ], $localeRules);
    }

    /**
     * 更新规则（PUT / PATCH）
     */
    private function updateRules(): array
    {
        $localeRules = $this->buildLocaleRules(isUpdate: true);

        return array_merge([
            'category_l1_id' => 'nullable|integer|exists:central.product_categories_l1,id',
            'category_l2_id' => 'nullable|integer|exists:central.product_categories_l2,id',
            'sku_prefix'     => 'nullable|string|max:50',
            'store_id'       => 'nullable|integer|exists:central.stores,id',
            'weight'         => 'sometimes|integer|min:1|max:100',
            'status'         => 'sometimes|integer|in:0,1',
        ], $localeRules);
    }

    /**
     * 构建 16 语言验证规则
     */
    private function buildLocaleRules(bool $isUpdate): array
    {
        $rules = [];

        foreach (CategorySafeName::SUPPORTED_LOCALES as $locale) {
            $field = "safe_name_{$locale}";
            if ($locale === 'en') {
                $rules[$field] = $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255';
            } else {
                $rules[$field] = 'nullable|string|max:255';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'safe_name_en.required'      => '英文安全名称不能为空',
            'safe_name_en.max'           => '英文安全名称最多255个字符',
            'category_l1_id.exists'      => 'L1 品类不存在',
            'category_l2_id.exists'      => 'L2 品类不存在',
            'store_id.exists'            => '站点不存在',
            'sku_prefix.max'             => 'SKU 前缀最多50个字符',
            'weight.min'                 => '权重最小为1',
            'weight.max'                 => '权重最大为100',
            'status.in'                  => '状态值非法，允许值：0（禁用）、1（启用）',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'code'    => 42200,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
