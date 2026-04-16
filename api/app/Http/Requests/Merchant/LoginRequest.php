<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * 所有请求均可提交（认证在 Controller 层完成）。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 验证规则
     *
     * login 字段支持 email 或 username 两种格式。
     */
    public function rules(): array
    {
        return [
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'    => '请输入邮箱或用户名',
            'password.required' => '请输入密码',
        ];
    }
}
