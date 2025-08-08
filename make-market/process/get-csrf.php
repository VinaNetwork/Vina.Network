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

initialize_auth();
if (!check_user_auth()) {
    exit;
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

log_message("CSRF token generated: $csrf_token, session_user_id=" . ($_SESSION['user_id'] ?? 'none'), 'make-market.log', 'auth', 'INFO');

echo json_encode([
    'status' => 'success',
    'csrf_token' => $csrf_token
], JSON_UNESCAPED_UNICODE);
?>
