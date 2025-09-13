<?php
// File: core/network.php
// Description: Centralized network configuration for Solana devnet and mainnet
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Determine Solana network (priority: ENV > default 'devnet')
if (!defined('SOLANA_NETWORK')) {
    $env_network = trim(getenv('SOLANA_NETWORK') ?: 'devnet');
    define('SOLANA_NETWORK', $env_network);
}

// Validate SOLANA_NETWORK
$valid_networks = ['devnet', 'mainnet'];
if (!in_array(SOLANA_NETWORK, $valid_networks, true)) {
    log_message("Invalid SOLANA_NETWORK: " . SOLANA_NETWORK, 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid SOLANA_NETWORK configuration'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Define RPC endpoint
if (!defined('RPC_ENDPOINT')) {
    define('RPC_ENDPOINT', match (SOLANA_NETWORK) {
        'devnet'  => 'https://api.devnet.solana.com',
        'mainnet' => (defined('HELIUS_API_KEY') && !empty(HELIUS_API_KEY))
            ? 'https://mainnet.helius-rpc.com/?api-key=' . HELIUS_API_KEY
            : 'https://api.mainnet-beta.solana.com',
    });
}

// Define Explorer URL
if (!defined('EXPLORER_URL')) {
    define('EXPLORER_URL', match (SOLANA_NETWORK) {
        'devnet' => 'https://solana.fm/tx/',
        'mainnet' => 'https://solscan.io/tx/',
    });
}

// Define Explorer query string
if (!defined('EXPLORER_QUERY')) {
    define('EXPLORER_QUERY', match (SOLANA_NETWORK) {
        'devnet'  => '?cluster=devnet',
        default   => '',
    });
}

// Define Jupiter API (1 endpoint)
if (!defined('JUPITER_API')) {
    define('JUPITER_API', 'https://quote-api.jup.ag/v6/quote');
}

// Log loaded configuration
if (!defined('NETWORK_CONFIG_LOGGED')) {
    define('NETWORK_CONFIG_LOGGED', true);
    log_message(
        "Network config loaded: SOLANA_NETWORK=" . SOLANA_NETWORK .
        ", RPC_ENDPOINT=" . RPC_ENDPOINT .
        ", EXPLORER_URL=" . EXPLORER_URL .
        ", EXPLORER_QUERY=" . ($EXPLORER_QUERY !== '' ? $EXPLORER_QUERY : 'empty') .
        ", JUPITER_API=" . JUPITER_API,
        'bootstrap.log',
        'logs',
        'INFO'
    );
}
?>
