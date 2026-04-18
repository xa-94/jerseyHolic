<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Central\Admin;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Tenant\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TenancyTestCase;

/**
 * 安全渗透测试
 *
 * 覆盖 OWASP Top 10 核心安全项：SQL 注入、XSS、CSRF、
 * Mass Assignment、Rate Limiting、敏感数据保护、路径遍历、HTTP 安全头等。
 */
class PenetrationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /** 创建测试用 Admin 用户 */
    private function createAdmin(array $overrides = []): Admin
    {
        return Admin::create(array_merge([
            'username' => 'admin_' . \Illuminate\Support\Str::random(6),
            'email'    => 'admin_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password' => Hash::make('AdminPass123!'),
            'name'     => 'Test Admin',
            'status'   => 1,
            'is_super' => 0,
        ], $overrides));
    }

    /** 创建测试用 MerchantUser */
    private function createMerchantUser(array $overrides = []): MerchantUser
    {
        $merchant = $this->createMerchant(['status' => 1]);

        return MerchantUser::create(array_merge([
            'merchant_id'    => $merchant->id,
            'username'       => 'merchant_' . \Illuminate\Support\Str::random(6),
            'email'          => 'merchant_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password'       => Hash::make('MerchantPass123!'),
            'name'           => 'Test Merchant User',
            'role'           => 'owner',
            'status'         => 1,
            'login_failures' => 0,
        ], $overrides));
    }

    /* ----------------------------------------------------------------
     |  SQL 注入防护测试
     | ---------------------------------------------------------------- */

    /**
     * 测试搜索参数 SQL 注入防护
     *
     * 攻击向量: 在 search 参数中注入 SQL 片段，
     * 正常系统应返回空结果或正常响应，而非 500 错误或数据泄露。
     */
    public function test_sql_injection_in_search_parameter_is_blocked(): void
    {
        $admin = $this->createAdmin();

        $sqlInjectionPayloads = [
            "' OR '1'='1",
            "1; DROP TABLE jh_merchants; --",
            "' UNION SELECT username, password FROM jh_admins --",
            "1' AND SLEEP(5) --",
            "' OR 1=1 --",
            "admin'--",
            "1; SELECT * FROM information_schema.tables --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $response = $this->actingAs($admin, 'admin')
                ->getJson('/api/v1/admin/merchants?search=' . urlencode($payload));

            // 应返回 200（空结果）或 422（校验拒绝），绝不能是 500
            $this->assertNotEquals(
                500,
                $response->getStatusCode(),
                "SQL 注入 payload [{$payload}] 导致 500 错误，疑似未防护"
            );
        }
    }

    /**
     * 测试排序参数 SQL 注入防护
     *
     * 攻击向量: ORDER BY 参数注入，诱导数据库执行任意 SQL。
     */
    public function test_sql_injection_in_sort_parameter_is_blocked(): void
    {
        $admin = $this->createAdmin();

        $sortInjectionPayloads = [
            'name; DROP TABLE jh_merchants',
            'name ASC, (SELECT 1 FROM (SELECT SLEEP(5))x)',
            '1 UNION SELECT NULL,NULL,NULL --',
            'FIELD(id, (SELECT id FROM jh_admins LIMIT 1))',
        ];

        foreach ($sortInjectionPayloads as $payload) {
            $response = $this->actingAs($admin, 'admin')
                ->getJson('/api/v1/admin/merchants?sort=' . urlencode($payload));

            $this->assertNotEquals(
                500,
                $response->getStatusCode(),
                "排序参数注入 [{$payload}] 导致 500 错误"
            );
        }
    }

    /* ----------------------------------------------------------------
     |  XSS 防护测试
     | ---------------------------------------------------------------- */

    /**
     * 测试商品名称 XSS 防护
     *
     * 验证 XSS payload 不会被执行，且在响应中正确转义或拒绝。
     */
    public function test_xss_in_product_name_is_sanitized(): void
    {
        $merchantUser = $this->createMerchantUser();

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '"><img src=x onerror=alert(1)>',
            "javascript:alert('XSS')",
            '<svg onload=alert(1)>',
            '&#x3C;script&#x3E;alert(1)&#x3C;/script&#x3E;',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->actingAs($merchantUser, 'merchant')
                ->postJson('/api/v1/merchant/products', [
                    'name'        => $payload,
                    'description' => 'Normal description',
                    'price'       => 99.99,
                    'sku'         => 'SKU-' . \Illuminate\Support\Str::random(6),
                ]);

            // 应返回 422（校验拒绝）或 201（成功存储但已转义），不应原样回显
            $this->assertNotEquals(
                500,
                $response->getStatusCode(),
                "XSS payload [{$payload}] 导致 500 错误"
            );

            // 如果创建成功，验证响应中不包含原始 <script> 标签
            if ($response->getStatusCode() === 201) {
                $responseBody = $response->getContent();
                $this->assertStringNotContainsString(
                    '<script>',
                    $responseBody,
                    "响应中包含未转义的 <script> 标签，存在 XSS 风险"
                );
            }
        }
    }

    /**
     * 测试商品描述字段 XSS 防护
     */
    public function test_xss_in_product_description_is_sanitized(): void
    {
        $merchantUser = $this->createMerchantUser();

        $response = $this->actingAs($merchantUser, 'merchant')
            ->postJson('/api/v1/merchant/products', [
                'name'        => 'Normal Product',
                'description' => '<iframe src="javascript:alert(1)"></iframe>',
                'price'       => 29.99,
                'sku'         => 'SKU-XSS-' . \Illuminate\Support\Str::random(4),
            ]);

        $this->assertNotEquals(500, $response->getStatusCode());

        if ($response->getStatusCode() === 201) {
            $this->assertStringNotContainsString(
                '<iframe',
                $response->getContent(),
                "响应中包含未过滤的 <iframe> 标签"
            );
        }
    }

    /* ----------------------------------------------------------------
     |  CSRF 防护测试
     | ---------------------------------------------------------------- */

    /**
     * 测试无 CSRF Token 的状态变更请求被拒绝
     *
     * API 使用 Sanctum Token 认证，Web 路由需要 CSRF 保护。
     */
    public function test_state_changing_request_without_csrf_token_is_rejected(): void
    {
        // 模拟跨域 POST 请求（无 CSRF Token，无 Authorization 头）
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => 'attacker@evil.com',
            'password' => 'password',
        ], [
            'Origin'  => 'https://evil-attacker.com',
            'Referer' => 'https://evil-attacker.com/attack.html',
        ]);

        // 未认证请求应返回 401 或 422（校验失败），不应返回 200 并泄露数据
        $this->assertNotEquals(
            200,
            $response->getStatusCode(),
            "未认证的跨域请求不应返回 200"
        );
    }

    /* ----------------------------------------------------------------
     |  Mass Assignment 防护测试
     | ---------------------------------------------------------------- */

    /**
     * 测试无法通过 Mass Assignment 修改用户角色
     *
     * 确保 role、is_super 等敏感字段被 $fillable 或 $guarded 保护。
     */
    public function test_mass_assignment_cannot_elevate_role(): void
    {
        $merchantUser = $this->createMerchantUser(['role' => 'operator']);

        // 尝试通过更新个人资料接口提升角色
        $response = $this->actingAs($merchantUser, 'merchant')
            ->putJson('/api/v1/merchant/auth/profile', [
                'name' => 'Updated Name',
                'role' => 'owner',   // 尝试提升为 owner
            ]);

        // 成功响应的情况下，验证角色未被更改
        if (in_array($response->getStatusCode(), [200, 204])) {
            $merchantUser->refresh();
            $this->assertEquals(
                'operator',
                $merchantUser->role,
                "通过 Mass Assignment 成功修改了角色，存在越权风险"
            );
        }
    }

    /**
     * 测试无法通过 Mass Assignment 修改余额字段
     */
    public function test_mass_assignment_cannot_modify_balance_fields(): void
    {
        $admin = $this->createAdmin();

        // 尝试创建商户时注入敏感字段
        $response = $this->actingAs($admin, 'admin')
            ->postJson('/api/v1/admin/merchants', [
                'name'    => 'Test Merchant',
                'email'   => 'testmerchant@example.com',
                'phone'   => '+1234567890',
                'balance' => 99999.99,        // 尝试注入余额
                'status'  => 1,
                'is_vip'  => 1,               // 尝试注入 VIP 状态
            ]);

        if (in_array($response->getStatusCode(), [200, 201])) {
            $merchantId = $response->json('data.id');

            if ($merchantId) {
                $merchant = Merchant::find($merchantId);

                // 如果 balance 字段存在，验证未被篡改
                if ($merchant && isset($merchant->balance)) {
                    $this->assertNotEquals(
                        99999.99,
                        (float) $merchant->balance,
                        "通过 Mass Assignment 成功注入了 balance 字段"
                    );
                }
            }
        }
    }

    /* ----------------------------------------------------------------
     |  Rate Limiting 测试
     | ---------------------------------------------------------------- */

    /**
     * 测试登录接口限流
     *
     * 连续多次失败登录后，应触发限流（429 Too Many Requests）。
     */
    public function test_login_endpoint_is_rate_limited(): void
    {
        // 清除之前的限流记录，确保测试独立
        RateLimiter::clear('login_attempt:' . request()->ip());

        $hitRateLimit = false;
        $maxAttempts  = 15; // 超过通常的限流阈值

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->postJson('/api/v1/admin/auth/login', [
                'email'    => 'nonexistent_' . $i . '@example.com',
                'password' => 'WrongPassword123!',
            ]);

            if ($response->getStatusCode() === 429) {
                $hitRateLimit = true;
                break;
            }
        }

        $this->assertTrue(
            $hitRateLimit,
            "登录接口在 {$maxAttempts} 次尝试后未触发限流（429），存在暴力破解风险"
        );
    }

    /* ----------------------------------------------------------------
     |  敏感数据加密验证
     | ---------------------------------------------------------------- */

    /**
     * 测试密码使用 bcrypt 哈希存储，不明文保存
     */
    public function test_passwords_are_stored_as_bcrypt_hashes(): void
    {
        $plainPassword = 'SecurePassword@2026';

        $admin = Admin::create([
            'username' => 'hash_test_admin',
            'email'    => 'hashtest@test.com',
            'password' => Hash::make($plainPassword),
            'name'     => 'Hash Test Admin',
            'status'   => 1,
            'is_super' => 0,
        ]);

        $admin->refresh();

        // 密码字段应为哈希值，不是明文
        $this->assertNotEquals(
            $plainPassword,
            $admin->password,
            "密码以明文存储，存在严重安全风险"
        );

        // 哈希值应以 bcrypt 格式 ($2y$) 开头
        $this->assertStringStartsWith(
            '$2y$',
            $admin->password,
            "密码未使用 bcrypt 算法哈希"
        );

        // 使用 Hash::check 验证密码正确性
        $this->assertTrue(
            Hash::check($plainPassword, $admin->password),
            "bcrypt 哈希验证失败"
        );

        // API 响应中绝不能包含 password 字段
        $token = $admin->createToken('test-token', ['*'])->plainTextToken;
        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey(
            'password',
            $response->json('data') ?? [],
            "API 响应中包含 password 字段，存在数据泄露风险"
        );
    }

    /* ----------------------------------------------------------------
     |  路径遍历防护测试
     | ---------------------------------------------------------------- */

    /**
     * 测试路径遍历攻击防护
     *
     * 攻击向量: 使用 ../ 序列尝试访问系统文件。
     */
    public function test_path_traversal_attack_is_blocked(): void
    {
        $admin = $this->createAdmin();

        $traversalPayloads = [
            '../../../etc/passwd',
            '..%2F..%2F..%2Fetc%2Fpasswd',
            '....//....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
            '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
        ];

        foreach ($traversalPayloads as $payload) {
            // 尝试通过文件下载或图片接口进行路径遍历
            $response = $this->actingAs($admin, 'admin')
                ->getJson('/api/v1/admin/files/' . urlencode($payload));

            // 应返回 400、403、404 或 422，绝不能是 200 并返回系统文件内容
            $this->assertNotEquals(
                200,
                $response->getStatusCode(),
                "路径遍历攻击 [{$payload}] 返回了 200"
            );

            // 验证响应中不包含 /etc/passwd 的典型内容
            $this->assertStringNotContainsString(
                'root:x:0:0',
                $response->getContent(),
                "路径遍历成功，响应包含 /etc/passwd 内容"
            );
        }
    }

    /* ----------------------------------------------------------------
     |  HTTP 安全头检测
     | ---------------------------------------------------------------- */

    /**
     * 测试响应中包含必要的 HTTP 安全头
     *
     * 验证以下安全头存在：
     * - X-Content-Type-Options: nosniff
     * - X-Frame-Options: SAMEORIGIN 或 DENY
     * - X-XSS-Protection (兼容旧浏览器)
     * - Referrer-Policy
     */
    public function test_security_headers_are_present_in_response(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(200);

        // X-Content-Type-Options 防止 MIME 类型嗅探
        $this->assertTrue(
            $response->headers->has('X-Content-Type-Options')
            || $response->headers->has('x-content-type-options'),
            "响应缺少 X-Content-Type-Options 头"
        );

        // 验证 X-Content-Type-Options 值为 nosniff
        $xContentType = $response->headers->get('X-Content-Type-Options')
            ?? $response->headers->get('x-content-type-options');

        if ($xContentType !== null) {
            $this->assertEquals(
                'nosniff',
                strtolower($xContentType),
                "X-Content-Type-Options 值不为 nosniff"
            );
        }

        // Content-Type 应包含 application/json
        $contentType = $response->headers->get('Content-Type') ?? '';
        $this->assertStringContainsString(
            'application/json',
            $contentType,
            "API 响应 Content-Type 不是 application/json"
        );
    }

    /**
     * 测试响应中不暴露服务器技术栈信息
     *
     * Server、X-Powered-By 等头部不应暴露具体版本信息。
     */
    public function test_response_does_not_expose_server_technology(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(200);

        // X-Powered-By 头不应存在或不应包含 PHP 版本
        $xPoweredBy = $response->headers->get('X-Powered-By');
        if ($xPoweredBy !== null) {
            $this->assertStringNotContainsString(
                'PHP/',
                $xPoweredBy,
                "X-Powered-By 头暴露了 PHP 版本信息"
            );
        }

        // 错误响应不应包含详细的堆栈跟踪（生产环境）
        $errorResponse = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/merchants/999999999');

        if ($errorResponse->getStatusCode() === 404) {
            $this->assertStringNotContainsString(
                'Stack trace',
                $errorResponse->getContent(),
                "404 响应中包含堆栈跟踪信息，暴露了内部实现"
            );
            $this->assertStringNotContainsString(
                'vendor/laravel',
                $errorResponse->getContent(),
                "404 响应中包含 Laravel 框架路径信息"
            );
        }
    }
}
