<?php
// ============================================================================
// File: make-market/process/get-api.php
// Description: Retrieve Helius API key and Solana network for authenticated users
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = '../../';
require_once $root_path . 'make-market/get-bootstrap.php';
require_once $root_path . 'config/config.php';

setup_cors_headers($csp_base, 'GET');
check_ajax_request();
$user_id = check_authenticated_user();
log_request_info('get-api.php');

// Cache configuration for 1 hour
$cache_key = 'api_config_' . $user_id;
$cache_file = $root_path . 'cache/' . $cache_key . '.json';
$cache_duration = 3600;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
    $cached_data = file_get_contents($cache_file);
    $config = json_decode($cached_data, true);
    log_message("API config retrieved from cache: network={$config['solana_network']}, user_id=$user_id", 'make-market.log', 'make-market', 'INFO');
    echo json_encode($config);
    exit;
}

// Check if HELIUS_API_KEY and SOLANA_NETWORK are defined
if (!defined('HELIUS_API_KEY') || empty(HELIUS_API_KEY)) {
    log_message("HELIUS_API_KEY is not defined or empty", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing HELIUS_API_KEY'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!defined('SOLANA_NETWORK') || !in_array(SOLANA_NETWORK, ['mainnet', 'testnet'])) {
    log_message("SOLANA_NETWORK is not defined or invalid", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Invalid SOLANA_NETWORK'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Store in cache
$config = [
    'status' => 'success',
    'helius_api_key' => HELIUS_API_KEY,
    'solana_network' => SOLANA_NETWORK
];
file_put_contents($cache_file, json_encode($config, JSON_UNESCAPED_UNICODE));
log_message("Helius API key and Solana network retrieved successfully: network=" . SOLANA_NETWORK . ", user_id=$user_id", 'make-market.log', 'make-market', 'INFO');
echo json_encode($config, JSON_UNESCAPED_UNICODE);
?>
