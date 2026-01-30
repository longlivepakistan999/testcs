#!/usr/bin/env php
<?php
/**
 * 后台队列处理脚本
 * 用法: php process.php [选项]
 *
 * 支持后台运行:
 *   nohup php process.php --batch=100 > logs/process.log 2>&1 &
 *   或使用 screen/tmux
 */

declare(ticks=1);

$config = require __DIR__ . '/../bootstrap.php';

use App\SideSiteDetector;
use App\Database;

// 解析命令行参数
$options = getopt('', [
    'batch:',      // 每批处理数量
    'delay:',      // API调用间隔(毫秒)
    'limit:',      // 总处理数量限制
    'status',      // 显示当前状态
    'retry',       // 重试失败的域名
    'refresh-ip',  // 刷新所有旁站IP
    'help',
]);

if (isset($options['help'])) {
    echo <<<HELP
后台队列处理工具

用法:
  php process.php [选项]

选项:
  --batch=N      每批处理数量 (默认: 100)
  --delay=N      API调用间隔毫秒 (默认: 500)
  --limit=N      总处理数量限制 (默认: 无限制)
  --status       显示当前处理状态
  --retry        重试所有失败的域名
  --refresh-ip   刷新所有旁站的当前IP
  --help         显示帮助信息

示例:
  php process.php --batch=50 --delay=1000
  php process.php --status
  php process.php --retry
  php process.php --refresh-ip

后台运行:
  nohup php process.php --batch=100 > logs/process.log 2>&1 &

HELP;
    exit(0);
}

// 显示状态
if (isset($options['status'])) {
    showStatus();
    exit(0);
}

// 重试失败的域名
if (isset($options['retry'])) {
    retryFailed();
    exit(0);
}

// 刷新旁站IP
if (isset($options['refresh-ip'])) {
    refreshSideSitesIp($config);
    exit(0);
}

// 处理参数
$batchSize = isset($options['batch']) ? (int)$options['batch'] : 100;
$delay = isset($options['delay']) ? (int)$options['delay'] : 500;
$limit = isset($options['limit']) ? (int)$options['limit'] : 0;

// 信号处理（优雅退出）
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$running) {
        echo "\n收到终止信号，正在优雅退出...\n";
        $running = false;
    });
    pcntl_signal(SIGINT, function() use (&$running) {
        echo "\n收到中断信号，正在优雅退出...\n";
        $running = false;
    });
}

echo "============================================\n";
echo "        后台队列处理工具\n";
echo "============================================\n";
echo "批次大小: {$batchSize}\n";
echo "API延迟: {$delay}ms\n";
echo "处理限制: " . ($limit > 0 ? $limit : '无限制') . "\n";
echo "--------------------------------------------\n";
echo "按 Ctrl+C 优雅退出\n";
echo "--------------------------------------------\n\n";

$detector = new SideSiteDetector($config);
$db = Database::getInstance();

$totalProcessed = 0;
$totalSuccess = 0;
$totalFailed = 0;
$totalSkipped = 0;
$startTime = microtime(true);

// 创建日志目录
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// 主处理循环
while ($running) {
    // 获取待处理的域名
    $stmt = $db->prepare(
        'SELECT id, domain FROM domains WHERE status = ? ORDER BY created_at ASC LIMIT ?'
    );
    $stmt->execute(['pending', $batchSize]);
    $domains = $stmt->fetchAll();

    if (empty($domains)) {
        echo "\n没有待处理的域名，等待中...\n";
        sleep(10);
        continue;
    }

    foreach ($domains as $domain) {
        if (!$running) break;

        // 检查限制
        if ($limit > 0 && $totalProcessed >= $limit) {
            echo "\n已达到处理限制 ({$limit})，退出\n";
            $running = false;
            break;
        }

        $totalProcessed++;

        echo sprintf(
            "[%s] #%d 处理: %s ... ",
            date('H:i:s'),
            $totalProcessed,
            $domain['domain']
        );

        try {
            $result = $detector->detectDomain($domain['id']);

            if ($result['success']) {
                if (isset($result['is_cloudflare']) && $result['is_cloudflare']) {
                    $totalSkipped++;
                    echo "跳过(Cloudflare)\n";
                } else {
                    $totalSuccess++;
                    $siteCount = $result['side_site_count'] ?? 0;
                    $hostingType = $result['hosting_type'] ?? 'unknown';
                    echo "完成 (旁站: {$siteCount}, 类型: {$hostingType})\n";
                }
            } else {
                $totalFailed++;
                $error = $result['message'] ?? '未知错误';
                echo "失败: {$error}\n";
            }
        } catch (Exception $e) {
            $totalFailed++;
            echo "异常: " . $e->getMessage() . "\n";

            // 记录错误日志
            $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
            $logMessage = sprintf(
                "[%s] Domain: %s, Error: %s\n",
                date('Y-m-d H:i:s'),
                $domain['domain'],
                $e->getMessage()
            );
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }

        // API延迟
        usleep($delay * 1000);

        // 每100个显示进度
        if ($totalProcessed % 100 === 0) {
            showProgress($totalProcessed, $totalSuccess, $totalFailed, $totalSkipped, $startTime);
        }
    }
}

// 最终统计
echo "\n============================================\n";
echo "              处理完成\n";
echo "============================================\n";
showProgress($totalProcessed, $totalSuccess, $totalFailed, $totalSkipped, $startTime);
echo "============================================\n";

/**
 * 显示进度
 */
function showProgress($processed, $success, $failed, $skipped, $startTime)
{
    $elapsed = microtime(true) - $startTime;
    $rate = $elapsed > 0 ? round($processed / $elapsed * 60, 1) : 0;

    echo "--------------------------------------------\n";
    echo "已处理: {$processed}\n";
    echo "成功: {$success} | 失败: {$failed} | 跳过: {$skipped}\n";
    echo "速率: {$rate} 个/分钟\n";
    echo "耗时: " . formatTime($elapsed) . "\n";
    echo "--------------------------------------------\n";
}

/**
 * 格式化时间
 */
function formatTime($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = floor($seconds % 60);

    if ($hours > 0) {
        return sprintf('%d小时%d分%d秒', $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf('%d分%d秒', $minutes, $secs);
    } else {
        return sprintf('%d秒', $secs);
    }
}

/**
 * 显示当前状态
 */
function showStatus()
{
    $db = Database::getInstance();

    echo "============================================\n";
    echo "           当前处理状态\n";
    echo "============================================\n";

    // 获取各状态数量
    $stmt = $db->query('SELECT status, COUNT(*) as count FROM domains GROUP BY status');
    $stats = [];
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }

    $total = array_sum($stats);
    $pending = $stats['pending'] ?? 0;
    $processing = $stats['processing'] ?? 0;
    $completed = $stats['completed'] ?? 0;
    $failed = $stats['failed'] ?? 0;
    $skipped = $stats['skipped'] ?? 0;

    echo "总域名数: {$total}\n";
    echo "--------------------------------------------\n";
    echo "待处理: {$pending}\n";
    echo "处理中: {$processing}\n";
    echo "已完成: {$completed}\n";
    echo "已跳过: {$skipped}\n";
    echo "失败: {$failed}\n";
    echo "--------------------------------------------\n";

    if ($total > 0) {
        $completedPercent = round(($completed + $skipped) / $total * 100, 1);
        echo "完成率: {$completedPercent}%\n";
    }

    // 主机类型统计
    $stmt = $db->query(
        'SELECT hosting_type, COUNT(*) as count FROM domains WHERE status = "completed" GROUP BY hosting_type'
    );
    $hostingStats = [];
    while ($row = $stmt->fetch()) {
        $hostingStats[$row['hosting_type']] = $row['count'];
    }

    if (!empty($hostingStats)) {
        echo "--------------------------------------------\n";
        echo "主机类型统计:\n";
        echo "  共享空间: " . ($hostingStats['shared'] ?? 0) . "\n";
        echo "  独立服务器: " . ($hostingStats['dedicated'] ?? 0) . "\n";
    }

    echo "============================================\n";
}

/**
 * 重试失败的域名
 */
function retryFailed()
{
    $db = Database::getInstance();

    // 获取失败数量
    $stmt = $db->query('SELECT COUNT(*) FROM domains WHERE status = "failed"');
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "没有失败的域名需要重试\n";
        return;
    }

    echo "将 {$count} 个失败的域名重置为待处理状态...\n";

    $stmt = $db->prepare('UPDATE domains SET status = ?, error_message = NULL WHERE status = ?');
    $stmt->execute(['pending', 'failed']);

    echo "完成! 请运行 process.php 继续处理\n";
}

/**
 * 刷新所有旁站的当前IP
 */
function refreshSideSitesIp($config)
{
    $db = Database::getInstance();
    $detector = new SideSiteDetector($config);

    // 获取需要刷新的旁站数量
    $stmt = $db->query('SELECT COUNT(*) FROM side_sites WHERE current_ip IS NULL OR current_ip = ""');
    $nullCount = (int)$stmt->fetchColumn();

    $stmt = $db->query('SELECT COUNT(*) FROM side_sites');
    $totalCount = (int)$stmt->fetchColumn();

    echo "============================================\n";
    echo "        刷新旁站IP\n";
    echo "============================================\n";
    echo "总旁站数: {$totalCount}\n";
    echo "需要刷新(IP为空): {$nullCount}\n";
    echo "--------------------------------------------\n";

    if ($totalCount == 0) {
        echo "没有旁站数据\n";
        return;
    }

    echo "开始刷新...\n\n";

    $batchSize = 100;
    $processed = 0;
    $startTime = microtime(true);

    // 分批处理
    while (true) {
        $result = $detector->refreshAllSideSitesIp($batchSize);

        if ($result['updated'] == 0) {
            break;
        }

        $processed += $result['updated'];
        $elapsed = microtime(true) - $startTime;
        $rate = $elapsed > 0 ? round($processed / $elapsed, 1) : 0;

        echo sprintf(
            "[%s] 已刷新: %d / %d (%.1f/秒)\n",
            date('H:i:s'),
            $processed,
            $totalCount,
            $rate
        );

        // 检查是否全部完成
        if ($result['remaining'] == 0) {
            break;
        }
    }

    $elapsed = microtime(true) - $startTime;

    echo "\n============================================\n";
    echo "              刷新完成\n";
    echo "============================================\n";
    echo "已刷新: {$processed} 个旁站IP\n";
    echo "耗时: " . formatTime($elapsed) . "\n";
    echo "============================================\n";
}
