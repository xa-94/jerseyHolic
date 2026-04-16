<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'parent_id'   => 'nullable|integer|min:0',
            'sort_order'  => 'nullable|integer|min:0',
            'status'      => 'nullable|boolean',
            'image'       => 'nullable|string|max:500',
            'descriptions' => $isUpdate ? 'nullable|array' : 'required|array',
        ];

        if ($isUpdate) {
            $rules['descriptions.*.name'] = 'nullable|string|max:255';
        } else {
            // 创建时至少需要 en 语言的 name
            $rules['descriptions.en']        = 'required|array';
            $rules['descriptions.en.name']   = 'required|string|max:255';
            $rules['descriptions.*.name']    = 'nullable|string|max:255';
        }

        $rules = array_merge($rules, [
            'descriptions.*.description'      => 'nullable|string',
            'descriptions.*.meta_title'       => 'nullable|string|max:255',
            'descriptions.*.meta_description' => 'nullable|string|max:500',
            'descriptions.*.meta_keywords'    => 'nullable|string|max:500',
            'descriptions.*.slug'             => 'nullable|string|max:255',
        ]);

        return $rules;
    }

    public function messages(): array
    {
        return [
            'descriptions.required'          => '分类描述不能为空',
            'descriptions.en.required'       => '必须提供英文（en）分类描述',
            'descriptions.en.name.required'  => '英文分类名称不能为空',
            'descriptions.*.name.max'        => '分类名称最多255个字符',
            'parent_id.integer'              => '父分类ID必须为整数',
            'sort_order.integer'             => '排序值必须为整数',
            'sort_order.min'                 => '排序值不能为负数',
            'image.max'                      => '图片路径最多500个字符',
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
