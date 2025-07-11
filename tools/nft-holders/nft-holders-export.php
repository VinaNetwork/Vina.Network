<?php
// ============================================================================
// File: tools/nft-holders/nft-holders-export.php
// Description: This script handles export requests for NFT holder data (CSV/JSON format).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK')) {
    define('VINANETWORK', true);
}
define('VINANETWORK_ENTRY', true);
require_once '../bootstrap.php';
require_once '../tools-api.php';

// Define cache directory and file
$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . 'nft_holders_cache.json';

// Check and create cache directory and file
if (!ensure_directory_and_file($cache_dir, $cache_file, 'holders_export_log.txt')) {
    log_message("export-holders: Cache setup failed for $cache_dir or $cache_file", 'holders_export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Cache setup failed']);
    exit;
}

log_message("export-holders: Script started", 'holders_export_log.txt', 'INFO');

header('Content-Type: application/json; charset=utf-8');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("export-holders: Invalid request method", 'holders_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Parse and validate parameters
$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'all';
$export_format = $_POST['export_format'] ?? 'csv';

log_message("export-holders: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format", 'holders_export_log.txt', 'DEBUG');

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("export-holders: Invalid collection address: $mintAddress", 'holders_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    log_message("export-holders: Invalid export format: $export_format", 'holders_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if ($export_type !== 'all') {
    log_message("export-holders: Invalid export type: $export_type", 'holders_export_log.txt', 'ERROR');
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
    log_message("export-holders: Fetching items - mintAddress=$mintAddress, page=$page, size=$size, params=" . json_encode($params), 'holders_export_log.txt', 'DEBUG');
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    if (isset($data['error'])) {
        log_message("export-holders: API error - " . json_encode($data['error']), 'holders_export_log.txt', 'ERROR');
        return ['error' => $data['error']];
    }
    $items = $data['result']['items'] ?? [];
    $total = $data['result']['total'] ?? $data['result']['totalItems'] ?? count($items);
    log_message("export-holders: API response - total=$total, items_count=" . count($items), 'holders_export_log.txt', 'DEBUG');
    $nft_items = array_map(function($item) {
        if (!isset($item['ownership']['owner'])) {
            log_message("export-holders: Invalid item structure, missing owner: " . json_encode($item), 'holders_export_log.txt', 'WARNING');
            return null;
        }
        return [
            'owner' => $item['ownership']['owner'],
            'amount' => 1
        ];
    }, $items);
    $nft_items = array_filter($nft_items);
    log_message("export-holders: Fetched " . count($nft_items) . " items for page $page, total=$total", 'holders_export_log.txt', 'INFO');
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
        log_message("export-holders: Failed to parse cache file, initializing empty cache", 'holders_export_log.txt', 'ERROR');
        $cache_data = [];
    }

    // Check file cache
    $cache_expiration = 3 * 3600; // 3 hours
    if (isset($cache_data[$mintAddress]) && 
        isset($cache_data[$mintAddress]['timestamp']) && 
        (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration)) {
        $total_expected = $cache_data[$mintAddress]['total_items'] ?? 0;
        $items = $cache_data[$mintAddress]['items'] ?? [];
        log_message("export-holders: Using file cache for mintAddress=$mintAddress, total_expected=$total_expected", 'holders_export_log.txt', 'INFO');
    } else {
        log_message("export-holders: No valid file cache found for mintAddress=$mintAddress, fetching new data", 'holders_export_log.txt', 'INFO');
        ini_set('memory_limit', '512M');
        $result = getItems($mintAddress, 1, 100);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . json_encode($result['error']));
        }
        $total_expected = $result['total'];

        if ($total_expected === 0) {
            log_message("export-holders: No items found for mintAddress=$mintAddress", 'holders_export_log.txt', 'ERROR');
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
            log_message("export-holders: Page $api_page - Fetched $total_fetched/$total_expected items", 'holders_export_log.txt', 'INFO');
            if (count($page_items) == 0) {
                log_message("export-holders: No more items on page $api_page, stopping", 'holders_export_log.txt', 'INFO');
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
            log_message("export-holders: Failed to write cache file for mintAddress=$mintAddress", 'holders_export_log.txt', 'ERROR');
            throw new Exception('Failed to save cache data');
        }
        log_message("export-holders: Cached total_items=$total_fetched for mintAddress=$mintAddress", 'holders_export_log.txt', 'INFO');
    }

    // Validate items
    if (empty($items)) {
        log_message("export-holders: No items found for mintAddress=$mintAddress", 'holders_export_log.txt', 'ERROR');
        throw new Exception('No items found');
    }

    // Group by wallet address
    $total_items = count($items);
    $unique_wallets = [];
    foreach ($items as $item) {
        if (!isset($item['owner'])) {
            log_message("export-holders: Skipping invalid item during deduplication: " . json_encode($item), 'holders_export_log.txt', 'WARNING');
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
    log_message("export-holders: Total items fetched: $total_items, Total unique wallets: " . count($wallets), 'holders_export_log.txt', 'INFO');

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
    log_message("export-holders: Exception - " . $e->getMessage(), 'holders_export_log.txt', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
?>
