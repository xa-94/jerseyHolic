<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 敏感品牌检测表单验证（Admin 端 — check 接口）
 *
 * POST /api/v1/admin/sensitive-brands/check
 */
class SensitiveBrandCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'            => 'required|string|max:100',
            'brand'          => 'nullable|string|max:255',
            'category_l1_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'SKU 不能为空',
            'sku.max'      => 'SKU 最多100个字符',
            'brand.max'    => '品牌名最多255个字符',
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
