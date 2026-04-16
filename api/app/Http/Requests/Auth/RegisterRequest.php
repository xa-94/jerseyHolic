<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email' => 'required|email|unique:jh_customers,email',
            'password' => 'required|string|min:6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => '姓名不能为空',
            'email.required' => '邮箱不能为空',
            'email.unique' => '该邮箱已注册',
            'password.min' => '密码至少6位',
            'password.confirmed' => '两次密码不一致',
        ];
    }
}
