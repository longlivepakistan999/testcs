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

        // 获取旁站列表（带实时IP检测）
        case 'side_sites':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            $domainId = (int) ($_GET['id'] ?? 0);
            $checkIp = ($_GET['check_ip'] ?? '1') === '1';

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $result = ['data' => $detector->getSideSitesWithIpCheck($domainId, $checkIp)];
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

            if ($domainId <= 0) {
                throw new Exception('无效的域名ID', 400);
            }

            $exportData = $detector->exportSingleDomain($domainId, $format);

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

            $exportData = $detector->exportAllDomains($format, $status);

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="all_side_sites_' . date('Ymd_His') . '.csv"');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $exportData;
                exit;
            }
            $result = $exportData;
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
