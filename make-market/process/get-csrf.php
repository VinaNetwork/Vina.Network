<?php
// ============================================================================
// File: make-market/process/get-csrf.php
// Description: Generate and return CSRF token
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';
require_once $root_path . 'make-market/process/auth.php';

log_message("get-csrf.php started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}, session_id: " . session_id(), 'make-market.log', 'make-market', 'DEBUG');

// Session start: in config/bootstrap.php

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}, network=" . SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate request: must be AJAX and authenticated
if (!check_ajax_request() || !check_user_auth()) {
    exit;
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
log_message("CSRF token generated: $csrf_token, session_user_id=" . ($_SESSION['user_id'] ?? 'none') . ", session_id: " . session_id(), 'make-market.log', 'make-market', 'INFO');

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'csrf_token' => $csrf_token
], JSON_UNESCAPED_UNICODE);
?>
