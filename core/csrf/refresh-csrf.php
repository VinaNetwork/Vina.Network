<?php
// ============================================================================
// File: core/csrf/refresh-csrf.php
// Description: API to refresh CSRF token for AJAX requests
// Created by: Vina Network
// ============================================================================

// List of allowed sources (core/constants.php)
$allowed_origins = ALLOWED_ORIGINS;
if (!isset($allowed_origins) || !is_array($allowed_origins)) {
    log_message("ALLOWED_ORIGINS not defined or invalid", 'bootstrap.log', 'logs', 'CRITICAL');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || 
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest' ||
    !check_request_origin($allowed_origins)) {
    log_message("Invalid request to refresh-csrf, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or unauthorized origin'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Origin check function
function check_request_origin($allowed_origins) { // Truyền tham số thay vì global
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

    if (empty($origin)) {
        log_message("No Origin header provided, IP=$ip, URI=$uri", 'bootstrap.log', 'logs', 'ERROR');
        return false;
    }
    $parsed_origin = parse_url($origin, PHP_URL_SCHEME) . '://' . parse_url($origin, PHP_URL_HOST);
    if (parse_url($origin, PHP_URL_PORT)) {
        $parsed_origin .= ':' . parse_url($origin, PHP_URL_PORT);
    }
    foreach ($allowed_origins as $allowed) {
        if ($parsed_origin === rtrim($allowed, '/')) {
            log_message("Origin validated: $parsed_origin, IP=$ip, URI=$uri", 'bootstrap.log', 'logs', 'INFO');
            return true;
        }
    }
    log_message("Invalid origin: $parsed_origin, IP=$ip, URI=$uri", 'bootstrap.log', 'logs', 'ERROR');
    return false;
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
