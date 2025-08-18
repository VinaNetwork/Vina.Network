<?php
// ============================================================================
// File: mm/network/get-network.php
// Description: API endpoint to return SOLANA_NETWORK value
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'mm/network/network.php';

// Protect endpoint with CSRF
csrf_protect();

// Return SOLANA_NETWORK
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'network' => SOLANA_NETWORK
], JSON_UNESCAPED_UNICODE);
exit;
?>
