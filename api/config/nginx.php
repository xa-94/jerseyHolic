<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nginx Configuration
    |--------------------------------------------------------------------------
    |
    | Nginx 自动配置相关选项。用于为多租户店铺自动生成 Nginx server block
    | 配置文件、管理 SSL 证书、以及执行 nginx reload。
    |
    */

    'nginx' => [
        // Nginx sites-available 目录
        'config_path' => env('NGINX_CONFIG_PATH', '/etc/nginx/sites-available'),

        // Nginx sites-enabled 目录
        'enabled_path' => env('NGINX_ENABLED_PATH', '/etc/nginx/sites-enabled'),

        // 店铺配置模板路径
        'template_path' => resource_path('templates/nginx/store.conf.template'),

        // 通配符配置模板路径
        'wildcard_template_path' => resource_path('templates/nginx/wildcard.conf.template'),

        // Nuxt 3 SSR 端口
        'nuxt_port' => env('NUXT_PORT', 3000),

        // Laravel API 端口
        'laravel_port' => env('LARAVEL_PORT', 8000),

        // SSL 证书基础路径（Let's Encrypt 默认路径）
        'ssl_cert_base_path' => env('SSL_CERT_PATH', '/etc/letsencrypt/live'),

        // 通配符 SSL 证书路径
        'ssl_wildcard_cert' => env('SSL_WILDCARD_CERT', '/etc/letsencrypt/live/jerseyholic.com/fullchain.pem'),
        'ssl_wildcard_key' => env('SSL_WILDCARD_KEY', '/etc/letsencrypt/live/jerseyholic.com/privkey.pem'),

        // 配置变更后是否自动 reload nginx（生产环境建议 true）
        'auto_reload' => env('NGINX_AUTO_RELOAD', false),

        // Dry-run 模式：只生成配置不写入文件系统（开发环境默认 true）
        'dry_run' => env('NGINX_DRY_RUN', true),

        // Nginx 二进制路径
        'nginx_bin' => env('NGINX_BIN', '/usr/sbin/nginx'),

        // certbot / acme.sh 路径
        'certbot_bin' => env('CERTBOT_BIN', '/usr/bin/certbot'),

        // certbot webroot 路径
        'certbot_webroot' => env('CERTBOT_WEBROOT', '/var/www/certbot'),

        // certbot email
        'certbot_email' => env('CERTBOT_EMAIL', 'admin@jerseyholic.com'),
    ],

];
