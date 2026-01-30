#!/usr/bin/env php
<?php
/**
 * 批量导出脚本
 * 用法: php export.php [选项]
 */

$config = require __DIR__ . '/../bootstrap.php';

use App\Database;

// 解析命令行参数
$options = getopt('o:f:s:', [
    'output:',     // 输出文件
    'format:',     // 格式 csv/json
    'status:',     // 筛选状态
    'type:',       // 筛选主机类型
    'no-ip-check', // 不检测当前IP
    'help',
]);

if (isset($options['help'])) {
    echo <<<HELP
批量导出工具

用法:
  php export.php [选项]

选项:
  -o, --output=FILE    输出文件路径 (默认: 输出到标准输出)
  -f, --format=FORMAT  输出格式 csv/json (默认: csv)
  -s, --status=STATUS  筛选状态: completed/failed/skipped (默认: completed)
  --type=TYPE          筛选主机类型: shared/dedicated
  --no-ip-check        不检测旁站当前IP (加快导出速度)
  --help               显示帮助信息

示例:
  php export.php -o result.csv -f csv
  php export.php -o result.json -f json --status=completed
  php export.php --type=shared -o shared_hosts.csv
  php export.php --no-ip-check -o quick_export.csv

HELP;
    exit(0);
}

// 解析参数
$outputFile = $options['o'] ?? $options['output'] ?? null;
$format = $options['f'] ?? $options['format'] ?? 'csv';
$status = $options['s'] ?? $options['status'] ?? 'completed';
$hostingType = $options['type'] ?? null;
$checkIp = !isset($options['no-ip-check']);

$db = Database::getInstance();

// 构建查询
$where = ['status = ?'];
$params = [$status];

if ($hostingType) {
    $where[] = 'hosting_type = ?';
    $params[] = $hostingType;
}

$whereSql = implode(' AND ', $where);

// 获取域名列表
$sql = "SELECT * FROM domains WHERE {$whereSql} ORDER BY domain";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$domains = $stmt->fetchAll();

$totalDomains = count($domains);

if ($totalDomains === 0) {
    fwrite(STDERR, "没有找到符合条件的域名\n");
    exit(0);
}

fwrite(STDERR, "找到 {$totalDomains} 个域名\n");

// 收集所有数据
$allData = [];
$processed = 0;

foreach ($domains as $domain) {
    $processed++;

    // 获取旁站
    $stmt = $db->prepare('SELECT * FROM side_sites WHERE domain_id = ? ORDER BY side_domain');
    $stmt->execute([$domain['id']]);
    $sites = $stmt->fetchAll();

    foreach ($sites as $site) {
        $row = [
            'main_domain' => $domain['domain'],
            'ip_address' => $domain['ip_address'],
            'hosting_type' => $domain['hosting_type'],
            'side_site_count' => $domain['side_site_count'],
            'side_domain' => $site['side_domain'],
            'original_ip' => $site['ip_address'],
            'last_resolved' => $site['last_resolved'],
        ];

        // 检测当前IP
        if ($checkIp) {
            $currentIp = @gethostbyname($site['side_domain']);
            if ($currentIp === $site['side_domain']) {
                $currentIp = null;
            }
            $row['current_ip'] = $currentIp;
            $row['ip_match'] = ($currentIp === $domain['ip_address']) ? '是' : '否';
        }

        $allData[] = $row;
    }

    // 显示进度
    if ($processed % 100 === 0 || $processed === $totalDomains) {
        $percent = round($processed / $totalDomains * 100, 1);
        fwrite(STDERR, "\r处理进度: {$percent}% ({$processed}/{$totalDomains})");
    }
}

fwrite(STDERR, "\n");

$totalSites = count($allData);
fwrite(STDERR, "共 {$totalSites} 条旁站记录\n");

// 生成输出
if ($format === 'json') {
    $output = json_encode([
        'meta' => [
            'total_domains' => $totalDomains,
            'total_sites' => $totalSites,
            'exported_at' => date('Y-m-d H:i:s'),
            'status_filter' => $status,
            'hosting_type_filter' => $hostingType,
        ],
        'data' => $allData,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    // CSV格式
    $output = fopen('php://temp', 'r+');

    // 写入BOM
    fwrite($output, "\xEF\xBB\xBF");

    // 表头
    $headers = ['主域名', 'IP地址', '主机类型', '旁站数量', '旁站域名', '原始IP', '最后解析日期'];
    if ($checkIp) {
        $headers[] = '当前IP';
        $headers[] = 'IP是否一致';
    }
    fputcsv($output, $headers);

    // 数据
    foreach ($allData as $row) {
        $csvRow = [
            $row['main_domain'],
            $row['ip_address'],
            $row['hosting_type'] === 'shared' ? '共享空间' : ($row['hosting_type'] === 'dedicated' ? '独立服务器' : '未知'),
            $row['side_site_count'],
            $row['side_domain'],
            $row['original_ip'],
            $row['last_resolved'],
        ];
        if ($checkIp) {
            $csvRow[] = $row['current_ip'] ?? '';
            $csvRow[] = $row['ip_match'] ?? '';
        }
        fputcsv($output, $csvRow);
    }

    rewind($output);
    $output = stream_get_contents($output);
}

// 输出
if ($outputFile) {
    file_put_contents($outputFile, $output);
    fwrite(STDERR, "已导出到: {$outputFile}\n");
} else {
    echo $output;
}

fwrite(STDERR, "完成!\n");
