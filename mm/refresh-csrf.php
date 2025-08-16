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
require_once $root_path . 'config/csrf.php';

// Validate request method and headers
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$allowed_origin = 'https://' . $_SERVER['HTTP_HOST'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

// Nới lỏng kiểm tra origin: chỉ yêu cầu AJAX và origin (nếu có) phải khớp
if (!$is_ajax) {
    log_message("Invalid request to refresh-csrf: AJAX=$is_ajax, Origin=$origin, IP=$ip, URI=$uri", 'csrf.log', 'logs', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($origin && strpos($origin, $allowed_origin) !== 0) {
    log_message("Invalid origin for refresh-csrf: Origin=$origin, Expected=$allowed_origin, IP=$ip, URI=$uri", 'csrf.log', 'logs', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid origin'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle GET or POST request
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_COOKIE[CSRF_TOKEN_COOKIE] ?? '';
    if (!validate_csrf_token($token)) {
        log_message("Invalid CSRF token for refresh: " . ($token ? substr($token, 0, 8) . '...' : 'none') . ", IP=$ip, URI=$uri", 'csrf.log', 'logs', 'ERROR');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Regenerate token for POST after validation
    regenerate_csrf_token();
} elseif ($method !== 'GET') {
    log_message("Unsupported method: $method, IP=$ip, URI=$uri", 'csrf.log', 'logs', 'ERROR');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Generate new token and set cookie
$token = generate_csrf_token();
if ($token === false) {
    log_message("Failed to generate new CSRF token, IP=$ip, URI=$uri", 'csrf.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie, IP=$ip, URI=$uri", 'csrf.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to set CSRF cookie'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Return token and expiration time
$expires = $_SESSION[CSRF_TOKEN_NAME . '_expires'] ?? null;
log_message("CSRF token refreshed successfully: " . substr($token, 0, 8) . "... , IP=$ip, URI=$uri", 'csrf.log', 'logs', 'INFO');
echo json_encode([
    'status' => 'success',
    'csrf_token' => $token,
    'expires_at' => $expires
], JSON_UNESCAPED_UNICODE);
?>
