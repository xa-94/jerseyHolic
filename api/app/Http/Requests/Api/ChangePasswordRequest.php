<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'  => '当前密码不能为空',
            'new_password.required'      => '新密码不能为空',
            'new_password.min'           => '新密码至少6位',
            'new_password.confirmed'     => '两次输入的密码不一致',
        ];
    }
}
