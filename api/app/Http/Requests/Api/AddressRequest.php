<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname'  => 'required|string|max:64',
            'lastname'   => 'required|string|max:64',
            'company'    => 'nullable|string|max:128',
            'address_1'  => 'required|string|max:256',
            'address_2'  => 'nullable|string|max:256',
            'city'       => 'required|string|max:64',
            'postcode'   => 'required|string|max:10',
            'country_id' => 'required|integer|exists:jh_countries,id',
            'zone_id'    => 'nullable|integer',
            'phone'      => 'required|string|max:32',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required'  => '名字不能为空',
            'lastname.required'   => '姓氏不能为空',
            'address_1.required'  => '地址不能为空',
            'city.required'       => '城市不能为空',
            'postcode.required'   => '邮编不能为空',
            'postcode.max'        => '邮编最多10位',
            'country_id.required' => '请选择国家',
            'country_id.integer'  => '国家ID格式不正确',
            'country_id.exists'   => '所选国家不存在',
            'phone.required'      => '手机号不能为空',
        ];
    }
}
