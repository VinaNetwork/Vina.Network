<?php
// ============================================================================
// File: mm/refresh-csrf.php
// Description: API to refresh CSRF token for AJAX requests
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    log_message("Invalid request to refresh-csrf, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'csrf.log', 'logs', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = generate_csrf_token();
if ($token === false) {
    log_message("Failed to generate new CSRF token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'csrf.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'csrf.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to set CSRF cookie'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message("CSRF token refreshed successfully: $token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'csrf.log', 'logs', 'INFO');
echo json_encode(['status' => 'success', 'csrf_token' => $token], JSON_UNESCAPED_UNICODE);
?>
