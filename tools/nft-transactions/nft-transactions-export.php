<?php
// ============================================================================
// File: nft-transactions/nft-transactions-export.php
// Description: Handles export requests for NFT transaction data (CSV/JSON format).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap1.php';
require_once '../tools-api1.php';

session_start();
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG_PATH);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Define log path
define('EXPORT_LOG_PATH', LOGS_PATH . 'transactions_export_log.txt');
log_message("export-transactions: Script started", 'transactions_export_log.txt');

// Define cache file
$cache_file = __DIR__ . '/cache/nft_transactions_cache.json';

// Validate cache file
if (!file_exists($cache_file)) {
    log_message("export-transactions: Cache file $cache_file does not exist", 'transactions_export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Cache file missing']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("export-transactions: Invalid request method", 'transactions_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Parse and validate parameters
$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'all';
$export_format = $_POST['export_format'] ?? 'csv';

log_message("export-transactions: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format", 'transactions_export_log.txt');

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("export-transactions: Invalid collection address: $mintAddress", 'transactions_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    log_message("export-transactions: Invalid export format: $export_format", 'transactions_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if ($export_type !== 'all') {
    log_message("export-transactions: Invalid export type: $export_type", 'transactions_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

try {
    $transactions = [];
    $filename = $export_format === 'csv'
        ? "transactions_all_{$mintAddress}.csv"
        : "transactions_all_{$mintAddress}.json";

    // Load cache from file
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if (!is_array($cache_data)) {
        log_message("export-transactions: Failed to parse cache file, initializing empty cache", 'transactions_export_log.txt', 'ERROR');
        $cache_data = [];
    }

    // Check file cache
    $cache_expiration = 3 * 3600; // 3 hours
    if (isset($cache_data[$mintAddress]) && 
        isset($cache_data[$mintAddress]['timestamp']) && 
        (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration)) {
        $total_transactions = $cache_data[$mintAddress]['total_transactions'] ?? 0;
        $transactions = $cache_data[$mintAddress]['transactions'] ?? [];
        log_message("export-transactions: Using file cache for mintAddress=$mintAddress, total_transactions=$total_transactions", 'transactions_export_log.txt');
    } else {
        log_message("export-transactions: No valid file cache found for mintAddress=$mintAddress", 'transactions_export_log.txt');
        throw new Exception('No valid cache data found. Please check transactions first.');
    }

    // Validate transactions
    if (empty($transactions)) {
        log_message("export-transactions: No transactions found for mintAddress=$mintAddress", 'transactions_export_log.txt', 'ERROR');
        throw new Exception('No transactions found');
    }

    // Output based on requested format
    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception('Failed to open output stream');
        }
        fputcsv($output, ['Signature', 'Time', 'Price (SOL)', 'Buyer', 'Seller']);
        foreach ($transactions as $tx) {
            fputcsv($output, [
                $tx['signature'] ?? 'N/A',
                $tx['timestamp'] ?? 'N/A',
                $tx['price'] ?? 0,
                $tx['buyer'] ?? 'N/A',
                $tx['seller'] ?? 'N/A'
            ]);
        }
        fclose($output);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $json_data = array_map(function($tx) {
            return [
                'signature' => $tx['signature'] ?? 'N/A',
                'timestamp' => $tx['timestamp'] ?? 'N/A',
                'price' => $tx['price'] ?? 0,
                'buyer' => $tx['buyer'] ?? 'N/A',
                'seller' => $tx['seller'] ?? 'N/A'
            ];
        }, $transactions);
        echo json_encode($json_data, JSON_PRETTY_PRINT);
    }
    exit;
} catch (Exception $e) {
    log_message("export-transactions: Exception - " . $e->getMessage(), 'transactions_export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
?>
