<?php
// ============================================================================
// File: mm/network/get-network.php
// Description: API endpoint to return SOLANA_NETWORK and related configurations
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

// Return network configuration
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'network' => SOLANA_NETWORK,
    'explorerUrl' => EXPLORER_URL,
    'explorerQuery' => EXPLORER_QUERY,
    'swapProvider' => in_array(SOLANA_NETWORK, ['devnet', 'testnet']) ? 'raydium' : 'jupiter',
    'jupiterApi' => defined('JUPITER_API') ? JUPITER_API : 'https://quote-api.jup.ag/v6',
    'solMint' => 'So11111111111111111111111111111111111111112',
    'prioritizationFeeLamports' => defined('PRIORITIZATION_FEE_LAMPORTS') ? PRIORITIZATION_FEE_LAMPORTS : 10000
], JSON_UNESCAPED_UNICODE);
exit;
?>
