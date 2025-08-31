<?php
// ============================================================================
// File: accounts/logout.php
// Description: API to handle logout requests with CSRF validation
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'accounts/bootstrap.php';

// Ensure no output before session operations
ob_start();

// Ensure POST request and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Invalid logout request: Not POST or not AJAX, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Protect with CSRF validation
$token = $_POST['csrf_token'] ?? $_COOKIE['csrf_token_cookie'] ?? '';
if (!validate_csrf_token($token)) {
    log_message("CSRF token validation failed for logout, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'WARNING');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired CSRF token'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Log logout attempt
$public_key = $_SESSION['public_key'] ?? 'unknown';
$short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Logout attempt for public_key: $short_public_key, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'INFO');

// Clear session
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', 'vina.network', true, true);
}
session_destroy();

// Respond with success (no CSRF token regeneration)
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Logout successful',
    'redirect' => '/accounts/'
], JSON_UNESCAPED_UNICODE);
ob_end_flush();
exit;
?>
