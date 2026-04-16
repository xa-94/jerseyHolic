<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Central\Blacklist;
use App\Services\NotificationService;
use App\Services\Payment\BlacklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class BlacklistTest extends TestCase
{
    use RefreshDatabase;

    private BlacklistService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('sendToAdmin')->andReturnNull();

        $this->service = new BlacklistService($notificationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  IP 黑名单命中拦截
     | ---------------------------------------------------------------- */

    public function test_ip_blacklist_hit(): void
    {
        Blacklist::create([
            'scope'     => Blacklist::SCOPE_PLATFORM,
            'dimension' => Blacklist::DIMENSION_IP,
            'value'     => '192.168.1.100',
            'reason'    => 'Suspicious activity',
        ]);

        Cache::flush();

        $this->assertTrue($this->service->isBlocked(Blacklist::DIMENSION_IP, '192.168.1.100'));
    }

    /* ----------------------------------------------------------------
     |  Email 黑名单命中拦截
     | ---------------------------------------------------------------- */

    public function test_email_blacklist_hit(): void
    {
        Blacklist::create([
            'scope'     => Blacklist::SCOPE_PLATFORM,
            'dimension' => Blacklist::DIMENSION_EMAIL,
            'value'     => 'scammer@evil.com',
            'reason'    => 'Known scammer',
        ]);

        Cache::flush();

        $this->assertTrue($this->service->isBlocked(Blacklist::DIMENSION_EMAIL, 'scammer@evil.com'));
    }

    /* ----------------------------------------------------------------
     |  设备指纹黑名单命中
     | ---------------------------------------------------------------- */

    public function test_device_fingerprint_blacklist_hit(): void
    {
        Blacklist::create([
            'scope'     => Blacklist::SCOPE_PLATFORM,
            'dimension' => Blacklist::DIMENSION_DEVICE,
            'value'     => 'fp_abc123def456',
            'reason'    => 'Fraudulent device',
        ]);

        Cache::flush();

        $this->assertTrue($this->service->isBlocked(Blacklist::DIMENSION_DEVICE, 'fp_abc123def456'));
    }

    /* ----------------------------------------------------------------
     |  黑名单 CRUD
     | ---------------------------------------------------------------- */

    public function test_blacklist_crud_operations(): void
    {
        // Create
        $entry = $this->service->add(
            Blacklist::DIMENSION_IP,
            '10.0.0.1',
            'Test reason',
        );

        $this->assertInstanceOf(Blacklist::class, $entry);
        $this->assertSame('10.0.0.1', $entry->value);
        $this->assertSame(Blacklist::SCOPE_PLATFORM, $entry->scope);

        // Update
        $updated = $this->service->update($entry->id, ['reason' => 'Updated reason']);
        $this->assertSame('Updated reason', $updated->reason);

        // Delete
        $result = $this->service->remove($entry->id);
        $this->assertTrue($result);
    }

    /* ----------------------------------------------------------------
     |  黑名单 Redis 缓存命中
     | ---------------------------------------------------------------- */

    public function test_blacklist_uses_cache(): void
    {
        Blacklist::create([
            'scope'     => Blacklist::SCOPE_PLATFORM,
            'dimension' => Blacklist::DIMENSION_IP,
            'value'     => '10.20.30.40',
            'reason'    => 'Cache test',
        ]);

        Cache::flush();

        // 第一次查询会写入缓存
        $firstResult = $this->service->isBlocked(Blacklist::DIMENSION_IP, '10.20.30.40');
        $this->assertTrue($firstResult);

        // 删除 DB 记录但缓存仍在
        Blacklist::where('value', '10.20.30.40')->delete();

        // 缓存命中，仍返回 true
        $cachedResult = $this->service->isBlocked(Blacklist::DIMENSION_IP, '10.20.30.40');
        $this->assertTrue($cachedResult);
    }

    /* ----------------------------------------------------------------
     |  过期黑名单不拦截
     | ---------------------------------------------------------------- */

    public function test_expired_blacklist_does_not_block(): void
    {
        Blacklist::create([
            'scope'     => Blacklist::SCOPE_PLATFORM,
            'dimension' => Blacklist::DIMENSION_IP,
            'value'     => '10.10.10.10',
            'reason'    => 'Expired entry',
            'expires_at' => now()->subDays(1), // 已过期
        ]);

        Cache::flush();

        $this->assertFalse($this->service->isBlocked(Blacklist::DIMENSION_IP, '10.10.10.10'));
    }
}
