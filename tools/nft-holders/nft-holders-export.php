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
$export_type = $_POST['export_type'] ?? 'all'; // all | wallets_only
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

if (!in_array($export_type, ['all', 'wallets_only'])) {
    log_message("export-holders: Invalid export type: $export_type", 'holders_export_log.txt', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

function getItems($mintAddress, $page = 1, $size = 100) {
    $params = [
        'groupKey' => 'collection',
        'groupValue' => $mintAddress,
        'page' => $page,
        'limit' => $size
    ];
    log_message("export-holders: Fetching items - mintAddress=$mintAddress, page=$page, size=$size", 'holders_export_log.txt', 'DEBUG');
    $data = callAPI('getAssetsByGroup', $params, 'POST');
    if (isset($data['error'])) {
        log_message("export-holders: API error - " . json_encode($data['error']), 'holders_export_log.txt', 'ERROR');
        return ['error' => $data['error']];
    }
    $items = $data['result']['items'] ?? [];
    $total = $data['result']['total'] ?? $data['result']['totalItems'] ?? count($items);
    $nft_items = array_map(function($item) {
        if (!isset($item['ownership']['owner'])) return null;
        return [
            'owner' => $item['ownership']['owner'],
            'amount' => 1
        ];
    }, $items);
    return ['items' => array_filter($nft_items), 'total' => $total];
}

try {
    $items = [];
    $filename = ($export_format === 'csv')
        ? "holders_{$export_type}_{$mintAddress}.csv"
        : "holders_{$export_type}_{$mintAddress}.json";

    $cache_data = json_decode(file_get_contents($cache_file), true);
    if (!is_array($cache_data)) $cache_data = [];

    $cache_expiration = 3 * 3600;
    if (isset($cache_data[$mintAddress]) && isset($cache_data[$mintAddress]['timestamp']) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration)) {
        $items = $cache_data[$mintAddress]['items'] ?? [];
    } else {
        $result = getItems($mintAddress);
        if (isset($result['error'])) throw new Exception('API error: ' . json_encode($result['error']));
        if (($result['total'] ?? 0) === 0) throw new Exception('No items found');

        $api_page = 1;
        $limit = 100;
        $total_expected = $result['total'];
        $total_fetched = 0;

        while ($total_fetched < $total_expected && $api_page <= 100) {
            $result = getItems($mintAddress, $api_page, $limit);
            if (isset($result['error'])) throw new Exception('API error: ' . json_encode($result['error']));
            $page_items = $result['items'];
            $items = array_merge($items, $page_items);
            $total_fetched += count($page_items);
            if (count($page_items) == 0) break;
            $api_page++;
            usleep(2000000);
        }

        $cache_data[$mintAddress] = [
            'total_items' => $total_fetched,
            'items' => $items,
            'timestamp' => time()
        ];
        file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
    }

    if (empty($items)) throw new Exception('No items found');

    $unique_wallets = [];
    foreach ($items as $item) {
        if (!isset($item['owner'])) continue;
        $owner = $item['owner'];
        if (!isset($unique_wallets[$owner])) {
            $unique_wallets[$owner] = $item;
        } else {
            $unique_wallets[$owner]['amount'] += 1;
        }
    }
    $wallets = array_values($unique_wallets);

    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        $output = fopen('php://output', 'w');
        if ($output === false) throw new Exception('Failed to open output stream');
        
        if ($export_type === 'wallets_only') {
            fputcsv($output, ['Wallet Address']);
            foreach ($wallets as $wallet) {
                fputcsv($output, [$wallet['owner'] ?? 'N/A']);
            }
        } else {
            fputcsv($output, ['Wallet Address', 'NFT Count']);
            foreach ($wallets as $wallet) {
                fputcsv($output, [$wallet['owner'] ?? 'N/A', $wallet['amount'] ?? 0]);
            }
        }
        fclose($output);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        
        $json_data = array_map(function($wallet) use ($export_type) {
            return $export_type === 'wallets_only'
                ? $wallet['owner'] ?? 'N/A'
                : [
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
