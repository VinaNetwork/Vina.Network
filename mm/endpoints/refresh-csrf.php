<?php
// ============================================================================
// File: mm/endpoints/refresh-csrf.php
// Description: API to refresh CSRF token for AJAX requests
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
// constants | logging | config | error | session | database | header-auth | network | csrf | vendor/autoload
require_once $root_path . 'mm/bootstrap.php';

// List of allowed sources (bootstrap)
$allowed_origins = ALLOWED_ORIGINS;

// Origin check function
function check_request_origin() {
    global $allowed_origins;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

    // If there is no Origin or Referer, reject the request.
    if (empty($origin)) {
        log_message("Invalid request to refresh-csrf: No Origin or Referer header, IP=$ip, URI=$uri", 'make-market.log', 'make-market', 'ERROR');
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
            log_message("Origin validated: $parsed_origin, IP=$ip, URI=$uri", 'make-market.log', 'make-market', 'INFO');
            return true;
        }
    }

    log_message("Invalid request to refresh-csrf: Origin/Referer ($parsed_origin) not allowed, IP=$ip, URI=$uri", 'make-market.log', 'make-market', 'ERROR');
    return false;
}

// Test methods, AJAX, origins, and X-Auth-Token
$headers = getallheaders();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || 
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest' ||
    !check_request_origin() ||
    $authToken !== JWT_SECRET) {
    log_message("Invalid request to refresh-csrf: Method={$_SERVER['REQUEST_METHOD']}, AJAX=" . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'none') . ", Origin=" . (check_request_origin() ? 'valid' : 'invalid') . ", X-Auth-Token=" . ($authToken ? 'provided' : 'missing') . ", IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", URI=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code($authToken !== JWT_SECRET ? 401 : 403);
    echo json_encode(['status' => 'error', 'message' => $authToken !== JWT_SECRET ? 'Invalid or missing token' : 'Invalid request or unauthorized origin'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = generate_csrf_token();
if ($token === false) {
    log_message("Failed to generate new CSRF token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!set_csrf_cookie()) {
    log_message("Failed to set CSRF cookie, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to set CSRF cookie'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_message("CSRF token refreshed successfully: $token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'make-market.log', 'make-market', 'INFO');
echo json_encode(['status' => 'success', 'csrf_token' => $token], JSON_UNESCAPED_UNICODE);
?>
