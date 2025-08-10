<?php
// ============================================================================
// File: make-market/process/get-api.php
// Description: Retrieve Helius API key and Solana network for authenticated users
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/network.php';
require_once $root_path . 'make-market/security/auth.php';

// Perform authentication check
if (!perform_auth_check()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication or CSRF validation failed']);
    exit;
}

// Log request info
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    log_message("get-api.php: Script started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'DEBUG');
}

// Check if HELIUS_API_KEY is defined for mainnet
if (SOLANA_NETWORK === 'mainnet' && (!defined('HELIUS_API_KEY') || empty(HELIUS_API_KEY))) {
    log_message("HELIUS_API_KEY is not defined or empty for mainnet", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Missing HELIUS_API_KEY for mainnet']);
    exit;
}

// Return Helius API key and Solana network
log_message("Helius API key and Solana network retrieved successfully: network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
echo json_encode([
    'status' => 'success',
    'helius_api_key' => defined('HELIUS_API_KEY') ? HELIUS_API_KEY : '',
    'solana_network' => SOLANA_NETWORK
]);
?>
