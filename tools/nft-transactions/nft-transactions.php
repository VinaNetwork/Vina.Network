<?php
// File: tools/nft-transactions/nft-transactions-export.php
// Description: Export transaction history for a Solana NFT collection.
// Created by: Vina Network

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!defined('VINANETWORK')) define('VINANETWORK', true);
if (!defined('VINANETWORK_ENTRY')) define('VINANETWORK_ENTRY', true);

$bootstrap_path = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrap_path)) {
    log_message("nft-transactions-export: bootstrap.php not found at $bootstrap_path", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'System error: bootstrap.php missing']);
    exit;
}
require_once $bootstrap_path;

session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

if (!defined('LOGS_PATH') || !is_writable(LOGS_PATH)) {
    log_message("nft-transactions-export: Logs directory " . (defined('LOGS_PATH') ? LOGS_PATH : 'undefined') . " is not writable", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Logs directory is not writable']);
    exit;
}

log_message("nft-transactions-export: Script started", 'nft_transactions_export_log.txt', 'DEBUG');

$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_export:$ip";
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['count'] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key]['time'] : 0;
if (time() - $rate_limit_time > 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    log_message("nft-transactions-export: Reset rate limit for IP=$ip, count=1", 'nft_transactions_export_log.txt');
} elseif ($rate_limit_count >= 3) {
    log_message("nft-transactions-export: Rate limit exceeded for IP=$ip, count=$rate_limit_count", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Export rate limit exceeded. Please try again after 1 minute.']);
    exit;
} else {
    $_SESSION[$rate_limit_key]['count']++;
    log_message("nft-transactions-export: Increment rate limit for IP=$ip, count=" . $_SESSION[$rate_limit_key]['count'], 'nft_transactions_export_log.txt');
}

$api_helper_path = dirname(__DIR__) . '/tools-api.php';
if (!file_exists($api_helper_path)) {
    log_message("nft-transactions-export: tools-api.php not found at $api_helper_path", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'System error: tools-api.php missing']);
    exit;
}
require_once $api_helper_path;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("nft-transactions-export: Invalid request method: {$_SERVER['REQUEST_METHOD']}", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    log_message("nft-transactions-export: Invalid CSRF token", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$export_type = $_POST['export_type'] ?? '';
$export_format = $_POST['export_format'] ?? '';
$mintAddress = isset($_POST['mintAddress']) ? trim($_POST['mintAddress']) : '';
$mintAddress = preg_replace('/\s+/', '', $mintAddress);

if ($export_type !== 'all' || !in_array($export_format, ['csv', 'json']) || empty($mintAddress)) {
    log_message("nft-transactions-export: Invalid parameters - export_type=$export_type, export_format=$export_format, mintAddress=$mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid export parameters']);
    exit;
}

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("nft-transactions-export: Invalid mintAddress format: $mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid collection address. Please enter a valid Solana collection address (32-44 chars, base58).']);
    exit;
}

$cache_file = __DIR__ . '/cache/nft_transactions_cache.json';
if (!file_exists($cache_file)) {
    log_message("nft-transactions-export: Cache file not found at $cache_file", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Cache file not found']);
    exit;
}

if (!is_writable($cache_file)) {
    log_message("nft-transactions-export: Cache file $cache_file is not writable", 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Cache file is not writable']);
    exit;
}

function getTransactions($mintAddress, $limit = 100, $max_pages = 5) {
    $transactions = [];
    $total_transactions = 0;
    $api_page = 1;
    $has_more = true;
    $before_signature = null;

    while ($has_more && $api_page <= $max_pages) {
        $params = [
            'address' => $mintAddress,
            'limit' => $limit
        ];
        if ($before_signature) {
            $params['before'] = $before_signature;
        }
        log_message("nft-transactions-export: Calling getTransactionHistory API, page=$api_page, params=" . json_encode($params), 'nft_transactions_export_log.txt', 'DEBUG');
        $data = callAPI('getTransactionHistory', $params, 'POST');
        log_message("nft-transactions-export: getTransactionHistory API response, page=$api_page: " . json_encode($data, JSON_PRETTY_PRINT), 'nft_transactions_export_log.txt', 'DEBUG');

        if (isset($data['error'])) {
            $errorMessage = is_array($data['error']) && isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']);
            log_message("nft-transactions-export: getTransactionHistory API error: $errorMessage", 'nft_transactions_export_log.txt', 'ERROR');
            throw new Exception("API error: $errorMessage");
        }

        if (!isset($data['result'])) {
            log_message("nft-transactions-export: Invalid API response, no result found for page=$api_page, mintAddress=$mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
            throw new Exception("Invalid API response: No data found.");
        }

        $page_txs = array_filter($data['result'], function($tx) {
            $is_nft_related = isset($tx['events']['nft']) || 
                              isset($tx['events']['compressed']) || 
                              in_array($tx['type'] ?? '', ['NFT_SALE', 'NFT_BID', 'NFT_TRANSFER', 'COMPRESSED_NFT_MINT', 'NFT_MINT', 'TRANSFER']);
            log_message("nft-transactions-export: Transaction signature={$tx['signature']}, type={$tx['type']}, is_nft_related=" . ($is_nft_related ? 'true' : 'false'), 'nft_transactions_export_log.txt', 'DEBUG');
            return $is_nft_related;
        });

        $tx_count = count($page_txs);
        $total_transactions += $tx_count;
        $transactions = array_merge($transactions, array_map(function($tx) {
            $nft_event = $tx['events']['nft'] ?? $tx['events']['compressed'][0] ?? [];
            return [
                'signature' => $tx['signature'] ?? 'N/A',
                'timestamp' => isset($tx['timestamp']) ? date('d M Y, H:i', $tx['timestamp']) : 'N/A',
                'price' => isset($nft_event['amount']) ? $nft_event['amount'] / 1e9 : 0,
                'buyer' => $nft_event['buyer'] ?? $tx['events']['compressed'][0]['newLeafOwner'] ?? 'N/A',
                'seller' => $nft_event['seller'] ?? $tx['events']['compressed'][0]['oldLeafOwner'] ?? 'N/A',
                'type' => $tx['type'] ?? 'N/A'
            ];
        }, $page_txs));

        $last_tx = end($page_txs);
        $before_signature = $last_tx ? $last_tx['signature'] : null;
        $has_more = $tx_count >= $limit;
        $api_page++;
        log_message("nft-transactions-export: Page $api_page added $tx_count transactions, total_transactions=$total_transactions", 'nft_transactions_export_log.txt');
        usleep(1000000); // 1s delay
    }

    return ['transactions' => $transactions, 'total_transactions' => $total_transactions];
}

try {
    $cache_data = [];
    $cache_content = file_get_contents($cache_file);
    if ($cache_content !== false) {
        $cache_data = json_decode($cache_content, true);
        if (!is_array($cache_data)) {
            $cache_data = [];
            log_message("nft-transactions-export: Could not decode cache, resetting cache", 'nft_transactions_export_log.txt', 'ERROR');
        }
    } else {
        log_message("nft-transactions-export: Could not read cache file", 'nft_transactions_export_log.txt', 'ERROR');
        throw new Exception("Could not read cache file");
    }

    $cache_expiration = 3 * 3600;
    $transactions = [];
    $total_transactions = 0;

    if (isset($cache_data[$mintAddress]) && 
        isset($cache_data[$mintAddress]['timestamp']) && 
        (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration)) {
        $transactions = $cache_data[$mintAddress]['transactions'] ?? [];
        $total_transactions = $cache_data[$mintAddress]['total_transactions'] ?? 0;
        log_message("nft-transactions-export: Using cache for mintAddress=$mintAddress, total_transactions=$total_transactions", 'nft_transactions_export_log.txt');
    } else {
        log_message("nft-transactions-export: No valid cache for mintAddress=$mintAddress, fetching new data", 'nft_transactions_export_log.txt');
        $result = getTransactions($mintAddress);
        $transactions = $result['transactions'];
        $total_transactions = $result['total_transactions'];

        $cache_data[$mintAddress] = [
            'total_transactions' => $total_transactions,
            'transactions' => $transactions,
            'timestamp' => time()
        ];
        $fp = fopen($cache_file, 'c');
        if (flock($fp, LOCK_EX)) {
            if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
                log_message("nft-transactions-export: Could not write cache to $cache_file", 'nft_transactions_export_log.txt', 'ERROR');
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception("Could not save cache data");
            }
            flock($fp, LOCK_UN);
        } else {
            log_message("nft-transactions-export: Could not lock cache file $cache_file", 'nft_transactions_export_log.txt', 'ERROR');
            fclose($fp);
            throw new Exception("Could not lock cache file");
        }
        fclose($fp);
        log_message("nft-transactions-export: Cached total_transactions=$total_transactions for $mintAddress", 'nft_transactions_export_log.txt');
    }

    if ($total_transactions === 0) {
        log_message("nft-transactions-export: No transactions found for mintAddress=$mintAddress", 'nft_transactions_export_log.txt', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No transactions found for this collection']);
        exit;
    }

    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions_all_' . htmlspecialchars($mintAddress) . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Signature', 'Timestamp', 'Price (SOL)', 'Buyer', 'Seller', 'Type']);
        foreach ($transactions as $tx) {
            fputcsv($output, [
                $tx['signature'] ?? 'N/A',
                $tx['timestamp'] ?? 'N/A',
                number_format($tx['price'], 2),
                $tx['buyer'] ?? 'N/A',
                $tx['seller'] ?? 'N/A',
                $tx['type'] ?? 'N/A'
            ]);
        }
        fclose($output);
        log_message("nft-transactions-export: Exported $total_transactions transactions as CSV for mintAddress=$mintAddress", 'nft_transactions_export_log.txt');
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions_all_' . htmlspecialchars($mintAddress) . '.json"');
        echo json_encode($transactions, JSON_PRETTY_PRINT);
        log_message("nft-transactions-export: Exported $total_transactions transactions as JSON for mintAddress=$mintAddress", 'nft_transactions_export_log.txt');
    }
    exit;
} catch (Exception $e) {
    log_message("nft-transactions-export: Exception - " . $e->getMessage(), 'nft_transactions_export_log.txt', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
?>
