<?php
// export-holders.php
require_once '/var/www/vinanetwork/public_html/tools/bootstrap.php';
require_once '/var/www/vinanetwork/public_html/tools/api-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token');
}

$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = in_array($_POST['export_type'] ?? '', ['current', 'all']) ? $_POST['export_type'] : 'current';
$export_format = in_array($_POST['export_format'] ?? '', ['csv', 'json']) ? $_POST['export_format'] : 'csv';
$page = max(1, (int)($_POST['page'] ?? 1));

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    die('Invalid collection address');
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
    $total_holders = 0;
    $holders_data = getAllHolders($mintAddress, $total_holders);
    
    if (isset($holders_data['error']) || empty($holders_data['holders'])) {
        die('No holders found');
    }
    
    $holders = $holders_data['holders'];
    $filename = $export_format === 'csv' 
        ? "holders_all_{$mintAddress}.csv"
        : "holders_all_{$mintAddress}.json";
    
    if ($export_format === 'csv') {
        exportToCSV($holders, $filename);
    } else {
        exportToJSON($holders, $filename);
    }
}
?>
