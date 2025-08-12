<?php
// ============================================================================
// File: mm/network.php
// Description: Centralized network configuration for Solana devnet, testnet, and mainnet
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
// Ensure bootstrap is loaded
if (!defined('BASE_URL')) {
    require_once $root_path . 'config/bootstrap.php';
}

// Determine Solana network (priority: ENV > bootstrap.php default)
if (!defined('SOLANA_NETWORK')) {
    define('SOLANA_NETWORK', getenv('SOLANA_NETWORK') ?: 'devnet'); 
}

// Validate SOLANA_NETWORK
$valid_networks = ['devnet', 'testnet', 'mainnet'];
if (!in_array(SOLANA_NETWORK, $valid_networks, true)) {
    log_message(
        "Invalid SOLANA_NETWORK: " . SOLANA_NETWORK,
        'make-market.log',
        'make-market',
        'ERROR'
    );
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid SOLANA_NETWORK configuration'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// RPC endpoints
define('RPC_ENDPOINT', match (SOLANA_NETWORK) {
    'devnet'  => 'https://api.devnet.solana.com',
    'testnet' => 'https://api.testnet.solana.com',
    'mainnet' => (defined('HELIUS_API_KEY') && !empty(HELIUS_API_KEY))
        ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY
        : 'https://api.mainnet-beta.solana.com',
});

// Explorer URLs
define('EXPLORER_URL', match (SOLANA_NETWORK) {
    'devnet', 'testnet' => 'https://solana.fm/tx/',
    'mainnet' => 'https://solscan.io/tx/',
});

define('EXPLORER_QUERY', match (SOLANA_NETWORK) {
    'devnet'  => '?cluster=devnet',
    'testnet' => '?cluster=testnet',
    default   => '',
});

// Log loaded configuration
log_message(
    "Network config loaded: SOLANA_NETWORK=" . SOLANA_NETWORK .
    ", RPC_ENDPOINT=" . RPC_ENDPOINT .
    ", EXPLORER_URL=" . EXPLORER_URL . EXPLORER_QUERY,
    'make-market.log',
    'make-market',
    'INFO'
);
?>
