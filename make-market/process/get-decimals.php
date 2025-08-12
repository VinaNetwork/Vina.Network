<?php
// ============================================================================
// File: make-market/process/get-decimals.php
// Description: Get Decimals Token Solana with caching and periodic cache cleanup
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/network.php';
require_once $root_path . 'make-market/security/auth.php';

// Perform authentication check (only AJAX and CSRF, no user auth)
initialize_auth();
if (!check_ajax_request() || !validate_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    exit;
}

define('CACHE_DIR', MAKE_MARKET_PATH . 'cache/');
define('CACHE_FILE', CACHE_DIR . 'cache-decimals.json');
define('CACHE_TTL', 24 * 60 * 60); // Cache TTL: 24 hours in seconds

// Function to read from cache and clean up expired entries
function read_from_cache($tokenMint, $network) {
    if (!file_exists(CACHE_FILE)) {
        log_message("Cache file does not exist: " . CACHE_FILE . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'WARNING');
        return null;
    }

    $cache_data = json_decode(file_get_contents(CACHE_FILE), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("Failed to parse cache file: " . json_last_error_msg() . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        return null;
    }

    // Remove expired cache entries
    $current_time = time();
    $initial_count = count($cache_data);
    $cache_data = array_filter($cache_data, function($entry) use ($current_time) {
        return $entry['timestamp'] + CACHE_TTL > $current_time;
    });

    // Write back cleaned cache if any entries were removed
    if (count($cache_data) !== $initial_count) {
        if (!ensure_directory_and_file(CACHE_DIR, CACHE_FILE)) {
            log_message("Failed to ensure cache directory or file for cleanup: " . CACHE_FILE . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        } elseif (file_put_contents(CACHE_FILE, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            log_message("Failed to write cleaned cache file: " . CACHE_FILE . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        } else {
            log_message("Cleaned up expired cache entries, removed " . ($initial_count - count($cache_data)) . " entries, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
        }
    }

    $cache_key = $tokenMint . '_' . $network;
    if (isset($cache_data[$cache_key]) && $cache_data[$cache_key]['timestamp'] + CACHE_TTL > $current_time) {
        return $cache_data[$cache_key]['decimals'];
    }

    return null;
}

// Function to write to cache
function write_to_cache($tokenMint, $network, $decimals) {
    $cache_data = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];
    if (json_last_error() !== JSON_ERROR_NONE) {
        $cache_data = [];
        log_message("Failed to parse cache file for writing: " . json_last_error_msg() . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    }

    $cache_key = $tokenMint . '_' . $network;
    $cache_data[$cache_key] = [
        'decimals' => $decimals,
        'timestamp' => time()
    ];

    if (!ensure_directory_and_file(CACHE_DIR, CACHE_FILE)) {
        log_message("Failed to ensure cache directory or file: " . CACHE_FILE . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        return;
    }

    if (file_put_contents(CACHE_FILE, json_encode($cache_data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        log_message("Failed to write to cache file: " . CACHE_FILE . ", network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    } else {
        log_message("Cache updated for mint=$tokenMint, network=$network, decimals=$decimals, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    }
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['tokenMint']) || !isset($data['network'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing tokenMint or network'], JSON_UNESCAPED_UNICODE);
    log_message("Invalid or missing tokenMint or network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    exit;
}

$tokenMint = $data['tokenMint'];
$network = $data['network'];
if (!in_array($network, ['testnet', 'mainnet'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid network'], JSON_UNESCAPED_UNICODE);
    log_message("Invalid network: $network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    exit;
}

// Check network consistency
if ($network !== SOLANA_NETWORK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Network mismatch: client ($network) vs server (" . SOLANA_NETWORK . ")"], JSON_UNESCAPED_UNICODE);
    log_message("Network mismatch: client_network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    exit;
}

// Check cache first
$cached_decimals = read_from_cache($tokenMint, $network);
if ($cached_decimals !== null) {
    log_message("Cache hit for mint=$tokenMint, network=$network, decimals=$cached_decimals, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    echo json_encode(['status' => 'success', 'decimals' => $cached_decimals]);
    exit;
}

// Use RPC_ENDPOINT from network.php
if (empty(RPC_ENDPOINT)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing RPC endpoint'], JSON_UNESCAPED_UNICODE);
    log_message("RPC_ENDPOINT is empty for network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    exit;
}

$maxRetries = 5;
$attempt = 0;

while ($attempt < $maxRetries) {
    log_message("Attempting to get token decimals (attempt " . ($attempt + 1) . "/$maxRetries): mint=$tokenMint, endpoint=" . RPC_ENDPOINT . ", network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');

    $ch = curl_init(RPC_ENDPOINT);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => 'getAccountInfo',
        'params' => [$tokenMint, ['encoding' => 'jsonParsed']]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $attempt++;
        log_message("Failed to get token decimals (attempt $attempt/$maxRetries): mint=$tokenMint, error=cURL error: $curl_error, network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        if ($attempt === $maxRetries) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: cURL error: $curl_error"], JSON_UNESCAPED_UNICODE);
            log_message("Failed to retrieve token decimals after $maxRetries attempts: cURL error: $curl_error, network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
            exit;
        }
        sleep($attempt); // Wait 1s, 2s, 3s, 4s, 5s
        continue;
    }

    if ($http_code !== 200) {
        $attempt++;
        log_message("Failed to get token decimals (attempt $attempt/$maxRetries): mint=$tokenMint, error=HTTP $http_code, network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        if ($attempt === $maxRetries) {
            http_response_code($http_code);
            echo json_encode(['status' => 'error', 'message' => "Failed to retrieve token decimals after $maxRetries attempts: HTTP $http_code"], JSON_UNESCAPED_UNICODE);
            log_message("Failed to retrieve token decimals after $maxRetries attempts: HTTP $http_code, network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
            exit;
        }
        sleep($attempt);
        continue;
    }

    $result = json_decode($response, true);
    log_message("Response from getAccountInfo: " . json_encode($result) . ", network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'DEBUG');

    if (!isset($result['result']['value']['data']['parsed']['type']) || $result['result']['value']['data']['parsed']['type'] !== 'mint') {
        http_response_code(400);
        $message = isset($result['result']['value'])
            ? "Invalid account type: received type={$result['result']['value']['data']['parsed']['type']}, expected 'mint'"
            : "Invalid response: no valid account data";
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        log_message("$message, mint=$tokenMint, endpoint=" . RPC_ENDPOINT . ", network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        exit;
    }

    $decimals = $result['result']['value']['data']['parsed']['info']['decimals'] ?? 9;
    log_message("Token decimals retrieved: mint=$tokenMint, decimals=$decimals, network=$network, server_network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');

    // Save to cache
    write_to_cache($tokenMint, $network, $decimals);

    echo json_encode(['status' => 'success', 'decimals' => $decimals]);
    exit;
}
