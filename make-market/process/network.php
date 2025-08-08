<?php
// ============================================================================
// File: make-market/process/network.php
// Description: Centralized network configuration for Solana testnet and mainnet
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';

// Validate SOLANA_NETWORK
if (!defined('SOLANA_NETWORK') || !in_array(SOLANA_NETWORK, ['testnet', 'mainnet'])) {
    log_message("Invalid or missing SOLANA_NETWORK: " . (defined('SOLANA_NETWORK') ? SOLANA_NETWORK : 'undefined'), 'make-market.log', 'network', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Invalid SOLANA_NETWORK configuration'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Define network-specific constants
define('RPC_ENDPOINT_TESTNET', 'https://api.testnet.solana.com');
define('RPC_ENDPOINT_MAINNET', defined('HELIUS_API_KEY') && !empty(HELIUS_API_KEY) 
    ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY 
    : 'https://api.mainnet-beta.solana.com');
define('RPC_ENDPOINT', SOLANA_NETWORK === 'testnet' ? RPC_ENDPOINT_TESTNET : RPC_ENDPOINT_MAINNET);
define('EXPLORER_URL_TESTNET', 'https://solana.fm/tx/');
define('EXPLORER_URL_MAINNET', 'https://solscan.io/tx/');
define('EXPLORER_QUERY_TESTNET', '?cluster=testnet');
define('EXPLORER_QUERY_MAINNET', '');
define('EXPLORER_URL', SOLANA_NETWORK === 'testnet' ? EXPLORER_URL_TESTNET : EXPLORER_URL_MAINNET);
define('EXPLORER_QUERY', SOLANA_NETWORK === 'testnet' ? EXPLORER_QUERY_TESTNET : EXPLORER_QUERY_MAINNET);

log_message("Network configuration loaded: SOLANA_NETWORK=" . SOLANA_NETWORK . ", RPC_ENDPOINT=" . RPC_ENDPOINT . ", EXPLORER_URL=" . EXPLORER_URL . EXPLORER_QUERY, 'make-market.log', 'network', 'INFO');
?>
