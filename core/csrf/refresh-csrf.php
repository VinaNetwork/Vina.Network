<?php
// ============================================================================
// File: core/csrf/refresh-csrf.php
// Description: API to refresh CSRF token for AJAX requests
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$root_path = __DIR__ . '/../../';
// constants | logging | core | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

// List of allowed sources (core/constants.php)
$allowed_origins = ALLOWED_ORIGINS;

// Origin check function
function check_request_origin() {
    global $allowed_origins;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

    // If there is no Origin or Referer, reject the request.
    if (empty($origin)) {
        log_message("Invalid request to refresh-csrf: No Origin or Referer header, IP=$ip, URI=$uri", 'bootstrap.log', 'logs', 'ERROR');
        return false;
    }

    // Normalize Origin/Referer by getting domain (remove path)
    $parsed_origin = parse_url($origin, PHP_URL_SCHEME) . '://' . parse_url($origin, PHP_URL_HOST);
    if (parse_url($origin, PHP_URL_PORT)) {
        $parsed_origin .= ':' . parse_url($origin, PHP_URL_PORT);
    }

    // Check if the origin is in the allowed list
    foreach ($allowed_origins as $allowed) {
        $parsed_allowed = rtrim($allowed, '/');
        if ($parsed_origin === $parsed_allowed) {
            log_message("Origin validated: $parsed_origin, IP=$ip, URI=$uri", 'bootstrap.log', 'logs', 'INFO');
            return true;
        }
    }

    log_message("Invalid request to refresh-csrf: Origin/Referer ($parsed_origin) not allowed, IP=$ip, URI=$uri", 'bootstrap.log', 'logs', 'ERROR');
    return false;
}

// Test methods, AJAX and origins
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || 
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest' ||
    !check_request_origin()) {
    log_message("Invalid request to refresh-csrf, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or unauthorized origin'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = generate_csrf_token();
if ($token === false) {
    log_message("Failed to generate new CSRF token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to set CSRF cookie'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message("CSRF token refreshed successfully: $token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'bootstrap.log', 'logs', 'INFO');
echo json_encode(['status' => 'success', 'csrf_token' => $token], JSON_UNESCAPED_UNICODE);
?>
