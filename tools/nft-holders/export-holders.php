<?php
// export-holders.php
// Điều kiện để truy cập config.php
define('VINANETWORK_ENTRY', true);
require_once '../../config/config.php';

// ...
session_start();
include '../config/config.php';
include '../api-helper.php';

ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'current';
$export_format = $_POST['export_format'] ?? 'csv';
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    die('Invalid collection address');
}

if (!in_array($export_format, ['csv', 'json'])) {
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
    // Export trang hiện tại
    $holders_per_page = 50;
    $offset = ($page - 1) * $holders_per_page;
    $holders_data = getNFTHolders($mintAddress, $offset, $holders_per_page);
    
    if (isset($holders_data['error']) || empty($holders_data['holders'])) {
        die('No holders found for this page');
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
    // Export toàn bộ holders
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
        die('No holders found');
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
?>
