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
     * 解析域名IP（带缓存，缓存1小时）
     */
    private function resolveIP(string $domain, bool $useCache = true): ?string
    {
        // 检查缓存（1小时内的结果）
        if ($useCache) {
            try {
                $stmt = $this->db->prepare(
                    'SELECT ip_address FROM ip_cache WHERE domain = ? AND checked_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                );
                $stmt->execute([$domain]);
                $cached = $stmt->fetch();
                if ($cached !== false) {
                    return $cached['ip_address'];
                }
            } catch (\Exception $e) {
                // 缓存表可能不存在，忽略错误
            }
        }

        $ip = gethostbyname($domain);

        // 如果返回的是原域名，说明解析失败
        if ($ip === $domain) {
            // 尝试获取所有IP
            $ips = gethostbynamel($domain);
            if ($ips && count($ips) > 0) {
                $ip = $ips[0];
            } else {
                $ip = null;
            }
        }

        // 保存到缓存
        if ($useCache) {
            try {
                $stmt = $this->db->prepare(
                    'INSERT INTO ip_cache (domain, ip_address, checked_at) VALUES (?, ?, NOW())
                     ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address), checked_at = NOW()'
                );
                $stmt->execute([$domain, $ip]);
            } catch (\Exception $e) {
                // 忽略缓存写入错误
            }
        }

        return $ip;
    }

    /**
     * 清理过期的IP缓存（超过24小时）
     */
    public function cleanIpCache(): int
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM ip_cache WHERE checked_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 调用ViewDNS API查询旁站
     * @param string $host IP地址或域名
     */
    private function queryViewDNS(string $host): array|false
    {
        $apiKey = $this->config['viewdns']['api_key'] ?? '';
        $apiUrl = $this->config['viewdns']['api_url'] ?? 'https://api.viewdns.info/reverseip/';

        // 检查API Key是否配置
        if (empty($apiKey) || $apiKey === 'YOUR_API_KEY') {
            throw new \RuntimeException('请在 config/config.php 中配置 ViewDNS API 密钥');
        }

        // ViewDNS API使用host参数
        $url = sprintf('%s?host=%s&apikey=%s&output=json', $apiUrl, urlencode($host), urlencode($apiKey));

        // 优先使用cURL（更可靠）
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'SideSiteDetector/1.0',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($error)) {
                throw new \RuntimeException('cURL错误: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \RuntimeException('API返回HTTP ' . $httpCode);
            }
        } else {
            // 使用file_get_contents
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
                $err = error_get_last();
                throw new \RuntimeException('HTTP请求失败: ' . ($err['message'] ?? '未知错误'));
            }
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON解析失败: ' . json_last_error_msg());
        }

        if (!$data || !isset($data['response']['domains'])) {
            // 检查是否有错误信息
            if (isset($data['response']['error'])) {
                throw new \RuntimeException('ViewDNS API错误: ' . $data['response']['error']);
            }
            // 可能API Key无效或其他问题
            if (isset($data['response'])) {
                throw new \RuntimeException('API响应无域名数据: ' . json_encode($data['response']));
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
     * 获取旁站列表并检测当前IP（支持分页）
     */
    public function getSideSitesWithIpCheck(int $domainId, bool $checkIp = true, int $page = 1, int $perPage = 100): array
    {
        // 获取域名信息
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$domainId]);
        $domainInfo = $stmt->fetch();

        if (!$domainInfo) {
            return ['domain' => null, 'sites' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
        }

        $originalIp = $domainInfo['ip_address'];

        // 获取总数
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM side_sites WHERE domain_id = ?');
        $stmt->execute([$domainId]);
        $total = (int) $stmt->fetchColumn();

        $totalPages = $total > 0 ? ceil($total / $perPage) : 0;
        $offset = ($page - 1) * $perPage;

        // 获取旁站列表（分页）
        $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain LIMIT ? OFFSET ?');
        $stmt->execute([$domainId, $perPage, $offset]);
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
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * 获取旁站列表（不分页，用于导出）
     */
    public function getAllSideSites(int $domainId, bool $checkIp = true): array
    {
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$domainId]);
        $domainInfo = $stmt->fetch();

        if (!$domainInfo) {
            return ['domain' => null, 'sites' => []];
        }

        $originalIp = $domainInfo['ip_address'];

        $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain');
        $stmt->execute([$domainId]);
        $sites = $stmt->fetchAll();

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
     * @param string $ipFilter 筛选：all-全部, match-仅一致, mismatch-仅不一致
     */
    public function exportSingleDomain(int $domainId, string $format = 'csv', string $ipFilter = 'all'): string
    {
        $data = $this->getAllSideSites($domainId, true);

        // 根据 IP 筛选
        if ($ipFilter !== 'all' && !empty($data['sites'])) {
            $data['sites'] = array_filter($data['sites'], function ($site) use ($ipFilter) {
                if ($ipFilter === 'match') {
                    return $site['ip_match'] === true;
                } elseif ($ipFilter === 'mismatch') {
                    return $site['ip_match'] === false;
                }
                return true;
            });
            $data['sites'] = array_values($data['sites']); // 重新索引
        }

        if ($format === 'csv') {
            return $this->generateCsv($data['domain'], $data['sites']);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 导出所有域名的旁站数据
     * @param bool $checkIp 是否检测当前IP（关闭可大幅提升导出速度）
     */
    public function exportAllDomains(string $format = 'csv', string $status = 'completed', bool $checkIp = false): string
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

            foreach ($sites as &$site) {
                $site['main_domain'] = $domain['domain'];
                $site['original_ip'] = $domain['ip_address'];
                $site['hosting_type'] = $domain['hosting_type'];

                // 仅在需要时检测IP（使用缓存）
                if ($checkIp) {
                    $currentIp = $this->resolveIP($site['side_domain'], true);
                    $site['current_ip'] = $currentIp;
                    $site['ip_match'] = ($currentIp === $domain['ip_address']);
                } else {
                    $site['current_ip'] = '';
                    $site['ip_match'] = null;
                }
            }

            $allData = array_merge($allData, $sites);
        }

        if ($format === 'csv') {
            return $this->generateAllCsv($allData);
        }

        return json_encode(['domains' => $domains, 'sites' => $allData], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 流式导出所有域名的旁站（直接输出，适合大数据量）
     * @param resource $output 输出流（如 php://output）
     */
    public function streamExportAllDomains($output, string $status = 'completed', bool $checkIp = false): void
    {
        // 写入CSV表头
        fputcsv($output, [
            '主域名', '旁站域名', '原始IP', '当前IP', 'IP是否一致', '主机类型', '最后解析日期'
        ]);

        // 使用游标分批获取域名（避免一次加载太多）
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE status = ? ORDER BY id');
        $stmt->execute([$status]);

        while ($domain = $stmt->fetch()) {
            $siteStmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain');
            $siteStmt->execute([$domain['id']]);

            while ($site = $siteStmt->fetch()) {
                $currentIp = '';
                $ipMatch = '';

                if ($checkIp) {
                    $currentIp = $this->resolveIP($site['side_domain'], true) ?? '';
                    $ipMatch = ($currentIp === $domain['ip_address']) ? '是' : '否';
                }

                $hostingType = $domain['hosting_type'] === 'shared' ? '共享空间' :
                    ($domain['hosting_type'] === 'dedicated' ? '独立服务器' : '未知');

                fputcsv($output, [
                    $domain['domain'],
                    $site['side_domain'],
                    $domain['ip_address'] ?? '',
                    $currentIp,
                    $ipMatch,
                    $hostingType,
                    $site['last_resolved'] ?? '',
                ]);

                // 每100条刷新一次输出
                if (ftell($output) % 100 === 0) {
                    flush();
                }
            }
        }
    }

    /**
     * 获取导出统计信息（用于预估导出大小）
     */
    public function getExportStats(string $status = 'completed'): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as domain_count FROM domains WHERE status = ?');
        $stmt->execute([$status]);
        $domainCount = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as site_count FROM side_sites ss
             JOIN domains d ON ss.domain_id = d.id WHERE d.status = ?'
        );
        $stmt->execute([$status]);
        $siteCount = (int) $stmt->fetchColumn();

        return [
            'domain_count' => $domainCount,
            'site_count' => $siteCount,
            'estimated_rows' => $siteCount,
            'estimated_size_mb' => round($siteCount * 0.0002, 2), // 约200字节/行
        ];
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

    /**
     * 导出域名列表（不含旁站详情）
     */
    public function exportDomainList(string $format = 'csv', ?string $status = null): string
    {
        $where = '';
        $params = [];

        if ($status) {
            $where = 'WHERE status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->prepare("SELECT * FROM domains {$where} ORDER BY domain");
        $stmt->execute($params);
        $domains = $stmt->fetchAll();

        if ($format === 'json') {
            return json_encode([
                'total' => count($domains),
                'exported_at' => date('Y-m-d H:i:s'),
                'data' => $domains,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // CSV格式
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            '序号',
            '域名',
            'IP地址',
            '是否Cloudflare',
            '旁站数量',
            '主机类型',
            '状态',
            '检测时间',
        ]);

        $index = 1;
        foreach ($domains as $domain) {
            fputcsv($output, [
                $index++,
                $domain['domain'],
                $domain['ip_address'] ?? '',
                $domain['is_cloudflare'] ? '是' : '否',
                $domain['side_site_count'] ?? 0,
                $domain['hosting_type'] === 'shared' ? '共享空间' : ($domain['hosting_type'] === 'dedicated' ? '独立服务器' : '未知'),
                $domain['status'],
                $domain['detected_at'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * 批量重试域名
     */
    public function batchRetry(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "UPDATE domains SET status = 'pending', error_message = NULL WHERE id IN ($placeholders)"
        );
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    /**
     * 重试所有失败的域名
     */
    public function retryAllFailed(): int
    {
        $stmt = $this->db->prepare(
            "UPDATE domains SET status = 'pending', error_message = NULL WHERE status = 'failed'"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * 批量导出域名及旁站
     */
    public function batchExport(array $ids, string $format = 'csv'): string|array
    {
        if (empty($ids)) {
            return $format === 'csv' ? '' : [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 获取域名信息
        $stmt = $this->db->prepare("SELECT * FROM domains WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $domains = $stmt->fetchAll();

        if ($format === 'json') {
            $result = [];
            foreach ($domains as $domain) {
                $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ?');
                $stmt->execute([$domain['id']]);
                $sideSites = $stmt->fetchAll();

                $result[] = [
                    'domain' => $domain['domain'],
                    'ip_address' => $domain['ip_address'],
                    'is_cloudflare' => (bool) $domain['is_cloudflare'],
                    'side_site_count' => (int) $domain['side_site_count'],
                    'hosting_type' => $domain['hosting_type'],
                    'status' => $domain['status'],
                    'error_message' => $domain['error_message'],
                    'detected_at' => $domain['detected_at'],
                    'side_sites' => array_map(fn($s) => [
                        'domain' => $s['side_domain'],
                        'ip_address' => $s['ip_address'],
                        'last_resolved' => $s['last_resolved'],
                    ], $sideSites),
                ];
            }
            return $result;
        }

        // CSV format
        $csv = "域名,IP地址,是否CF,旁站数,主机类型,状态,错误信息,检测时间,旁站域名,旁站IP,最后解析\n";

        foreach ($domains as $domain) {
            $stmt = $this->db->prepare('SELECT * FROM side_sites WHERE domain_id = ?');
            $stmt->execute([$domain['id']]);
            $sideSites = $stmt->fetchAll();

            $hostingType = $domain['hosting_type'] === 'shared' ? '共享空间' :
                ($domain['hosting_type'] === 'dedicated' ? '独立服务器' : '未知');

            if (empty($sideSites)) {
                $csv .= sprintf(
                    "%s,%s,%s,%d,%s,%s,%s,%s,,,\n",
                    $domain['domain'],
                    $domain['ip_address'] ?? '',
                    $domain['is_cloudflare'] ? '是' : '否',
                    $domain['side_site_count'] ?? 0,
                    $hostingType,
                    $domain['status'],
                    $domain['error_message'] ?? '',
                    $domain['detected_at'] ?? ''
                );
            } else {
                foreach ($sideSites as $i => $site) {
                    if ($i === 0) {
                        $csv .= sprintf(
                            "%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s\n",
                            $domain['domain'],
                            $domain['ip_address'] ?? '',
                            $domain['is_cloudflare'] ? '是' : '否',
                            $domain['side_site_count'] ?? 0,
                            $hostingType,
                            $domain['status'],
                            $domain['error_message'] ?? '',
                            $domain['detected_at'] ?? '',
                            $site['side_domain'],
                            $site['ip_address'] ?? '',
                            $site['last_resolved'] ?? ''
                        );
                    } else {
                        $csv .= sprintf(
                            ",,,,,,,,,%s,%s,%s\n",
                            $site['side_domain'],
                            $site['ip_address'] ?? '',
                            $site['last_resolved'] ?? ''
                        );
                    }
                }
            }
        }

        return $csv;
    }

    /**
     * 批量删除域名
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 先删除旁站
        $stmt = $this->db->prepare("DELETE FROM side_sites WHERE domain_id IN ($placeholders)");
        $stmt->execute($ids);

        // 再删除域名
        $stmt = $this->db->prepare("DELETE FROM domains WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        return $stmt->rowCount();
    }
}
