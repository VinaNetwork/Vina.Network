<?php
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap.php';
require_once '../api-helper.php';

session_start();
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

log_message("export-holders: Request received - method={$_SERVER['REQUEST_METHOD']}, POST=" . json_encode($_POST), 'export_log.txt');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("export-holders: Invalid request method", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'current';
$export_format = $_POST['export_format'] ?? 'csv';
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;

log_message("export-holders: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format, page=$page", 'export_log.txt');

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("export-holders: Invalid collection address: $mintAddress", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    log_message("export-holders: Invalid export format: $export_format", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if (!in_array($export_type, ['all', 'current'])) {
    log_message("export-holders: Invalid export type: $export_type", 'export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

function getHolders($mintAddress, $page = 1, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => $page,
        'limit' => $size
    ];
    log_message("export-holders: Fetching holders - mintAddress=$mintAddress, page=$page, size=$size", 'export_log.txt');
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    if (isset($data['error'])) {
        log_message("export-holders: API error - " . json_encode($data['error']), 'export_log.txt', 'ERROR');
        return ['error' => $data['error']];
    }
    $items = $data['result']['items'] ?? [];
    $total = $data['result']['total'] ?? count($items);
    if (empty($items)) {
        return ['holders' => [], 'total' => $total];
    }
    $holders = array_map(function($item) {
        return [
            'owner' => $item['ownership']['owner'] ?? 'unknown',
            'amount' => 1
        ];
    }, $items);
    log_message("export-holders: Fetched " . count($holders) . " holders for page $page, total=$total", 'export_log.txt');
    return ['holders' => $holders, 'total' => $total];
}

try {
    $holders = [];
    $filename = $export_format === 'csv'
        ? "holders_{$export_type}_{$mintAddress}_{$page}.csv"
        : "holders_{$export_type}_{$mintAddress}_{$page}.json";

    if ($export_type === 'current') {
        $holders_per_page = 50;
        $result = getHolders($mintAddress, $page, $holders_per_page);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $holders = $result['holders'];
        if (empty($holders)) {
            log_message("export-holders: No holders found for page $page, mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
            throw new Exception('No holders found for this page');
        }
    } else {
        $api_page = 1;
        $limit = 1000;
        $total_fetched = 0;
        $total_expected = null;

        // Get total holders first
        $result = getHolders($mintAddress, 1, 1);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $total_expected = $result['total'];
        log_message("export-holders: Total expected holders: $total_expected", 'export_log.txt');

        if ($total_expected === 0) {
            log_message("export-holders: No holders found for mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
            throw new Exception('No holders found');
        }

        // Fetch all holders
        while ($total_fetched < $total_expected) {
            $result = getHolders($mintAddress, $api_page, $limit);
            if (isset($result['error'])) {
                throw new Exception('API error: ' . json_encode($result['error']));
            }
            $page_holders = $result['holders'];
            $holders = array_merge($holders, $page_holders);
            $total_fetched += count($page_holders);
            log_message("export-holders: Fetched $total_fetched/$total_expected holders after page $api_page", 'export_log.txt');
            if (count($page_holders) < $limit) {
                break;
            }
            $api_page++;
            if ($api_page > 100) {
                log_message("export-holders: Reached safety limit at page $api_page", 'export_log.txt', 'ERROR');
                break;
            }
        }

        if (empty($holders)) {
            log_message("export-holders: No holders found for mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
            throw new Exception('No holders found');
        }
        log_message("export-holders: Total fetched holders: $total_fetched", 'export_log.txt');
    }

    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Wallet Address', 'NFT Count']);
        foreach ($holders as $holder) {
            fputcsv($output, [
                $holder['owner'] ?? 'N/A',
                $holder['amount'] ?? 0
            ]);
        }
        fclose($output);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $json_data = array_map(function($holder) {
            return [
                'address' => $holder['owner'] ?? 'N/A',
                'amount' => $holder['amount'] ?? 0
            ];
        }, $holders);
        echo json_encode($json_data, JSON_PRETTY_PRINT);
    }
    exit;
} catch (Exception $e) {
    log_message("export-holders: Exception - " . $e->getMessage(), 'export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
