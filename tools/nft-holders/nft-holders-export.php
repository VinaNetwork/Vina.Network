<?php
// ============================================================================
// File: tools/nft-holders/nft-holders-export.php
// Description: Handles NFT holder export (CSV/JSON). Validates inputs,
//              loads from cache or fetches from API, processes data by wallet,
//              and generates downloadable output with full logging.
// Created by: Vina Network Development Team
// ============================================================================

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap.php';
require_once '../tools-api.php';

session_start();
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/vinanetwork/public_html/nft-holders/nfts/logs/php_error.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Define log path
define('EXPORT_LOG_PATH', '/var/www/vinanetwork/public_html/nft-holders/nfts/logs/holders_export_log.txt');
file_put_contents(EXPORT_LOG_PATH, "export-holders: Script started - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Define cache file
$cache_file = __DIR__ . '/cache/nft_holders_cache.json';

// Validate cache file
if (!file_exists($cache_file)) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Cache file $cache_file does not exist - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Cache file missing']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid request method - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Parse and validate parameters
$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'all';
$export_format = $_POST['export_format'] ?? 'csv';

file_put_contents(EXPORT_LOG_PATH, "export-holders: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid collection address: $mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid export format: $export_format - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if ($export_type !== 'all') {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid export type: $export_type - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

/**
 * getItems - Fetches paginated NFT ownership data from API
 */
function getItems($mintAddress, $page = 1, $size = 100) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => $page,
        'limit' => $size
    ];
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Fetching items - mintAddress=$mintAddress, page=$page, size=$size, params=" . json_encode($params) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    if (isset($data['error'])) {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: API error - " . json_encode($data['error']) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return ['error' => $data['error']];
    }
    $items = $data['result']['items'] ?? [];
    $total = $data['result']['total'] ?? $data['result']['totalItems'] ?? count($items);
    file_put_contents(EXPORT_LOG_PATH, "export-holders: API response - total=$total, items_count=" . count($items) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $nft_items = array_map(function($item) {
        if (!isset($item['ownership']['owner'])) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: Invalid item structure, missing owner: " . json_encode($item) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            return null;
        }
        return [
            'owner' => $item['ownership']['owner'],
            'amount' => 1
        ];
    }, $items);
    $nft_items = array_filter($nft_items);
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Fetched " . count($nft_items) . " items for page $page, total=$total - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    return ['items' => $nft_items, 'total' => $total];
}

try {
    $items = [];
    $filename = $export_format === 'csv'
        ? "holders_all_{$mintAddress}.csv"
        : "holders_all_{$mintAddress}.json";

    // Load cache from file
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if (!is_array($cache_data)) {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Failed to parse cache file, initializing empty cache - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $cache_data = [];
    }

    // Check file cache
    $cache_expiration = 3 * 3600; // 3 hours
    if (isset($cache_data[$mintAddress]) && 
        isset($cache_data[$mintAddress]['timestamp']) && 
        (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration)) {
        $total_expected = $cache_data[$mintAddress]['total_items'] ?? 0;
        $items = $cache_data[$mintAddress]['items'] ?? [];
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Using file cache for mintAddress=$mintAddress, total_expected=$total_expected - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    } else {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: No valid file cache found for mintAddress=$mintAddress, fetching new data - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        ini_set('memory_limit', '512M');
        $result = getItems($mintAddress, 1, 100);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $total_expected = $result['total'];

        if ($total_expected === 0) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: No items found for mintAddress=$mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            throw new Exception('No items found');
        }

        // Fetch paginated data
        $api_page = 1;
        $limit = 100;
        $total_fetched = 0;
        while ($total_fetched < $total_expected && $api_page <= 100) {
            $result = getItems($mintAddress, $api_page, $limit);
            if (isset($result['error'])) {
                throw new Exception('API error: ' . json_encode($result['error']));
            }
            $page_items = $result['items'];
            $items = array_merge($items, $page_items);
            $total_fetched += count($page_items);
            file_put_contents(EXPORT_LOG_PATH, "export-holders: Page $api_page - Fetched $total_fetched/$total_expected items - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            if (count($page_items) == 0) {
                file_put_contents(EXPORT_LOG_PATH, "export-holders: No more items on page $api_page, stopping - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                break;
            }
            $api_page++;
            usleep(2000000); // 2-second delay
        }

        // Update file cache
        $cache_data[$mintAddress] = [
            'total_items' => $total_fetched,
            'items' => $items,
            'timestamp' => time()
        ];
        if (file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT)) === false) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: Failed to write cache file for mintAddress=$mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            throw new Exception('Failed to save cache data');
        }
        file_put_contents(EXPORT_LOG_PATH, "export-holders: Cached total_items=$total_fetched for mintAddress=$mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }

    // Validate items
    if (empty($items)) {
        file_put_contents(EXPORT_LOG_PATH, "export-holders: No items found for mintAddress=$mintAddress - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        throw new Exception('No items found');
    }

    // Group by wallet address
    $total_items = count($items);
    $unique_wallets = [];
    foreach ($items as $item) {
        if (!isset($item['owner'])) {
            file_put_contents(EXPORT_LOG_PATH, "export-holders: Skipping invalid item during deduplication: " . json_encode($item) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            continue;
        }
        $owner = $item['owner'];
        if (!isset($unique_wallets[$owner])) {
            $unique_wallets[$owner] = $item;
        } else {
            $unique_wallets[$owner]['amount'] += 1;
        }
    }
    $wallets = array_values($unique_wallets);
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Total items fetched: $total_items, Total unique wallets: " . count($wallets) . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

    // Output based on requested format
    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception('Failed to open output stream');
        }
        fputcsv($output, ['Wallet Address', 'NFT Count']);
        foreach ($wallets as $wallet) {
            fputcsv($output, [
                $wallet['owner'] ?? 'N/A',
                $wallet['amount'] ?? 0
            ]);
        }
        fclose($output);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $json_data = array_map(function($wallet) {
            return [
                'address' => $wallet['owner'] ?? 'N/A',
                'amount' => $wallet['amount'] ?? 0
            ];
        }, $wallets);
        echo json_encode($json_data, JSON_PRETTY_PRINT);
    }
    exit;
} catch (Exception $e) {
    file_put_contents(EXPORT_LOG_PATH, "export-holders: Exception - " . $e->getMessage() . " - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
?>
