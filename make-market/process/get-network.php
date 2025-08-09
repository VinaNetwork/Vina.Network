<?php
// ============================================================================
// File: make-market/process/get-network.php
// Description: Endpoint to return network configuration for client-side use
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/network.php';

// Add Security Headers
require_once $root_path . 'make-market/security-headers.php';

// Log request
log_message("get-network.php: Request received, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'INFO');

try {
    $config = [
        'status' => 'success',
        'network' => SOLANA_NETWORK,
        'config' => [
            'jupiterApi' => 'https://quote-api.jup.ag/v6',
            'explorerUrl' => EXPLORER_URL,
            'explorerQuery' => EXPLORER_QUERY,
            'solMint' => 'So11111111111111111111111111111111111111112',
            'prioritizationFeeLamports' => SOLANA_NETWORK === 'testnet' ? 0 : 10000
        ]
    ];
    log_message("Network config sent: network=" . SOLANA_NETWORK . ", explorerUrl=" . EXPLORER_URL . ", explorerQuery=" . EXPLORER_QUERY, 'make-market.log', 'make-market', 'INFO');
    echo json_encode($config, JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    log_message("Failed to fetch network config: " . $e->getMessage(), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch network configuration'], JSON_UNESCAPED_UNICODE);
}
?>
