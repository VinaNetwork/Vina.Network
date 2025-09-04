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

// Kiểm tra X-Auth-Token
$headers = getallheaders();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;

if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
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
