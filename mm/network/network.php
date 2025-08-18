<?php
// ============================================================================
// File: mm/network/network.php
// Description: Centralized network configuration for Solana devnet, testnet, and mainnet
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'mm/csrf/csrf.php';

// Determine Solana network (priority: ENV > default 'devnet')
if (!defined('SOLANA_NETWORK')) {
    $env_network = trim(getenv('SOLANA_NETWORK') ?: 'devnet');
    define('SOLANA_NETWORK', $env_network);
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

// Define RPC endpoint (only if not already defined)
if (!defined('RPC_ENDPOINT')) {
    define('RPC_ENDPOINT', match (SOLANA_NETWORK) {
        'devnet'  => 'https://api.devnet.solana.com',
        'testnet' => 'https://api.testnet.solana.com',
        'mainnet' => (defined('HELIUS_API_KEY') && !empty(HELIUS_API_KEY))
            ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY
            : 'https://api.mainnet-beta.solana.com',
    });
}

// Define Explorer URL (only if not already defined)
if (!defined('EXPLORER_URL')) {
    define('EXPLORER_URL', match (SOLANA_NETWORK) {
        'devnet', 'testnet' => 'https://solana.fm/tx/',
        'mainnet' => 'https://solscan.io/tx/',
    });
}

// Define Explorer query string (only if not already defined)
if (!defined('EXPLORER_QUERY')) {
    define('EXPLORER_QUERY', match (SOLANA_NETWORK) {
        'devnet'  => '?cluster=devnet',
        'testnet' => '?cluster=testnet',
        default   => '',
    });
}

// Log loaded configuration (only if first time loaded)
if (!defined('NETWORK_CONFIG_LOGGED')) {
    define('NETWORK_CONFIG_LOGGED', true);
    log_message(
        "Network config loaded: SOLANA_NETWORK=" . SOLANA_NETWORK .
        ", RPC_ENDPOINT=" . RPC_ENDPOINT .
        ", EXPLORER_URL=" . EXPLORER_URL . EXPLORER_QUERY,
        'make-market.log',
        'make-market',
        'INFO'
    );
}
?>
