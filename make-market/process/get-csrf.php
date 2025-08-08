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

log_message("get-csrf.php started, REQUEST_METHOD: {$_SERVER['REQUEST_METHOD']}, REQUEST_URI: {$_SERVER['REQUEST_URI']}", 'make-market.log', 'make-market', 'DEBUG');

initialize_auth();
if (!check_user_auth()) {
    log_message("check_user_auth failed, exiting", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

log_message("CSRF token generated: $csrf_token, session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'make-market', 'INFO');

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'csrf_token' => $csrf_token
], JSON_UNESCAPED_UNICODE);
?>
