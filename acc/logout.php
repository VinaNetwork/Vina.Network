<?php
// ============================================================================
// File: acc/logout.php
// Description: API to handle logout requests
// Created by: Vina Network
// ============================================================================

ob_start();
// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
// constants | logging | config | error | session | database | header-auth | wallet-auth
require_once $root_path . 'acc/bootstrap.php';

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

// Log logout attempt
$public_key = $_SESSION['public_key'] ?? 'unknown';
$short_public_key = strlen($public_key) >= 8 ? substr($public_key, 0, 4) . '...' . substr($public_key, -4) : 'Invalid';
log_message("Logout attempt for public_key: $short_public_key, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'INFO');

// Clear session
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', $domain, true, false); // Match core/session.php
}
session_destroy();

// Respond with success
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Logout successful',
    'redirect' => '/acc/'
], JSON_UNESCAPED_UNICODE);
ob_end_flush();
exit;
?>
