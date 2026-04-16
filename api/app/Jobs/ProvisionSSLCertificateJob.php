<?php

namespace App\Jobs;

use App\Models\Central\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * 为域名签发 SSL 证书
 *
 * 通过 certbot (Let's Encrypt) 自动签发 SSL 证书。
 * 支持 webroot 验证方式和 dry-run 模式。
 * 失败时自动更新 Domain 模型的 certificate_status 为 'failed'。
 */
class ProvisionSSLCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大重试次数
     */
    public int $tries = 3;

    /**
     * 超时时间（秒）— SSL 签发可能较慢
     */
    public int $timeout = 300;

    /**
     * 重试间隔（秒）
     */
    public int $backoff = 30;

    public function __construct(
        protected Domain $domain
    ) {
        $this->onQueue('nginx');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domainName = $this->domain->domain;

        Log::info('[ProvisionSSLCertificateJob] Starting SSL provisioning.', [
            'domain_id' => $this->domain->id,
            'domain'    => $domainName,
        ]);

        // 更新状态为 provisioning
        $this->updateCertificateStatus('provisioning');

        $nginx  = config('nginx.nginx');
        $dryRun = (bool) $nginx['dry_run'];

        if ($dryRun) {
            Log::info('[ProvisionSSLCertificateJob] DRY-RUN: Would provision SSL for domain.', [
                'domain' => $domainName,
            ]);
            $this->updateCertificateStatus('dry_run');
            return;
        }

        try {
            $certbotBin = $nginx['certbot_bin'];
            $webroot    = $nginx['certbot_webroot'];
            $email      = $nginx['certbot_email'];

            // 使用 certbot webroot 方式签发证书
            $command = implode(' ', [
                $certbotBin,
                'certonly',
                '--webroot',
                "-w {$webroot}",
                "-d {$domainName}",
                "--email {$email}",
                '--agree-tos',
                '--non-interactive',
                '--no-eff-email',
            ]);

            Log::info('[ProvisionSSLCertificateJob] Running certbot command.', [
                'command' => $command,
            ]);

            $result = Process::timeout($this->timeout - 30)->run($command);

            if ($result->successful()) {
                $this->updateCertificateStatus('active');
                Log::info('[ProvisionSSLCertificateJob] SSL certificate provisioned successfully.', [
                    'domain' => $domainName,
                    'output' => $result->output(),
                ]);
            } else {
                $this->updateCertificateStatus('failed');
                Log::error('[ProvisionSSLCertificateJob] Certbot command failed.', [
                    'domain' => $domainName,
                    'error'  => $result->errorOutput(),
                    'exit'   => $result->exitCode(),
                ]);
                $this->fail(new \RuntimeException(
                    "Certbot failed for {$domainName}: {$result->errorOutput()}"
                ));
            }
        } catch (\Throwable $e) {
            $this->updateCertificateStatus('failed');
            Log::error('[ProvisionSSLCertificateJob] Exception during SSL provisioning.', [
                'domain' => $domainName,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->updateCertificateStatus('failed');

        Log::error('[ProvisionSSLCertificateJob] Job failed permanently.', [
            'domain_id' => $this->domain->id,
            'domain'    => $this->domain->domain,
            'error'     => $exception?->getMessage(),
        ]);
    }

    /**
     * 更新域名的证书状态
     */
    protected function updateCertificateStatus(string $status): void
    {
        try {
            $this->domain->update(['certificate_status' => $status]);
        } catch (\Throwable $e) {
            // 如果 Domain 模型尚未添加 certificate_status 字段，仅记录日志
            Log::warning('[ProvisionSSLCertificateJob] Could not update certificate_status.', [
                'domain_id' => $this->domain->id,
                'status'    => $status,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
