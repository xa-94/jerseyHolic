<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 商户公开注册 Controller
 *
 * 路由：POST /api/v1/merchant/register（无需认证）
 * 供新商户自助注册，初始状态为 pending，需平台管理员审核后激活。
 */
class RegisterController extends BaseController
{
    public function __construct(
        private readonly MerchantService $merchantService
    ) {}

    /**
     * 商户注册
     *
     * POST /api/v1/merchant/register
     *
     * Body:
     *  - merchant_name  string  商户名称（必填，最多 100 字符）
     *  - email          string  邮箱（必填，唯一）
     *  - password       string  密码（必填，至少 8 位）
     *  - contact_name   string  联系人姓名（必填）
     *  - phone          string? 手机号（可选）
     *
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_name' => 'required|string|max:100',
            'email'         => 'required|email|max:255',
            'password'      => 'required|string|min:8',
            'contact_name'  => 'required|string|max:100',
            'phone'         => 'nullable|string|max:30',
        ]);

        try {
            $merchant = $this->merchantService->register($request->only([
                'merchant_name',
                'email',
                'password',
                'contact_name',
                'phone',
            ]));
        } catch (ValidationException $e) {
            return response()->json([
                'code'    => 42200,
                'message' => $e->validator->errors()->first(),
                'data'    => $e->errors(),
            ], 422);
        }

        return response()->json([
            'code'    => 0,
            'message' => '注册成功，请等待平台审核。',
            'data'    => [
                'id'            => $merchant->id,
                'merchant_name' => $merchant->merchant_name,
                'email'         => $merchant->email,
                'status'        => MerchantService::STATUS_LABEL[$merchant->status] ?? 'pending',
            ],
        ]);
    }
}
