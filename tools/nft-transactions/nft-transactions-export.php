<?php
// ============================================================================
// File: tools/nft-transactions/nft-transactions-export.php
// Description: Export transaction history for a Solana NFT collection.
// Created by: Vina Network
// ============================================================================

// Disable display of errors in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Define constants to mark script entry
if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Load bootstrap dependencies
$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    error_log("nft-transactions-export: bootstrap.php not found at $bootstrap_path");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal Server Error: Missing bootstrap.php']);
    exit;
}
require_once $bootstrap_path;

// Start session and configure error logging
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// Check logs directory permissions
if (!defined('LOGS_PATH') || !is_writable(LOGS_PATH)) {
    error_log("nft-transactions-export: Logs directory " . (defined('LOGS_PATH') ? LOGS_PATH : 'undefined') . " is not writable");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Logs directory is not writable']);
    exit;
}

// Log script start
log_message("nft-transactions-export: Script started", 'nft_transactions_export_log.txt', 'DEBUG');

// Rate limiting: 5 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_export:$ip";
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    log_message("nft-transactions-export: Reset rate limit for IP=$ip, count=1", 'nft_transactions_export_log.txt');
} elseif ($rate_limit_count >= 5) {
    log_message("nft-transactions-export: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limit exceeded. Please try again in a minute.']);
    exit;
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-transactions-export: Incremented rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_transactions_export_log.txt');
}

// Include tools API helper
$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-transactions-export: tools-api.php not found at $api_helper_path", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal Server Error: Missing tools-api.php']);
    exit;
}
require_once $api_helper_path;

// Validate request method and parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("nft-transactions-export: Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
if (!function_exists('validate_csrf_token') || !isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    log_message("nft-transactions-export: Invalid CSRF token", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Validate input parameters
$export_type = $_POST['export_type'] ?? '';
$export_format = $_POST['export_format'] ?? '';
$mintAddress = isset($_POST['mintAddress']) ? trim($_POST['mintAddress']) : '';
$mintAddress = preg_replace('/\s+/', '', $mintAddress); // Remove all whitespace

if ($export_type !== 'all' || !in_array($export_format, ['csv', 'json']) || empty($mintAddress)) {
    log_message("nft-transactions-export: Invalid parameters - export_type=$export_type, export_format=$export_format, mintAddress=$mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid export parameters']);
    exit;
}

// Validate address format (base58, 32–44 characters)
if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("nft-transactions-export: Invalid mintAddress format: $mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid collection address. Please enter a valid Solana collection address (32-44 characters, base58).']);
    exit;
}

// Define cache file
$cache_file = __DIR__ . '/cache/nft_transactions_cache.json';
if (!file_exists($cache_file)) {
    log_message("nft-transactions-export: Cache file not found at $cache_file", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Cache file not found']);
    exit;
}

// Check cache file permissions
if (!is_writable($cache_file)) {
    log_message("nft-transactions-export: Cache file $cache_file is not writable", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Cache file is not writable']);
    exit;
}

// Load cache data
$cache_data = [];
$cache_content = file_get_contents($cache_file);
if ($cache_content !== false) {
    $cache_data = json_decode($cache_content, true);
    if (!is_array($cache_data)) {
        $cache_data = [];
        log_message("nft-transactions-export: Failed to decode cache, resetting cache", 'nft_transactions_export_log.txt', 'ERROR');
    }
} else {
    log_message("nft-transactions-export: Failed to read cache file", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to read cache file']);
    exit;
}

// Check if cache exists for mintAddress
if (!isset($cache_data[$mintAddress]) || !isset($cache_data[$mintAddress]['transactions'])) {
    log_message("nft-transactions-export: No cached data for mintAddress=$mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No transaction data available for this collection']);
    exit;
}

$transactions = $cache_data[$mintAddress]['transactions'];
$total_transactions = $cache_data[$mintAddress]['total_transactions'] ?? 0;

if ($total_transactions === 0) {
    log_message("nft-transactions-export: No transactions found for mintAddress=$mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No transactions found for this collection']);
    exit;
}

// Export data based on format
if ($export_format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_all_' . htmlspecialchars($mintAddress) . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Signature', 'Time', 'Price (SOL)', 'Buyer', 'Seller']);

    foreach ($transactions as $tx) {
        fputcsv($output, [
            $tx['signature'] ?? 'N/A',
            $tx['timestamp'] ?? 'N/A',
            isset($tx['price']) ? number_format($tx['price'], 2) : 0,
            $tx['buyer'] ?? 'N/A',
            $tx['seller'] ?? 'N/A'
        ]);
    }
    fclose($output);
    log_message("nft-transactions-export: Exported $total_transactions transactions as CSV for mintAddress=$mintAddress", 'nft_transactions_export_log.txt');
} elseif ($export_format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="transactions_all_' . htmlspecialchars($mintAddress) . '.json"');

    echo json_encode($transactions, JSON_PRETTY_PRINT);
    log_message("nft-transactions-export: Exported $total_transactions transactions as JSON for mintAddress=$mintAddress", 'nft_transactions_export_log.txt');
}

exit;
?>
