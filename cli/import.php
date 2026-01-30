#!/usr/bin/env php
<?php
/**
 * 批量导入域名脚本
 * 用法: php import.php <文件路径> [--skip-duplicates]
 *
 * 支持的文件格式:
 * - TXT: 每行一个域名
 * - CSV: 第一列为域名（可带表头）
 */

$config = require __DIR__ . '/../bootstrap.php';

use App\Database;

// 解析命令行参数
$options = getopt('', ['skip-duplicates', 'help']);
$args = array_values(array_filter($argv, function($arg) {
    return strpos($arg, '--') !== 0 && $arg !== $argv[0];
}));

if (isset($options['help']) || empty($args)) {
    echo <<<HELP
批量导入域名工具

用法:
  php import.php <文件路径> [选项]

选项:
  --skip-duplicates  跳过重复域名（不更新）
  --help             显示帮助信息

支持的文件格式:
  - TXT: 每行一个域名
  - CSV: 第一列为域名

示例:
  php import.php domains.txt
  php import.php domains.csv --skip-duplicates

HELP;
    exit(0);
}

$filePath = $args[0];
$skipDuplicates = isset($options['skip-duplicates']);

if (!file_exists($filePath)) {
    echo "错误: 文件不存在 - {$filePath}\n";
    exit(1);
}

echo "============================================\n";
echo "        批量导入域名工具\n";
echo "============================================\n";
echo "文件: {$filePath}\n";
echo "跳过重复: " . ($skipDuplicates ? '是' : '否') . "\n";
echo "--------------------------------------------\n";

// 读取文件
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$domains = [];

if ($extension === 'csv') {
    $handle = fopen($filePath, 'r');
    $isFirstRow = true;
    while (($row = fgetcsv($handle)) !== false) {
        if ($isFirstRow) {
            $isFirstRow = false;
            // 检查是否是表头（如果第一个值看起来不像域名）
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', trim($row[0]))) {
                continue;
            }
        }
        if (!empty($row[0])) {
            $domains[] = trim($row[0]);
        }
    }
    fclose($handle);
} else {
    $content = file_get_contents($filePath);
    $lines = preg_split('/[\r\n]+/', $content);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '#') !== 0) {
            $domains[] = $line;
        }
    }
}

$totalDomains = count($domains);
echo "读取到 {$totalDomains} 个域名\n";
echo "--------------------------------------------\n";

if ($totalDomains === 0) {
    echo "没有找到有效的域名\n";
    exit(0);
}

// 标准化域名
function normalizeDomain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = preg_replace('#:\d+$#', '', $domain);
    return $domain;
}

function isValidDomain(string $domain): bool
{
    return (bool) preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
}

// 批量插入
$db = Database::getInstance();
$batchSize = 1000;
$inserted = 0;
$skipped = 0;
$invalid = 0;
$updated = 0;

$startTime = microtime(true);

// 准备SQL
if ($skipDuplicates) {
    $sql = 'INSERT IGNORE INTO domains (domain, status) VALUES (?, ?)';
} else {
    $sql = 'INSERT INTO domains (domain, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP';
}
$stmt = $db->prepare($sql);

$db->beginTransaction();

try {
    foreach ($domains as $index => $domain) {
        $domain = normalizeDomain($domain);

        if (!isValidDomain($domain)) {
            $invalid++;
            continue;
        }

        $stmt->execute([$domain, 'pending']);
        $affectedRows = $stmt->rowCount();

        if ($affectedRows === 1) {
            $inserted++;
        } elseif ($affectedRows === 2) {
            $updated++;
        } else {
            $skipped++;
        }

        // 每1000条提交一次
        if (($index + 1) % $batchSize === 0) {
            $db->commit();
            $db->beginTransaction();

            $progress = round(($index + 1) / $totalDomains * 100, 1);
            $elapsed = round(microtime(true) - $startTime, 1);
            echo "\r进度: {$progress}% ({$index}/{$totalDomains}) - 已用时: {$elapsed}秒    ";
        }
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}

$elapsed = round(microtime(true) - $startTime, 2);

echo "\n--------------------------------------------\n";
echo "导入完成!\n";
echo "--------------------------------------------\n";
echo "总计处理: {$totalDomains}\n";
echo "新增: {$inserted}\n";
echo "更新: {$updated}\n";
echo "跳过(重复): {$skipped}\n";
echo "无效域名: {$invalid}\n";
echo "耗时: {$elapsed} 秒\n";
echo "============================================\n";
