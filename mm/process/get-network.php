<?php
// ============================================================================
// File: mm/process/get-network.php
// Description: Endpoint to return network configuration for client-side use
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'config/csrf.php';
require_once $root_path . 'mm/network.php';
require_once $root_path . 'mm/header-auth.php'; // Security Headers

// Log request details
$session_id = session_id() ?: 'none';
$headers = apache_request_headers();
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$cookies = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none';
log_message("get-network.php: Request received, method=$request_method, uri=$request_uri, network=" . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id, cookies=$cookies, headers=" . json_encode($headers), 'make-market.log', 'make-market', 'INFO');

// Check authentication (includes CSRF validation via perform_auth_check)
if (!perform_auth_check()) {
    log_message("Authentication check failed, session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Validate SOLANA_NETWORK
    if (!defined('SOLANA_NETWORK') || !in_array(SOLANA_NETWORK, ['devnet', 'testnet', 'mainnet'])) {
        log_message("Invalid or missing SOLANA_NETWORK: " . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined') . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid SOLANA_NETWORK configuration'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $config = [
        'status' => 'success',
        'network' => SOLANA_NETWORK,
        'config' => [
            'jupiterApi' => 'https://quote-api.jup.ag/v6',
            'explorerUrl' => EXPLORER_URL,
            'explorerQuery' => EXPLORER_QUERY,
            'solMint' => 'So11111111111111111111111111111111111111112',
            'prioritizationFeeLamports' => in_array(SOLANA_NETWORK, ['testnet', 'devnet']) ? 0 : 10000
        ]
    ];
    log_message("Network config sent: network=" . SOLANA_NETWORK . ", explorerUrl=" . EXPLORER_URL . ", explorerQuery=" . EXPLORER_QUERY . ", prioritizationFeeLamports=" . $config['config']['prioritizationFeeLamports'] . ", session_id=$session_id", 'make-market.log', 'make-market', 'INFO');
    header('Content-Type: application/json');
    echo json_encode($config, JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    log_message("Failed to fetch network config: " . $e->getMessage() . ", session_id=$session_id", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch network configuration'], JSON_UNESCAPED_UNICODE);
}
?>
