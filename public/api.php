<?php
/**
 * API接口
 * RESTful API Endpoints
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = require __DIR__ . '/../bootstrap.php';

use App\SideSiteDetector;

$detector = new SideSiteDetector($config);

// 获取请求参数
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        // 获取域名列表
        case 'list':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);
            $status = $_GET['status'] ?? null;
            $result = $detector->getDomainList($page, $perPage, $status);
            break;

        // 添加域名
        case 'add':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $domain = $input['domain'] ?? '';

            if (empty($domain)) {
                throw new Exception('域名不能为空', 400);
            }

            // 支持批量添加（按行分隔）
            $domains = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $domain)));

            if (count($domains) === 1) {
                $result = $detector->addDomain($domains[0]);
            } else {
                $result = ['results' => $detector->addDomains($domains)];
            }
            break;

        // 检测单个域名
        case 'detect':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $domainId = (int) ($input['id'] ?? 0);

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $result = $detector->detectDomain($domainId);
            break;

        // 处理队列
        case 'process':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $limit = (int) ($input['limit'] ?? 10);
            $result = ['results' => $detector->processQueue($limit)];
            break;

        // 获取旁站列表（带实时IP检测，支持分页）
        case 'side_sites':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $domainId = (int) ($_GET['id'] ?? 0);
            $checkIp = ($_GET['check_ip'] ?? '1') === '1';
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 100);

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $result = ['data' => $detector->getSideSitesWithIpCheck($domainId, $checkIp, $page, $perPage)];
            break;

        // 获取统计数据
        case 'statistics':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $result = $detector->getStatistics();
            break;

        // 删除域名
        case 'delete':
            if ($method !== 'DELETE' && $method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $domainId = (int) ($input['id'] ?? $_GET['id'] ?? 0);

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $success = $detector->deleteDomain($domainId);
            $result = ['success' => $success, 'message' => $success ? '删除成功' : '删除失败'];
            break;

        // 重新检测
        case 'retry':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $domainId = (int) ($input['id'] ?? 0);

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $result = $detector->retryDomain($domainId);
            break;

        // 导出单个域名的旁站
        case 'export_single':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $domainId = (int) ($_GET['id'] ?? 0);
            $format = $_GET['format'] ?? 'csv';
            $ipFilter = $_GET['ip_filter'] ?? 'all'; // all, match, mismatch

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $exportData = $detector->exportSingleDomain($domainId, $format, $ipFilter);

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="side_sites_' . $domainId . '_' . date('Ymd_His') . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $exportData;
                exit;
            }
            $result = $exportData;
            break;

        // 导出所有域名的旁站
        case 'export_all':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $format = $_GET['format'] ?? 'csv';
            $status = $_GET['status'] ?? 'completed';
            $checkIp = ($_GET['check_ip'] ?? '0') === '1';
            $stream = ($_GET['stream'] ?? '1') === '1'; // 默认使用流式导出

            if ($format === 'csv' && $stream) {
                // 流式导出（适合大数据量）
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="all_side_sites_' . date('Ymd_His') . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                $output = fopen('php://output', 'w');
                $detector->streamExportAllDomains($output, $status, $checkIp);
                fclose($output);
                exit;
            }

            $exportData = $detector->exportAllDomains($format, $status, $checkIp);

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="all_side_sites_' . date('Ymd_His') . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $exportData;
                exit;
            }
            $result = $exportData;
            break;

        // 获取导出统计（预估大小）
        case 'export_stats':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $status = $_GET['status'] ?? 'completed';
            $result = $detector->getExportStats($status);
            break;

        // 文件上传导入域名
        case 'import':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('文件上传失败', 400);
            }

            $file = $_FILES['file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ['txt', 'csv'])) {
                throw new Exception('只支持 TXT 或 CSV 文件', 400);
            }

            $content = file_get_contents($file['tmp_name']);
            $domains = [];

            if ($extension === 'csv') {
                $handle = fopen($file['tmp_name'], 'r');
                $isFirstRow = true;
                while (($row = fgetcsv($handle)) !== false) {
                    if ($isFirstRow) {
                        $isFirstRow = false;
                        // 检查是否是表头
                        if (!empty($row[0]) && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', trim($row[0]))) {
                            continue;
                        }
                    }
                    if (!empty($row[0])) {
                        $domains[] = trim($row[0]);
                    }
                }
                fclose($handle);
            } else {
                $lines = preg_split('/[\r\n]+/', $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && strpos($line, '#') !== 0) {
                        $domains[] = $line;
                    }
                }
            }

            $domains = array_filter($domains);
            $totalCount = count($domains);

            if ($totalCount === 0) {
                throw new Exception('文件中没有找到有效的域名', 400);
            }

            // 批量导入
            $inserted = 0;
            $skipped = 0;
            $invalid = 0;

            foreach ($domains as $domain) {
                $addResult = $detector->addDomain($domain);
                if ($addResult['success']) {
                    $inserted++;
                } elseif (strpos($addResult['message'] ?? '', '无效') !== false) {
                    $invalid++;
                } else {
                    $skipped++;
                }
            }

            $result = [
                'success' => true,
                'message' => "导入完成",
                'total' => $totalCount,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'invalid' => $invalid,
            ];
            break;

        // 导出域名列表（不含旁站）
        case 'export_domains':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $format = $_GET['format'] ?? 'csv';
            $status = $_GET['status'] ?? null;

            $exportData = $detector->exportDomainList($format, $status);

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="domains_' . date('Ymd_His') . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $exportData;
                exit;
            }
            $result = $exportData;
            break;

        // 批量重试选中的域名
        case 'batch_retry':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                throw new Exception('请选择要重试的域名', 400);
            }

            $count = $detector->batchRetry($ids);
            $result = ['success' => true, 'count' => $count];
            break;

        // 重试所有失败的域名
        case 'retry_all_failed':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $count = $detector->retryAllFailed();
            $result = ['success' => true, 'count' => $count];
            break;

        // 批量导出选中的域名
        case 'batch_export':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $idsStr = $_GET['ids'] ?? '';
            $format = $_GET['format'] ?? 'csv';

            if (empty($idsStr)) {
                throw new Exception('请选择要导出的域名', 400);
            }

            $ids = array_map('intval', explode(',', $idsStr));
            $ids = array_filter($ids, fn($id) => $id > 0);

            if (empty($ids)) {
                throw new Exception('无效的域名ID', 400);
            }

            $exportData = $detector->batchExport($ids, $format);

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="batch_export_' . date('Ymd_His') . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $exportData;
                exit;
            }
            $result = $exportData;
            break;

        // 批量删除选中的域名
        case 'batch_delete':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                throw new Exception('请选择要删除的域名', 400);
            }

            $count = $detector->batchDelete($ids);
            $result = ['success' => true, 'count' => $count];
            break;

        default:
            throw new Exception('Unknown action', 400);
    }

    echo json_encode(['code' => 0, 'data' => $result], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'code' => $e->getCode() ?: 500,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
