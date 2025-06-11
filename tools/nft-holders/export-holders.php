<?php
define('VINANETWORK_STATUS', true);
require_once '../../tools/bootstrap.php';

session_start();
log_message('export-holders.php: Script started');

$api_helper_path = TOOLS_PATH . 'api-helper.php';
if (!file_exists($api_helper_path)) {
    log_message("export-holders.php: api-helper.php not found at $api_helper_path", 'error_log.txt', 'ERROR');
    die('Internal Server Error: Missing api-helper.php');
}
include $api_helper_path;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message('export-holders.php: Invalid request method', 'error_log.txt', 'ERROR');
    die('Invalid request method');
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'current';
$export_format = $_POST['export_format'] ?? 'csv';
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;

log_message("export-holders.php: Processing export - mintAddress=$mintAddress, type=$export_type, format=$export_format, page=$page");

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message('export-holders.php: Invalid collection address', 'error_log.txt', 'ERROR');
    die('Invalid collection address');
}

if (!in_array($export_format, ['csv', 'json'])) {
    log_message('export-holders.php: Invalid export format', 'error_log.txt', 'ERROR');
    die('Invalid export format');
}

function exportToCSV($holders, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Address', 'Amount']);
    foreach ($holders as $holder) {
        fputcsv($output, [
            $holder['owner'] ?? 'N/A',
            $holder['amount'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit;
}

function exportToJSON($holders, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $data = array_map(function($holder) {
        return [
            'address' => $holder['owner'] ?? 'N/A',
            'amount' => $holder['amount'] ?? 'N/A'
        ];
    }, $holders);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

if ($export_type === 'current') {
    $holders_per_page = 50;
    $offset = ($page - 1) * $holders_per_page;
    $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);
    
    if (isset($holders_data['error']) || empty($holders_data['holders'])) {
        log_message('export-holders.php: No holders found for this page', 'error_log.txt', 'ERROR');
        die('No holders found for this page');
    }
    
    $holders = $holders_data['holders'];
    $filename = $export_format === 'csv' 
        ? "holders_page_{$page}_{$mintAddress}.csv"
        : "holders_page_{$page}_{$mintAddress}.json";
    
    log_message("export-holders.php: Exporting current page - filename=$filename");
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
        $data = callHeliusAPI('getAssetsByGroup', $params, 'POST');
        
        if (isset($data['error'])) {
            log_message('export-holders.php: Error fetching holders: ' . json_encode($data), 'error_log.txt', 'ERROR');
            die('Error fetching holders: ' . htmlspecialchars($data['error']));
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
        log_message('export-holders.php: No holders found', 'error_log.txt', 'ERROR');
        die('No holders found');
    }
    
    $filename = $export_format === 'csv' 
        ? "holders_all_{$mintAddress}.csv"
        : "holders_all_{$mintAddress}.json";
    
    log_message("export-holders.php: Exporting all holders - filename=$filename");
    if ($export_format === 'csv') {
        exportToCSV($all_holders, $filename);
    } else {
        exportToJSON($all_holders, $filename);
    }
}
?>
