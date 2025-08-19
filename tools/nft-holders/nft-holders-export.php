<?php
// ============================================================================
// File: tools/nft-holders/nft-holders-export.php
// Description: This script handles export requests for NFT holder data (CSV/JSON format).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'tools/bootstrap.php';
require_once $root_path . 'tools/core/tools-api.php';
require_once $root_path . 'tools/nft-holders/nft-holders-helper.php';

// Define cache directory and file
$cache_dir = NFT_HOLDERS_PATH . 'cache/';
$cache_file = $cache_dir . 'nft_holders_cache.json';
log_message("export_holders: Checking cache directory: $cache_dir, file: $cache_file", 'holders-export.log', 'tools', 'DEBUG');

if (!ensure_directory_and_file($cache_dir, $cache_file)) {
    log_message("export_holders: Cache setup failed for $cache_dir or $cache_file", 'holders-export.log', 'tools', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Cache setup failed']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("export_holders: Invalid request method", 'holders-export.log', 'tools', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Parse and validate parameters
$mintAddress = trim($_POST['mintAddress'] ?? '');
$export_type = $_POST['export_type'] ?? 'all';
$export_format = $_POST['export_format'] ?? 'csv';

log_message("export_holders: Parameters - mintAddress=$mintAddress, export_type=$export_type, export_format=$export_format", 'holders-export.log', 'tools', 'DEBUG');

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $mintAddress)) {
    log_message("export_holders: Invalid collection address: $mintAddress", 'holders-export.log', 'tools', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid collection address']);
    exit;
}

if (!in_array($export_format, ['csv', 'json'])) {
    log_message("export_holders: Invalid export format: $export_format", 'holders-export.log', 'tools', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

if (!in_array($export_type, ['all', 'address-only'])) {
    log_message("export_holders: Invalid export type: $export_type", 'holders-export.log', 'tools', 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export type']);
    exit;
}

try {
    $items = [];
    $filename = ($export_type === 'address-only' ? "wallets_only_" : "holders_all_") . "{$mintAddress}." . ($export_format === 'csv' ? 'csv' : 'json');

    // Load cache
    $cache_data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) ?? [] : [];
    $cache_expiration = 3 * 3600;
    $cache_valid = isset($cache_data[$mintAddress]['timestamp']) && (time() - $cache_data[$mintAddress]['timestamp'] < $cache_expiration);

    if ($cache_valid && isset($cache_data[$mintAddress]['wallets'])) {
        $wallets = $cache_data[$mintAddress]['wallets'];
        $total_items = $cache_data[$mintAddress]['total_items'] ?? count($wallets);
        log_message("export_holders: Using cache for mintAddress=$mintAddress", 'holders-export.log', 'tools', 'INFO');
    } else {
        log_message("export_holders: Fetching new data for mintAddress=$mintAddress", 'holders-export.log', 'tools', 'INFO');
        ini_set('memory_limit', '512M');

        $holderData = fetchNFTCollectionHolders($mintAddress, 100, 100, 2000000);
        $wallets = $holderData['wallets'];
        $total_items = $holderData['total_items'];

        if (empty($wallets)) {
            log_message("export_holders: No items found for mintAddress=$mintAddress", 'holders-export.log', 'tools', 'ERROR');
            throw new Exception('No items found');
        }

        // Update cache
        $cache_data[$mintAddress] = [
            'wallets' => $wallets,
            'total_items' => $total_items,
            'timestamp' => time()
        ];
        $fp = fopen($cache_file, 'c');
        if (flock($fp, LOCK_EX)) {
            file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        log_message("export_holders: Cache updated for mintAddress=$mintAddress", 'holders-export.log', 'tools', 'INFO');
    }

    // Output
    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception('Failed to open output stream');
        }

        if ($export_type === 'address-only') {
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
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $json_data = array_map(function($wallet) use ($export_type) {
            if ($export_type === 'address-only') {
                return $wallet['owner'] ?? 'N/A';
            }
            return [
                'address' => $wallet['owner'] ?? 'N/A',
                'amount' => $wallet['amount'] ?? 0
            ];
        }, $wallets);

        echo json_encode($json_data, JSON_PRETTY_PRINT);
    }

    log_message("export_holders: Successfully exported data for mintAddress=$mintAddress, format=$export_format, type=$export_type", 'holders-export.log', 'tools', 'INFO');
    exit;

} catch (Exception $e) {
    log_message("export_holders: Exception - " . $e->getMessage(), 'holders-export.log', 'tools', 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
