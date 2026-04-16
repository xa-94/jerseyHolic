<?php

namespace App\Services;

use App\Models\Central\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Nginx 配置管理服务
 *
 * 负责为多租户店铺自动生成、写入、删除 Nginx server block 配置文件，
 * 以及测试和重载 Nginx。
 */
class NginxConfigService
{
    protected string $configPath;
    protected string $enabledPath;
    protected string $templatePath;
    protected int $nuxtPort;
    protected int $laravelPort;
    protected string $sslCertBasePath;
    protected string $nginxBin;
    protected bool $autoReload;
    protected bool $dryRun;

    public function __construct()
    {
        $nginx = config('nginx.nginx');

        $this->configPath      = $nginx['config_path'];
        $this->enabledPath     = $nginx['enabled_path'];
        $this->templatePath    = $nginx['template_path'];
        $this->nuxtPort        = (int) $nginx['nuxt_port'];
        $this->laravelPort     = (int) $nginx['laravel_port'];
        $this->sslCertBasePath = $nginx['ssl_cert_base_path'];
        $this->nginxBin        = $nginx['nginx_bin'];
        $this->autoReload      = (bool) $nginx['auto_reload'];
        $this->dryRun          = (bool) $nginx['dry_run'];
    }

    /* ----------------------------------------------------------------
     |  Core Methods
     | ---------------------------------------------------------------- */

    /**
     * 从模板渲染 Nginx 配置内容（纯字符串，不写入文件）
     */
    public function generateConfig(Store $store): string
    {
        $domain = $this->getPrimaryDomain($store);

        $template = File::get($this->templatePath);

        $replacements = [
            '{{STORE_NAME}}'    => $store->store_name,
            '{{DOMAIN}}'        => $domain,
            '{{STORE_ID}}'      => (string) $store->id,
            '{{GENERATED_AT}}'  => now()->toDateTimeString(),
            '{{SSL_CERT_PATH}}' => "{$this->sslCertBasePath}/{$domain}/fullchain.pem",
            '{{SSL_KEY_PATH}}'  => "{$this->sslCertBasePath}/{$domain}/privkey.pem",
            '{{NUXT_PORT}}'     => (string) $this->nuxtPort,
            '{{LARAVEL_PORT}}'  => (string) $this->laravelPort,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * 生成并写入配置文件到 sites-available，可选 symlink 到 sites-enabled
     *
     * @param  bool  $enableSite  是否创建 symlink 到 sites-enabled
     * @return bool  写入是否成功（dry-run 模式始终返回 true）
     */
    public function writeConfig(Store $store, bool $enableSite = true): bool
    {
        $config   = $this->generateConfig($store);
        $filename = $this->getConfigFilename($store);
        $filePath = "{$this->configPath}/{$filename}";

        if ($this->dryRun) {
            Log::info("[NginxConfigService] DRY-RUN: Would write config to {$filePath}", [
                'store_id' => $store->id,
                'domain'   => $this->getPrimaryDomain($store),
                'content_length' => strlen($config),
            ]);
            return true;
        }

        try {
            // 确保目录存在
            if (!File::isDirectory($this->configPath)) {
                File::makeDirectory($this->configPath, 0755, true);
            }

            File::put($filePath, $config);
            Log::info("[NginxConfigService] Config written: {$filePath}", ['store_id' => $store->id]);

            // 创建 symlink 到 sites-enabled
            if ($enableSite) {
                $linkPath = "{$this->enabledPath}/{$filename}";
                if (File::exists($linkPath)) {
                    File::delete($linkPath);
                }
                symlink($filePath, $linkPath);
                Log::info("[NginxConfigService] Symlink created: {$linkPath}");
            }

            // 自动 reload
            if ($this->autoReload) {
                $this->reloadNginx();
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("[NginxConfigService] Failed to write config: {$e->getMessage()}", [
                'store_id' => $store->id,
                'file'     => $filePath,
            ]);
            return false;
        }
    }

    /**
     * 删除店铺的 Nginx 配置文件和 symlink
     */
    public function removeConfig(Store $store): bool
    {
        $filename = $this->getConfigFilename($store);
        $filePath = "{$this->configPath}/{$filename}";
        $linkPath = "{$this->enabledPath}/{$filename}";

        if ($this->dryRun) {
            Log::info("[NginxConfigService] DRY-RUN: Would remove config {$filePath}");
            return true;
        }

        try {
            if (File::exists($linkPath)) {
                File::delete($linkPath);
                Log::info("[NginxConfigService] Symlink removed: {$linkPath}");
            }

            if (File::exists($filePath)) {
                File::delete($filePath);
                Log::info("[NginxConfigService] Config removed: {$filePath}");
            }

            if ($this->autoReload) {
                $this->reloadNginx();
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("[NginxConfigService] Failed to remove config: {$e->getMessage()}", [
                'store_id' => $store->id,
            ]);
            return false;
        }
    }

    /**
     * 测试 Nginx 配置语法（nginx -t）
     */
    public function testConfig(): bool
    {
        try {
            $result = Process::run("{$this->nginxBin} -t");

            if ($result->successful()) {
                Log::info('[NginxConfigService] Nginx config test passed.');
                return true;
            }

            Log::error('[NginxConfigService] Nginx config test failed.', [
                'output' => $result->errorOutput(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error("[NginxConfigService] Cannot run nginx -t: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * 重载 Nginx 配置（nginx -s reload）
     */
    public function reloadNginx(): bool
    {
        if ($this->dryRun) {
            Log::info('[NginxConfigService] DRY-RUN: Would reload nginx.');
            return true;
        }

        try {
            // 先测试配置
            if (!$this->testConfig()) {
                Log::error('[NginxConfigService] Reload aborted: config test failed.');
                return false;
            }

            $result = Process::run("{$this->nginxBin} -s reload");

            if ($result->successful()) {
                Log::info('[NginxConfigService] Nginx reloaded successfully.');
                return true;
            }

            Log::error('[NginxConfigService] Nginx reload failed.', [
                'output' => $result->errorOutput(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error("[NginxConfigService] Cannot reload nginx: {$e->getMessage()}");
            return false;
        }
    }

    /* ----------------------------------------------------------------
     |  Helper Methods
     | ---------------------------------------------------------------- */

    /**
     * 获取店铺的主域名
     */
    protected function getPrimaryDomain(Store $store): string
    {
        // 优先使用 domains 关系中的第一个域名，否则回退到 store->domain 字段
        $domainModel = $store->domains()->first();

        return $domainModel?->domain ?? $store->domain ?? "{$store->store_code}.jerseyholic.com";
    }

    /**
     * 获取配置文件名
     */
    public function getConfigFilename(Store $store): string
    {
        return "store_{$store->id}.conf";
    }

    /**
     * 获取配置文件的完整路径
     */
    public function getConfigFilePath(Store $store): string
    {
        return "{$this->configPath}/{$this->getConfigFilename($store)}";
    }

    /**
     * 检查店铺的配置文件是否存在
     */
    public function configExists(Store $store): bool
    {
        if ($this->dryRun) {
            return false;
        }

        return File::exists($this->getConfigFilePath($store));
    }
}
