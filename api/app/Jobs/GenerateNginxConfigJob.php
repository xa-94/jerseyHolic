<?php

namespace App\Jobs;

use App\Models\Central\Store;
use App\Services\NginxConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 为店铺生成并写入 Nginx 配置文件
 *
 * 从模板渲染实际配置（替换占位符），将配置写入 sites-available，
 * 可选创建 symlink 到 sites-enabled。支持 dry-run 模式。
 */
class GenerateNginxConfigJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大重试次数
     */
    public int $tries = 3;

    /**
     * 超时时间（秒）
     */
    public int $timeout = 60;

    /**
     * 重试间隔（秒）
     */
    public int $backoff = 10;

    /**
     * 是否启用 dry-run 模式（覆盖全局配置）
     */
    protected ?bool $dryRunOverride;

    /**
     * 是否创建 symlink 到 sites-enabled
     */
    protected bool $enableSite;

    public function __construct(
        protected Store $store,
        ?bool $dryRun = null,
        bool $enableSite = true
    ) {
        $this->dryRunOverride = $dryRun;
        $this->enableSite     = $enableSite;
        $this->onQueue('nginx');
    }

    /**
     * Execute the job.
     */
    public function handle(NginxConfigService $service): void
    {
        Log::info('[GenerateNginxConfigJob] Starting config generation.', [
            'store_id'   => $this->store->id,
            'store_name' => $this->store->store_name,
            'dry_run'    => $this->dryRunOverride,
        ]);

        // 如果指定了 dry-run override，仅生成不写入
        if ($this->dryRunOverride === true) {
            $config = $service->generateConfig($this->store);
            Log::info('[GenerateNginxConfigJob] DRY-RUN complete. Generated config preview.', [
                'store_id'       => $this->store->id,
                'content_length' => strlen($config),
                'preview'        => substr($config, 0, 500),
            ]);
            return;
        }

        $success = $service->writeConfig($this->store, $this->enableSite);

        if ($success) {
            Log::info('[GenerateNginxConfigJob] Config written successfully.', [
                'store_id' => $this->store->id,
                'file'     => $service->getConfigFilePath($this->store),
            ]);
        } else {
            Log::error('[GenerateNginxConfigJob] Failed to write config.', [
                'store_id' => $this->store->id,
            ]);
            $this->fail(new \RuntimeException(
                "Failed to generate Nginx config for store #{$this->store->id}"
            ));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[GenerateNginxConfigJob] Job failed permanently.', [
            'store_id' => $this->store->id,
            'error'    => $exception?->getMessage(),
        ]);
    }
}
