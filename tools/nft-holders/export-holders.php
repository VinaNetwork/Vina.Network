<?php
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once('../bootstrap.php');

session_start();
include('../api-helper.php');
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

log_message("export-holders: Request received - method={$_SERVER['REQUEST_METHOD']}, POST=" . json_encode($_POST), 'export_log.txt');

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

function exportToCSV($holders, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Address', 'Amount']);
    foreach ($holders as $holder) {
        fputcsv($output, [
            $holder['owner'] ?? 'N/A',
            $holder['amount'] ?? '0'
        ]);
    }
    fclose($output);
    exit;
}

function exportToJSON($holders, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode(array_map(function($holder) {
        return [
            'address' => $holder['owner'] ?? 'N/A',
            'amount' => $holder['amount'] ?? '0'
        ];
    }, $holders), JSON_PRETTY_PRINT);
    exit;
}

try {
    if ($export_type === 'current') {
        $holders_per_page = 50;
        $offset = ($page - 1) * $holders_per_page;
        $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);
        log_message("export-holders: Current page holders data: " . json_encode($holders_data), 'export_log.txt');
        if (isset($holders_data['error']) || empty($holders_data['holders'])) {
            log_message("export-holders: No holders found for page $page, mintAddress: $mintAddress", 'export_log.txt', 'ERROR');
            throw new Exception('No holders found for this page');
        }
        $holders = $holders_data['holders'];
        $filename = $export_format === 'csv'
            ? "holders_page_{$page}_{$mintAddress}.csv"
            : "holders_page_{$page}_{$mintAddress}.json";
        if ($export_format === 'csv') {
            exportToCSV($holders, $filename);
        } else {
            exportToJSON($holders, $filename);
        }
    } else {
        $all_holders = [];
        $api_page = 1;
        $limit = 1000;
        $has_more = true;
        while ($has_more) {
            $params = [
                'groupKey' => 'collection',
                'groupValue' => $mintAddress,
                'page' => $api_page,
                'limit' => $limit
            ];
            log_message("export-holders: Calling API for holders, page=$api_page", 'export_log.txt');
            $data = callAPI('getAssetsByGroup', $params, 'POST');
            if (isset($data['error'])) {
                log_message("export-holders: API error - " . json_encode($data['error']), 'export_log.txt', 'ERROR');
                throw new Exception('API error: ' . json_encode($data['error']));
            }
            $items = $data['result']['items'] ?? [];
            foreach ($items as $item) {
                $all_holders[] = [
                    'owner' => $item['ownership']['owner'] ?? 'unknown',
                    'amount' => 1
                ];
            }
            if (count($items) < $limit) {
                $has_more = false;
            } else {
                $api_page++;
            }
        }
        if (empty($all_holders)) {
            log_message("export-holders: No holders found for mintAddress=$mintAddress", 'export_log.txt', 'ERROR');
            throw new Exception('No holders found');
        }
        $filename = $export_format === 'csv'
            ? "holders_all_{$mintAddress}.csv"
            : "holders_all_{$mintAddress}.json";
        if ($export_format === 'csv') {
            exportToCSV($all_holders, $filename);
        } else {
            exportToJSON($all_holders, $filename);
        }
    }
} catch (Exception $e) {
    log_message("export-holders: Exception - " . $e->getMessage(), 'export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
