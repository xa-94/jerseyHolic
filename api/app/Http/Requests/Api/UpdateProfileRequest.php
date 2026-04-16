<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'firstname' => 'required|string|max:32',
            'lastname'  => 'required|string|max:32',
            'email'     => [
                'required',
                'email',
                Rule::unique('jh_customers', 'email')->ignore($userId),
            ],
            'phone'     => 'nullable|string|max:32',
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => '名字不能为空',
            'firstname.max'      => '名字最多32个字符',
            'lastname.required'  => '姓氏不能为空',
            'lastname.max'       => '姓氏最多32个字符',
            'email.required'     => '邮箱不能为空',
            'email.email'        => '邮箱格式不正确',
            'email.unique'       => '该邮箱已被使用',
            'phone.max'          => '手机号最多32个字符',
        ];
    }
}
