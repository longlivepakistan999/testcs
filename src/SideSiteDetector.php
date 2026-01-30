<?php
/**
 * 旁站探测类
 * 使用ViewDNS.info API进行反向IP查询
 */

namespace App;

use PDO;

class SideSiteDetector
{
    private array $config;
    private CloudflareDetector $cfDetector;
    private PDO $db;

    // 判断为共享主机的旁站数量阈值
    private const SHARED_HOSTING_THRESHOLD = 5;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->cfDetector = new CloudflareDetector($config['cloudflare']);
        $this->db = Database::getInstance();
    }

    /**
     * 添加域名到检测队列
     */
    public function addDomain(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);

        if (!$this->isValidDomain($domain)) {
            return ['success' => false, 'message' => '无效的域名格式'];
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO domains (domain, status) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$domain, 'pending']);

            return ['success' => true, 'message' => '域名已添加', 'domain' => $domain];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => '添加失败: ' . $e->getMessage()];
        }
    }

    /**
     * 批量添加域名
     */
    public function addDomains(array $domains): array
    {
        $results = [];
        foreach ($domains as $domain) {
            $results[] = $this->addDomain($domain);
        }
        return $results;
    }

    /**
     * 检测单个域名
     */
    public function detectDomain(int $domainId): array
    {
        // 获取域名信息
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$domainId]);
        $domainInfo = $stmt->fetch();

        if (!$domainInfo) {
            return ['success' => false, 'message' => '域名不存在'];
        }

        $domain = $domainInfo['domain'];

        // 更新状态为处理中
        $this->updateDomainStatus($domainId, 'processing');

        try {
            // 1. 解析域名获取IP
            $ip = $this->resolveIP($domain);
            if (!$ip) {
                $this->updateDomainStatus($domainId, 'failed', '无法解析域名IP');
                return ['success' => false, 'message' => '无法解析域名IP'];
            }

            // 更新IP地址
            $stmt = $this->db->prepare('UPDATE domains SET ip_address = ? WHERE id = ?');
            $stmt->execute([$ip, $domainId]);

            // 2. 检测是否是Cloudflare IP
            $isCloudflare = $this->cfDetector->isCloudflareIP($ip);
            $stmt = $this->db->prepare('UPDATE domains SET is_cloudflare = ? WHERE id = ?');
            $stmt->execute([$isCloudflare ? 1 : 0, $domainId]);

            if ($isCloudflare) {
                // Cloudflare IP，跳过旁站探测
                $this->updateDomainStatus($domainId, 'skipped', '使用Cloudflare CDN，无法获取真实IP');
                return [
                    'success' => true,
                    'message' => '域名使用Cloudflare，已跳过旁站探测',
                    'ip' => $ip,
                    'is_cloudflare' => true,
                ];
            }

            // 3. 调用ViewDNS API进行旁站探测
            $sideSites = $this->queryViewDNS($ip);

            if ($sideSites === false) {
                $this->updateDomainStatus($domainId, 'failed', 'ViewDNS API调用失败');
                return ['success' => false, 'message' => 'ViewDNS API调用失败'];
            }

            // 4. 保存旁站数据
            $this->saveSideSites($domainId, $ip, $sideSites);

            // 5. 判断主机类型
            $siteCount = count($sideSites);
            $hostingType = $siteCount > self::SHARED_HOSTING_THRESHOLD ? 'shared' : 'dedicated';

            // 更新域名信息
            $stmt = $this->db->prepare(
                'UPDATE domains SET side_site_count = ?, hosting_type = ?, status = ?, detected_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$siteCount, $hostingType, 'completed', $domainId]);

            return [
                'success' => true,
                'message' => '检测完成',
                'ip' => $ip,
                'is_cloudflare' => false,
                'side_site_count' => $siteCount,
                'hosting_type' => $hostingType,
                'side_sites' => $sideSites,
            ];
        } catch (\Exception $e) {
            $this->updateDomainStatus($domainId, 'failed', $e->getMessage());
            return ['success' => false, 'message' => '检测失败: ' . $e->getMessage()];
        }
    }

    /**
     * 处理待检测的域名队列
     */
    public function processQueue(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM domains WHERE status = ? ORDER BY created_at ASC LIMIT ?'
        );
        $stmt->execute(['pending', $limit]);
        $domains = $stmt->fetchAll();

        $results = [];
        foreach ($domains as $domain) {
            $results[] = $this->detectDomain($domain['id']);
            // 避免API请求过于频繁
            usleep(500000); // 0.5秒延迟
        }

        return $results;
    }

    /**
     * 解析域名IP
     */
    private function resolveIP(string $domain): ?string
    {
        $ip = gethostbyname($domain);

        // 如果返回的是原域名，说明解析失败
        if ($ip === $domain) {
            // 尝试获取所有IP
            $ips = gethostbynamel($domain);
            if ($ips && count($ips) > 0) {
                return $ips[0];
            }
            return null;
        }

        return $ip;
    }

    /**
     * 调用ViewDNS API查询旁站
     * @param string $host IP地址或域名
     */
    private function queryViewDNS(string $host): array|false
    {
        $apiKey = $this->config['viewdns']['api_key'];
        $apiUrl = $this->config['viewdns']['api_url'];

        // ViewDNS API使用host参数
        $url = sprintf('%s?host=%s&apikey=%s&output=json', $apiUrl, urlencode($host), urlencode($apiKey));

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => "User-Agent: SideSiteDetector/1.0\r\nAccept: application/json",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['response']['domains'])) {
            // 检查是否有错误信息
            if (isset($data['response']['error'])) {
                throw new \RuntimeException('ViewDNS API错误: ' . $data['response']['error']);
            }
            return false;
        }

        return $data['response']['domains'];
    }

    /**
     * 保存旁站数据
     */
    private function saveSideSites(int $domainId, string $ip, array $sideSites): void
    {
        // 先清除旧数据
        $stmt = $this->db->prepare('DELETE FROM side_sites WHERE domain_id = ?');
        $stmt->execute([$domainId]);

        // 插入新数据
        $stmt = $this->db->prepare(
            'INSERT INTO side_sites (domain_id, ip_address, side_domain, last_resolved) VALUES (?, ?, ?, ?)'
        );

        foreach ($sideSites as $site) {
            $sideDomain = $site['name'] ?? $site;
            $lastResolved = $site['last_resolved'] ?? null;
            $stmt->execute([$domainId, $ip, $sideDomain, $lastResolved]);
        }
    }

    /**
     * 更新域名状态
     */
    private function updateDomainStatus(int $domainId, string $status, ?string $errorMessage = null): void
    {
        $stmt = $this->db->prepare('UPDATE domains SET status = ?, error_message = ? WHERE id = ?');
        $stmt->execute([$status, $errorMessage, $domainId]);
    }

    /**
     * 标准化域名
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        // 移除协议前缀
        $domain = preg_replace('#^https?://#', '', $domain);
        // 移除路径和查询参数
        $domain = preg_replace('#/.*$#', '', $domain);
        // 移除端口号
        $domain = preg_replace('#:\d+$#', '', $domain);
        return $domain;
    }

    /**
     * 验证域名格式
     */
    private function isValidDomain(string $domain): bool
    {
        return (bool) preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
    }

    /**
     * 获取所有域名列表
     */
    public function getDomainList(int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];

        if ($status) {
            $where = 'WHERE status = ?';
            $params[] = $status;
        }

        // 获取总数
        $countSql = "SELECT COUNT(*) FROM domains $where";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // 获取数据
        $sql = "SELECT * FROM domains $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $domains = $stmt->fetchAll();

        return [
            'data' => $domains,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * 获取域名的旁站列表
     */
    public function getSideSites(int $domainId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain');
        $stmt->execute([$domainId]);
        return $stmt->fetchAll();
    }

    /**
     * 获取统计数据
     */
    public function getStatistics(): array
    {
        // 总体统计
        $stmt = $this->db->query('SELECT COUNT(*) as total FROM domains');
        $total = $stmt->fetch()['total'];

        // 状态统计
        $stmt = $this->db->query('SELECT status, COUNT(*) as count FROM domains GROUP BY status');
        $statusStats = [];
        while ($row = $stmt->fetch()) {
            $statusStats[$row['status']] = (int) $row['count'];
        }

        // 主机类型统计
        $stmt = $this->db->query(
            'SELECT hosting_type, COUNT(*) as count FROM domains WHERE status = "completed" GROUP BY hosting_type'
        );
        $hostingStats = [];
        while ($row = $stmt->fetch()) {
            $hostingStats[$row['hosting_type']] = (int) $row['count'];
        }

        // Cloudflare统计
        $stmt = $this->db->query('SELECT is_cloudflare, COUNT(*) as count FROM domains GROUP BY is_cloudflare');
        $cfStats = [];
        while ($row = $stmt->fetch()) {
            $cfStats[$row['is_cloudflare'] ? 'cloudflare' : 'non_cloudflare'] = (int) $row['count'];
        }

        return [
            'total' => $total,
            'status' => $statusStats,
            'hosting_type' => $hostingStats,
            'cloudflare' => $cfStats,
        ];
    }

    /**
     * 删除域名
     */
    public function deleteDomain(int $domainId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM domains WHERE id = ?');
        return $stmt->execute([$domainId]);
    }

    /**
     * 重新检测域名
     */
    public function retryDomain(int $domainId): array
    {
        $stmt = $this->db->prepare('UPDATE domains SET status = ?, error_message = NULL WHERE id = ?');
        $stmt->execute(['pending', $domainId]);
        return $this->detectDomain($domainId);
    }

    /**
     * 获取旁站列表并检测当前IP
     */
    public function getSideSitesWithIpCheck(int $domainId, bool $checkIp = true): array
    {
        // 获取域名信息
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$domainId]);
        $domainInfo = $stmt->fetch();

        if (!$domainInfo) {
            return ['domain' => null, 'sites' => []];
        }

        $originalIp = $domainInfo['ip_address'];

        // 获取旁站列表
        $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain');
        $stmt->execute([$domainId]);
        $sites = $stmt->fetchAll();

        // 如果需要检测IP
        if ($checkIp && !empty($sites)) {
            foreach ($sites as &$site) {
                $currentIp = $this->resolveIP($site['side_domain']);
                $site['current_ip'] = $currentIp;
                $site['ip_match'] = ($currentIp === $originalIp);
            }
        }

        return [
            'domain' => $domainInfo,
            'original_ip' => $originalIp,
            'sites' => $sites,
        ];
    }

    /**
     * 导出单个域名的旁站数据
     */
    public function exportSingleDomain(int $domainId, string $format = 'csv'): string
    {
        $data = $this->getSideSitesWithIpCheck($domainId, true);

        if ($format === 'csv') {
            return $this->generateCsv($data['domain'], $data['sites']);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 导出所有域名的旁站数据
     */
    public function exportAllDomains(string $format = 'csv', string $status = 'completed'): string
    {
        // 获取所有符合条件的域名
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE status = ? ORDER BY domain');
        $stmt->execute([$status]);
        $domains = $stmt->fetchAll();

        $allData = [];

        foreach ($domains as $domain) {
            $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain');
            $stmt->execute([$domain['id']]);
            $sites = $stmt->fetchAll();

            // 检测每个旁站的当前IP
            foreach ($sites as &$site) {
                $currentIp = $this->resolveIP($site['side_domain']);
                $site['current_ip'] = $currentIp;
                $site['ip_match'] = ($currentIp === $domain['ip_address']);
                $site['main_domain'] = $domain['domain'];
                $site['original_ip'] = $domain['ip_address'];
                $site['hosting_type'] = $domain['hosting_type'];
            }

            $allData = array_merge($allData, $sites);
        }

        if ($format === 'csv') {
            return $this->generateAllCsv($allData);
        }

        return json_encode(['domains' => $domains, 'sites' => $allData], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 生成单个域名的CSV
     */
    private function generateCsv(?array $domain, array $sites): string
    {
        $output = fopen('php://temp', 'r+');

        // 写入表头
        fputcsv($output, [
            '序号',
            '旁站域名',
            '原始IP',
            '当前IP',
            'IP是否一致',
            '最后解析日期',
        ]);

        // 写入数据
        $index = 1;
        foreach ($sites as $site) {
            fputcsv($output, [
                $index++,
                $site['side_domain'],
                $site['ip_address'] ?? '',
                $site['current_ip'] ?? '',
                isset($site['ip_match']) ? ($site['ip_match'] ? '是' : '否') : '',
                $site['last_resolved'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * 生成所有域名的CSV
     */
    private function generateAllCsv(array $sites): string
    {
        $output = fopen('php://temp', 'r+');

        // 写入表头
        fputcsv($output, [
            '序号',
            '主域名',
            '主机类型',
            '旁站域名',
            '原始IP',
            '当前IP',
            'IP是否一致',
            '最后解析日期',
        ]);

        // 写入数据
        $index = 1;
        foreach ($sites as $site) {
            fputcsv($output, [
                $index++,
                $site['main_domain'] ?? '',
                $site['hosting_type'] === 'shared' ? '共享空间' : ($site['hosting_type'] === 'dedicated' ? '独立服务器' : '未知'),
                $site['side_domain'],
                $site['original_ip'] ?? $site['ip_address'] ?? '',
                $site['current_ip'] ?? '',
                isset($site['ip_match']) ? ($site['ip_match'] ? '是' : '否') : '',
                $site['last_resolved'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
