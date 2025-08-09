<?php
// ============================================================================
// File: make-market/process/network.php
// Description: Centralized network configuration for Solana and endpoint to return config for client-side
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';

// Add Security Headers
require_once $root_path . 'make-market/security-headers.php';

// Validate SOLANA_NETWORK
if (!defined('SOLANA_NETWORK') || !in_array(SOLANA_NETWORK, ['devnet', 'testnet', 'mainnet'])) {
    log_message("Invalid or missing SOLANA_NETWORK: " . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'make-market', 'ERROR');
    if (isset($_SERVER['REQUEST_METHOD'])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Invalid SOLANA_NETWORK configuration'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return; // Return silently if included by another PHP file
}

// Define network-specific constants
define('RPC_ENDPOINT_DEVNET', 'https://api.devnet.solana.com');
define('RPC_ENDPOINT_TESTNET', 'https://api.testnet.solana.com');
define('RPC_ENDPOINT_MAINNET', defined('HELIUS_API_KEY') && !empty(HELIUS_API_KEY) 
    ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY 
    : 'https://api.mainnet-beta.solana.com');
define('RPC_ENDPOINT', SOLANA_NETWORK === 'devnet' ? RPC_ENDPOINT_DEVNET : (SOLANA_NETWORK === 'testnet' ? RPC_ENDPOINT_TESTNET : RPC_ENDPOINT_MAINNET));
define('EXPLORER_URL_DEVNET', 'https://solana.fm/tx/');
define('EXPLORER_URL_TESTNET', 'https://solana.fm/tx/');
define('EXPLORER_URL_MAINNET', 'https://solscan.io/tx/');
define('EXPLORER_QUERY_DEVNET', '?cluster=devnet');
define('EXPLORER_QUERY_TESTNET', '?cluster=testnet');
define('EXPLORER_QUERY_MAINNET', '');
define('EXPLORER_URL', SOLANA_NETWORK === 'devnet' ? EXPLORER_URL_DEVNET : (SOLANA_NETWORK === 'testnet' ? EXPLORER_URL_TESTNET : EXPLORER_URL_MAINNET));
define('EXPLORER_QUERY', SOLANA_NETWORK === 'devnet' ? EXPLORER_QUERY_DEVNET : (SOLANA_NETWORK === 'testnet' ? EXPLORER_QUERY_TESTNET : EXPLORER_QUERY_MAINNET));

// Log network configuration when included
log_message("Network configuration loaded: SOLANA_NETWORK=" . SOLANA_NETWORK . ", RPC_ENDPOINT=" . RPC_ENDPOINT . ", EXPLORER_URL=" . EXPLORER_URL . EXPLORER_QUERY, 'make-market.log', 'make-market', 'INFO');

// Handle HTTP request if called as an endpoint
if (isset($_SERVER['REQUEST_METHOD'])) {
    // Check if request is AJAX
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        log_message("Direct access to network.php detected, rejecting request", 'make-market.log', 'make-market', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Log request
    log_message("network.php: Request received, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');
    try {
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
        log_message("Network config sent: network=" . SOLANA_NETWORK . ", explorerUrl=" . EXPLORER_URL . ", explorerQuery=" . EXPLORER_QUERY . ", prioritizationFeeLamports=" . $config['config']['prioritizationFeeLamports'], 'make-market.log', 'make-market', 'INFO');
        echo json_encode($config, JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Exception $e) {
        log_message("Failed to fetch network config: " . $e->getMessage(), 'make-market.log', 'make-market', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch network configuration'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
