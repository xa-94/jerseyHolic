<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Central\SensitiveBrand;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * 敏感品牌表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：brand_name / risk_level 必填
 *  - PUT / PATCH（update）：所有字段可选
 */
class SensitiveBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        if ($isUpdate) {
            return $this->updateRules();
        }

        return $this->createRules();
    }

    /**
     * 创建规则（POST）
     */
    private function createRules(): array
    {
        return [
            'brand_name'     => [
                'required',
                'string',
                'max:255',
                Rule::unique('sensitive_brands')->where(function ($query) {
                    return $query->where('category_l1_id', $this->input('category_l1_id'));
                }),
            ],
            'brand_aliases'  => 'nullable|array',
            'brand_aliases.*' => 'string|max:255',
            'category_l1_id' => 'nullable|integer',
            'risk_level'     => ['required', 'string', Rule::in(SensitiveBrand::RISK_LEVELS)],
            'reason'         => 'nullable|string|max:500',
            'status'         => 'nullable|integer|in:0,1',
        ];
    }

    /**
     * 更新规则（PUT / PATCH）
     */
    private function updateRules(): array
    {
        $brandId = (int) $this->route('id');

        return [
            'brand_name'     => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('sensitive_brands')->where(function ($query) {
                    return $query->where('category_l1_id', $this->input('category_l1_id'));
                })->ignore($brandId),
            ],
            'brand_aliases'  => 'nullable|array',
            'brand_aliases.*' => 'string|max:255',
            'category_l1_id' => 'nullable|integer',
            'risk_level'     => ['sometimes', 'string', Rule::in(SensitiveBrand::RISK_LEVELS)],
            'reason'         => 'nullable|string|max:500',
            'status'         => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'brand_name.required' => '品牌名称不能为空',
            'brand_name.max'      => '品牌名称最多255个字符',
            'brand_name.unique'   => '该品牌在同一品类下已存在',
            'risk_level.required' => '风险等级不能为空',
            'risk_level.in'       => '风险等级必须为: ' . implode(', ', SensitiveBrand::RISK_LEVELS),
            'reason.max'          => '标记原因最多500个字符',
            'status.in'           => '状态必须为 0 或 1',
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
