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

function getHolders($mintAddress, $offset = 0, $size = 50) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => ceil(($offset + $size) / $size),
        'limit' => $size
    ];
    log_message("export-holders: Fetching holders - mintAddress=$mintAddress, offset=$offset, size=$size, page={$params['page']}", 'export_log.txt');
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    if (isset($data['error'])) {
        log_message("export-holders: API error - " . json_encode($data['error']), 'export_log.txt', 'ERROR');
        return ['error' => $data['error']];
    }
    $items = $data['result']['items'] ?? [];
    if (empty($items)) {
        return ['holders' => []];
    }
    $holders = array_map(function($item) {
        return [
            'owner' => $item['ownership']['owner'] ?? 'unknown',
            'amount' => 1
        ];
    }, $items);
    return ['holders' => $holders];
}

try {
    $holders = [];
    $filename = $export_format === 'csv'
        ? "holders_{$export_type}_{$mintAddress}_{$page}.csv"
        : "holders_{$export_type}_{$mintAddress}_{$page}.json";

    if ($export_type === 'current') {
        $holders_per_page = 50;
        $offset = ($page - 1) * $holders_per_page;
        $result = getHolders($mintAddress, $offset, $holders_per_page);
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
        $has_more = true;
        while ($has_more) {
            $result = getHolders($mintAddress, ($api_page - 1) * $limit, $limit);
            if (isset($result['error'])) {
                throw new Exception('API error: ' . json_encode($result['error']));
            }
            $page_holders = $result['holders'];
            $holders = array_merge($holders, $page_holders);
            if (count($page_holders) < $limit) {
                $has_more = false;
            } else {
                $api_page++;
            }
        }
        if (empty($holders)) {
            log_message("export-holders: No holders found for mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
            throw new Exception('No holders found');
        }
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
