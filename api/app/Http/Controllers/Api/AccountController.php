<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends BaseApiController
{
    /**
     * 获取当前用户个人信息
     *
     * 返回登录买家的个人信息，包含 ID、姓名、邮筱、手机号、订阅状态及注册时间。
     * 需要 Sanctum 认证。
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id'         => $user->id,
            'firstname'  => $user->firstname,
            'lastname'   => $user->lastname,
            'full_name'  => $user->full_name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'newsletter' => $user->newsletter,
            'created_at' => $user->created_at?->toDateTimeString(),
        ]);
    }

    /**
     * 更新个人信息
     *
     * 更新登录买家的姓名、邮筱、手机号、订阅核答等基本信息。
     * 邮筱唯一性验证已在 UpdateProfileRequest 中处理。
     * 返回更新后的用户基本信息字段。
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // 如果邮箱已被其他用户使用，验证在 Request 中完成
        $user->update($data);

        return $this->success([
            'id'        => $user->id,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'full_name' => $user->full_name,
            'email'     => $user->email,
            'phone'     => $user->phone,
        ], '个人信息已更新');
    }

    /**
     * 修改登录密码
     *
     * 验证当前密码后，将密码更新为新密码。
     * 操作成功后处消该用户的所有 Sanctum Token，强制重新登录。
     * 请求体参数：`current_password`（必填）、`new_password`（必填，需 confirmed）。
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return $this->error(40001, '当前密码不正确');
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        // 吊销所有旧 Token，强制重新登录
        $user->tokens()->delete();

        return $this->success(null, '密码已修改，请重新登录');
    }

    /**
     * 查看订单历史
     *
     * 返回当前登录买家的订单历史，按创建时间倒序分页。
     * 每条订单包含商品明细和收货地址信息。
     * 可选传入 `per_page` 控制每页条数（最多 50，默认 15）。
     */
    public function orderHistory(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;
        $perPage = min((int)$request->input('per_page', 15), 50);

        $paginator = Order::where('customer_id', $customerId)
            ->with(['items', 'shippingAddress'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->paginate($paginator);
    }
}
