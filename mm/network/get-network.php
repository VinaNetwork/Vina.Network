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
// constants | logging | config | error | session | database | header-auth.php | network.php | csrf.php | vendor/autoload.php
require_once $root_path . 'mm/bootstrap.php';

// Handle preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Return network configuration
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'network' => SOLANA_NETWORK,
    'explorerUrl' => EXPLORER_URL,
    'explorerQuery' => EXPLORER_QUERY,
    'jupiterApi' => defined('JUPITER_API') ? JUPITER_API : 'https://quote-api.jup.ag/v6',
    'solMint' => 'So11111111111111111111111111111111111111112',
    'prioritizationFeeLamports' => defined('PRIORITIZATION_FEE_LAMPORTS') ? PRIORITIZATION_FEE_LAMPORTS : 10000
], JSON_UNESCAPED_UNICODE);
exit;
?>
