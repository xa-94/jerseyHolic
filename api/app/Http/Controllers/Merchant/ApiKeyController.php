<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantApiKey;
use App\Services\MerchantKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 商户 API 密钥管理控制器
 *
 * 所有路由均受 auth:merchant 中间件保护。
 * 当前认证用户类型为 MerchantUser，通过 merchant() 关联获取所属 Merchant 实体。
 */
class ApiKeyController extends Controller
{
    public function __construct(
        protected MerchantKeyService $keyService
    ) {}

    /* ----------------------------------------------------------------
     |  GET /api/v1/merchant/api-keys
     |  列出当前商户所有密钥（不含私钥与 download_token）
     | ---------------------------------------------------------------- */

    public function index(Request $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        $keys = $this->keyService->listKeys($merchant)->map(function (MerchantApiKey $key) {
            return $this->formatKey($key);
        });

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => ['list' => $keys],
        ]);
    }

    /* ----------------------------------------------------------------
     |  POST /api/v1/merchant/api-keys
     |  生成新 RSA-4096 密钥对，返回 download_token（仅出现一次）
     | ---------------------------------------------------------------- */

    public function store(Request $request): JsonResponse
    {
        $merchant = $this->getMerchant($request);

        // 可选：指定关联的 store_id
        $storeId = $request->input('store_id');
        $store   = null;

        if ($storeId) {
            $store = $merchant->stores()->find($storeId);
            if (!$store) {
                return response()->json([
                    'code'    => 422,
                    'message' => 'Store not found or does not belong to this merchant.',
                ], 422);
            }
        }

        try {
            $result = $this->keyService->generateKeyPair($merchant, $store);
        } catch (RuntimeException $e) {
            return response()->json([
                'code'    => 500,
                'message' => 'Key generation failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'Key pair generated successfully. Download the private key immediately using the download_token — it will not be shown again.',
            'data'    => $result,
        ], 201);
    }

    /* ----------------------------------------------------------------
     |  GET /api/v1/merchant/api-keys/{keyId}
     |  密钥详情（不含私钥）
     | ---------------------------------------------------------------- */

    public function show(Request $request, string $keyId): JsonResponse
    {
        $merchant = $this->getMerchant($request);
        $key      = $this->findKeyForMerchant($keyId, $merchant);

        if (!$key) {
            return response()->json([
                'code'    => 404,
                'message' => 'Key not found.',
            ], 404);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => $this->formatKey($key),
        ]);
    }

    /* ----------------------------------------------------------------
     |  POST /api/v1/merchant/api-keys/{keyId}/rotate
     |  密钥轮换：旧密钥进入 24h Grace Period，新密钥即时激活
     | ---------------------------------------------------------------- */

    public function rotate(Request $request, string $keyId): JsonResponse
    {
        $merchant = $this->getMerchant($request);
        $key      = $this->findKeyForMerchant($keyId, $merchant);

        if (!$key) {
            return response()->json([
                'code'    => 404,
                'message' => 'Key not found.',
            ], 404);
        }

        if ($key->isRevoked()) {
            return response()->json([
                'code'    => 422,
                'message' => 'Revoked keys cannot be rotated.',
            ], 422);
        }

        if ($key->isExpired()) {
            return response()->json([
                'code'    => 422,
                'message' => 'Expired keys cannot be rotated.',
            ], 422);
        }

        try {
            $result = $this->keyService->rotateKey($key);
        } catch (RuntimeException $e) {
            return response()->json([
                'code'    => 500,
                'message' => 'Key rotation failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'Key rotated successfully. The old key will expire in 24 hours. Download the new private key immediately.',
            'data'    => $result,
        ], 201);
    }

    /* ----------------------------------------------------------------
     |  DELETE /api/v1/merchant/api-keys/{keyId}
     |  吊销密钥
     | ---------------------------------------------------------------- */

    public function revoke(Request $request, string $keyId): JsonResponse
    {
        $merchant = $this->getMerchant($request);
        $key      = $this->findKeyForMerchant($keyId, $merchant);

        if (!$key) {
            return response()->json([
                'code'    => 404,
                'message' => 'Key not found.',
            ], 404);
        }

        if ($key->isRevoked()) {
            return response()->json([
                'code'    => 422,
                'message' => 'Key is already revoked.',
            ], 422);
        }

        $reason = $request->input('reason', 'Revoked by merchant.');

        $revokedKey = $this->keyService->revokeKey($key, $reason);

        return response()->json([
            'code'    => 0,
            'message' => 'Key revoked successfully.',
            'data'    => $this->formatKey($revokedKey),
        ]);
    }

    /* ----------------------------------------------------------------
     |  POST /api/v1/merchant/api-keys/download
     |  凭 download_token 一次性下载加密私钥
     | ---------------------------------------------------------------- */

    public function download(Request $request): JsonResponse
    {
        $request->validate([
            'download_token' => ['required', 'string', 'size:64'],
        ]);

        $downloadToken = $request->input('download_token');

        try {
            $payload = $this->keyService->validateDownload($downloadToken);
        } catch (RuntimeException $e) {
            $httpCode = $e->getCode() ?: 400;
            // 限制只返回合法 HTTP 状态码
            if (!in_array($httpCode, [400, 404, 410, 500], true)) {
                $httpCode = 400;
            }

            return response()->json([
                'code'    => $httpCode,
                'message' => $e->getMessage(),
            ], $httpCode);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'Private key downloaded successfully. This token is now invalidated and cannot be used again.',
            'data'    => $payload,
        ]);
    }

    /* ----------------------------------------------------------------
     |  私有辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 从 auth:merchant guard 获取当前 MerchantUser，再获取所属 Merchant
     */
    private function getMerchant(Request $request): Merchant
    {
        /** @var \App\Models\Central\MerchantUser $user */
        $user = $request->user('merchant');

        return $user->merchant;
    }

    /**
     * 查找属于指定商户的密钥，防止越权访问
     */
    private function findKeyForMerchant(string $keyId, Merchant $merchant): ?MerchantApiKey
    {
        return MerchantApiKey::where('key_id', $keyId)
            ->where('merchant_id', $merchant->id)
            ->first();
    }

    /**
     * 格式化密钥输出（隐藏私钥与敏感 token）
     */
    private function formatKey(MerchantApiKey $key): array
    {
        return [
            'key_id'        => $key->key_id,
            'algorithm'     => $key->algorithm,
            'key_size'      => $key->key_size,
            'status'        => $key->status,
            'public_key'    => $key->public_key,
            'store_id'      => $key->store_id,
            'activated_at'  => $key->activated_at?->toIso8601String(),
            'expires_at'    => $key->expires_at?->toIso8601String(),
            'revoked_at'    => $key->revoked_at?->toIso8601String(),
            'revoke_reason' => $key->revoke_reason,
            'downloaded_at' => $key->downloaded_at?->toIso8601String(),
            'is_downloaded' => $key->isDownloaded(),
            'created_at'    => $key->created_at->toIso8601String(),
        ];
    }
}
