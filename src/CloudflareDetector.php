<?php
/**
 * Cloudflare IP检测类
 * 检测给定的IP地址是否属于Cloudflare
 */

namespace App;

class CloudflareDetector
{
    private array $config;
    private array $ipv4Ranges = [];
    private array $ipv6Ranges = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->loadCloudflareIPs();
    }

    /**
     * 加载Cloudflare IP范围
     */
    private function loadCloudflareIPs(): void
    {
        $cacheFile = $this->config['cache_file'];
        $cacheTtl = $this->config['cache_ttl'];

        // 检查缓存是否存在且有效
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                $this->ipv4Ranges = $cached['ipv4'] ?? [];
                $this->ipv6Ranges = $cached['ipv6'] ?? [];
                return;
            }
        }

        // 从Cloudflare获取最新IP范围
        $this->fetchAndCacheIPs();
    }

    /**
     * 从Cloudflare获取IP范围并缓存
     */
    private function fetchAndCacheIPs(): void
    {
        // 获取IPv4范围
        $ipv4Content = @file_get_contents($this->config['ipv4_url']);
        if ($ipv4Content !== false) {
            $this->ipv4Ranges = array_filter(array_map('trim', explode("\n", $ipv4Content)));
        }

        // 获取IPv6范围
        $ipv6Content = @file_get_contents($this->config['ipv6_url']);
        if ($ipv6Content !== false) {
            $this->ipv6Ranges = array_filter(array_map('trim', explode("\n", $ipv6Content)));
        }

        // 缓存到文件
        $cacheDir = dirname($this->config['cache_file']);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($this->config['cache_file'], json_encode([
            'ipv4' => $this->ipv4Ranges,
            'ipv6' => $this->ipv6Ranges,
            'updated_at' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * 检测IP是否属于Cloudflare
     */
    public function isCloudflareIP(string $ip): bool
    {
        // 判断是IPv4还是IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isInRanges($ip, $this->ipv4Ranges);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isInRanges($ip, $this->ipv6Ranges);
        }

        return false;
    }

    /**
     * 检测IP是否在指定的CIDR范围内
     */
    private function isInRanges(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检测IP是否在CIDR范围内
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);

        // IPv4处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InCidr($ip, $subnet, (int)$mask);
        }

        return false;
    }

    /**
     * 检测IPv6是否在CIDR范围内
     */
    private function ipv6InCidr(string $ip, string $subnet, int $mask): bool
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // 将掩码转换为二进制
        $maskBin = str_repeat('1', $mask) . str_repeat('0', 128 - $mask);
        $maskBin = pack('H*', str_pad(base_convert($maskBin, 2, 16), 32, '0', STR_PAD_LEFT));

        return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
    }

    /**
     * 获取所有Cloudflare IP范围
     */
    public function getAllRanges(): array
    {
        return [
            'ipv4' => $this->ipv4Ranges,
            'ipv6' => $this->ipv6Ranges,
        ];
    }

    /**
     * 强制刷新缓存
     */
    public function refreshCache(): void
    {
        $this->fetchAndCacheIPs();
    }
}
