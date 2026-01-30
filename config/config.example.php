<?php
/**
 * 旁站探测系统配置文件示例
 * 复制此文件为 config.php 并填入您的配置
 *
 * Side Site Detection System Configuration Example
 * Copy this file to config.php and fill in your settings
 */

return [
    // ViewDNS.info API配置
    'viewdns' => [
        'api_key' => 'YOUR_VIEWDNS_API_KEY_HERE', // 在此填入您的ViewDNS API Key
        'api_url' => 'https://api.viewdns.info/reverseip/',
    ],

    // MySQL数据库配置
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'sidesite_detection',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    // Cloudflare IP范围URL
    'cloudflare' => [
        'ipv4_url' => 'https://www.cloudflare.com/ips-v4',
        'ipv6_url' => 'https://www.cloudflare.com/ips-v6',
        'cache_file' => __DIR__ . '/../cache/cloudflare_ips.json',
        'cache_ttl' => 86400, // 缓存24小时
    ],

    // 系统设置
    'system' => [
        'timezone' => 'Asia/Shanghai',
        'debug' => false,
    ],
];
