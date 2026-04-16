<?php

namespace App\Services;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantApiKey;
use App\Models\Central\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 商户 RSA 密钥管理服务
 *
 * 负责 RSA-4096 密钥对的生成、轮换、吊销和安全下载。
 * 私钥永不存储于数据库，仅在生成时临时存于内存，
 * 封装为 AES-256-GCM 加密的 PKCS#8 格式供一次性下载。
 */
class MerchantKeyService
{
    /**
     * 获取用于加密私钥的 AES 密钥（32 字节）
     */
    private function getEncryptionKey(): string
    {
        $raw = env('MERCHANT_KEY_ENCRYPTION_KEY') ?: config('app.key');

        // Laravel app.key 格式为 "base64:xxx"，需要解码
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7));
        } else {
            $decoded = $raw;
        }

        // 确保密钥为 32 字节（AES-256）
        return substr(hash('sha256', $decoded, true), 0, 32);
    }

    /**
     * 生成 RSA-4096 密钥对
     *
     * 公钥存入数据库，私钥用 AES-256-GCM 加密后作为一次性下载载荷。
     *
     * @param  Merchant    $merchant  所属商户
     * @param  Store|null  $store     可选的关联店铺
     * @return array{key_id: string, download_token: string, download_url: string}
     * @throws RuntimeException 密钥生成失败时抛出
     */
    public function generateKeyPair(Merchant $merchant, ?Store $store = null): array
    {
        // 1. 生成 RSA-4096 密钥对
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $keyResource = openssl_pkey_new($config);
        if ($keyResource === false) {
            throw new RuntimeException('RSA key generation failed: ' . openssl_error_string());
        }

        // 2. 提取公钥（PEM）
        $keyDetails = openssl_pkey_get_details($keyResource);
        if ($keyDetails === false) {
            throw new RuntimeException('Failed to get key details: ' . openssl_error_string());
        }
        $publicKeyPem = $keyDetails['key'];

        // 3. 导出私钥（PEM PKCS#1）
        $privateKeyPem = '';
        if (!openssl_pkey_export($keyResource, $privateKeyPem)) {
            throw new RuntimeException('Failed to export private key: ' . openssl_error_string());
        }

        // 4. AES-256-GCM 加密私钥
        $encryptedPayload = $this->encryptPrivateKey($privateKeyPem);

        // 5. 生成 key_id
        $keyId = 'mk_' . Str::random(24);

        // 6. 生成 download_token（SHA-256，仅一次有效，24h 过期）
        $downloadToken = hash('sha256', Str::random(64));

        // 7. 持久化 MerchantApiKey（公钥 + download_token）
        $apiKey = MerchantApiKey::create([
            'merchant_id'               => $merchant->id,
            'store_id'                  => $store?->id,
            'key_id'                    => $keyId,
            'public_key'                => $publicKeyPem,
            'algorithm'                 => 'RSA-SHA256',
            'key_size'                  => 4096,
            'status'                    => 'active',
            'activated_at'              => now(),
            'download_token'            => $downloadToken,
            'download_token_expires_at' => now()->addHours(24),
        ]);

        // 8. 将加密的私钥载荷临时缓存到 session/cache，供 download 接口取用
        //    使用 download_token 作为 cache key，TTL 略大于 token 有效期
        cache()->put(
            'merchant_private_key:' . $downloadToken,
            $encryptedPayload,
            now()->addHours(25)
        );

        // 清空内存中的私钥明文
        unset($privateKeyPem);

        return [
            'key_id'         => $keyId,
            'download_token' => $downloadToken,
            'download_url'   => url('/api/v1/merchant/api-keys/download'),
            'expires_in'     => 86400, // 24h（秒）
        ];
    }

    /**
     * 密钥轮换
     *
     * 将旧密钥置为 rotating 状态（24h Grace Period），生成新密钥对。
     *
     * @param  MerchantApiKey $oldKey 待轮换的旧密钥
     * @return array 新密钥信息
     */
    public function rotateKey(MerchantApiKey $oldKey): array
    {
        // 旧密钥进入 rotating 状态，24h 后自动过期
        $oldKey->update([
            'status'     => 'rotating',
            'expires_at' => now()->addHours(24),
        ]);

        // 获取商户并生成新密钥对
        $merchant = $oldKey->merchant;
        $store    = $oldKey->store_id ? $oldKey->store : null;

        return $this->generateKeyPair($merchant, $store);
    }

    /**
     * 吊销密钥
     *
     * @param  MerchantApiKey $key     待吊销的密钥
     * @param  string         $reason  吊销原因
     */
    public function revokeKey(MerchantApiKey $key, string $reason): MerchantApiKey
    {
        $key->update([
            'status'        => 'revoked',
            'revoked_at'    => now(),
            'revoke_reason' => $reason,
        ]);

        return $key->refresh();
    }

    /**
     * 列出商户所有密钥
     */
    public function listKeys(Merchant $merchant): Collection
    {
        return $merchant->apiKeys()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * 通过 key_id 查找密钥
     */
    public function getKey(string $keyId): ?MerchantApiKey
    {
        return MerchantApiKey::where('key_id', $keyId)->first();
    }

    /**
     * 验证 download_token 并返回加密的私钥载荷
     *
     * 规则：
     * - token 存在且未过期（24h）
     * - downloaded_at 为 null（一次性下载）
     * 验证通过后设置 downloaded_at，并清除 download_token。
     *
     * @param  string $downloadToken
     * @return array{key_id: string, encrypted_private_key: string, algorithm: string, key_size: int}
     * @throws RuntimeException 验证失败时抛出
     */
    public function validateDownload(string $downloadToken): array
    {
        /** @var MerchantApiKey|null $apiKey */
        $apiKey = MerchantApiKey::withoutGlobalScopes()
            ->where('download_token', $downloadToken)
            ->first();

        if (!$apiKey) {
            throw new RuntimeException('Invalid download token.', 404);
        }

        if ($apiKey->downloaded_at !== null) {
            throw new RuntimeException('Private key has already been downloaded.', 410);
        }

        if ($apiKey->download_token_expires_at === null || $apiKey->download_token_expires_at->isPast()) {
            throw new RuntimeException('Download token has expired.', 410);
        }

        // 从 cache 取出加密的私钥载荷
        $encryptedPayload = cache()->get('merchant_private_key:' . $downloadToken);
        if (!$encryptedPayload) {
            throw new RuntimeException('Encrypted private key payload not found or expired.', 410);
        }

        // 标记已下载，清除 token
        $apiKey->update([
            'downloaded_at'  => now(),
            'download_token' => null,
        ]);

        // 清除 cache
        cache()->forget('merchant_private_key:' . $downloadToken);

        return [
            'key_id'                => $apiKey->key_id,
            'encrypted_private_key' => $encryptedPayload,
            'algorithm'             => $apiKey->algorithm,
            'key_size'              => $apiKey->key_size,
            'format'                => 'AES-256-GCM encrypted PKCS#8 PEM',
            'instructions'          => 'Decrypt using AES-256-GCM with your MERCHANT_KEY_ENCRYPTION_KEY. IV and tag are base64-encoded in the payload.',
        ];
    }

    /**
     * 清理过期的 rotating 密钥（标记为 expired）
     *
     * 由 Scheduler 或 Artisan 命令定期调用。
     *
     * @return int 处理的记录数
     */
    public function cleanupExpiredKeys(): int
    {
        return MerchantApiKey::where('status', 'rotating')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /* ----------------------------------------------------------------
     |  私有辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 使用 AES-256-GCM 加密私钥 PEM，返回 base64 编码的 JSON 载荷
     *
     * 载荷格式：
     * {
     *   "iv":         "<base64>",
     *   "tag":        "<base64>",
     *   "ciphertext": "<base64>"
     * }
     */
    private function encryptPrivateKey(string $privateKeyPem): string
    {
        $encKey = $this->getEncryptionKey();
        $iv     = random_bytes(12); // GCM 推荐 12 字节 IV
        $tag    = '';

        $ciphertext = openssl_encrypt(
            $privateKeyPem,
            'aes-256-gcm',
            $encKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // tag length = 16 bytes
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encryption failed: ' . openssl_error_string());
        }

        return base64_encode(json_encode([
            'iv'         => base64_encode($iv),
            'tag'        => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ]));
    }
}
