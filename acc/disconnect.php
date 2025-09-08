<?php
// ============================================================================
// File: acc/core/disconnect.php
// Description: API to handle disconnect requests
// Created by: Vina Network
// ============================================================================

ob_start();
$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth
require_once $root_path . 'acc/bootstrap.php';

// Set response header
header('Content-Type: application/json');

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid logout request: Not POST, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST.'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Check X-Auth-Token
$headers = getallheaders();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;

if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token'], JSON_UNESCAPED_UNICODE);
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
    setcookie(session_name(), '', time() - 3600, '/', $domain, true, false);
}
session_destroy();

// Respond with success
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Logout successful',
    'redirect' => '/acc/connect'
], JSON_UNESCAPED_UNICODE);
ob_end_flush();
exit;
?>
