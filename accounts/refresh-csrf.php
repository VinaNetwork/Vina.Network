<?php
// ============================================================================
// File: accounts/refresh-csrf.php
// Description: CSRF token refresh endpoint for Vina Network Accounts
// Author: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';

if (
    php_sapi_name() !== 'cli' &&
    isset($_GET['action']) && 
    $_GET['action'] === 'refresh' &&
    $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    // Prepare headers for security
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Validate request
    global $is_secure, $domain;
    if (!isset($domain) || !isset($is_secure)) {
        log_message('CSRF Refresh: $domain or $is_secure not defined', 'csrf.log', 'logs', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
        exit;
    }

    $allowed_host = $domain;
    $origin = parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST) ?? '';
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '';
    
    $is_valid_request = (
        ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' &&
        ($origin === '' || $origin === $allowed_host) &&
        ($referer === '' || $referer === $allowed_host)
    );

    if (!$is_valid_request) {
        http_response_code(403);
        log_message('CSRF Refresh: Invalid request', 'csrf.log', 'logs', 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }

    // Generate and set token
    try {
        $token = regenerate_csrf_token();
        if (!$token || !set_csrf_cookie($token)) {
            throw new RuntimeException('Failed to generate or set CSRF token');
        }
        
        echo json_encode([
            'status' => 'success',
            'csrf_token' => $token,
            'expires_in' => CSRF_TOKEN_TTL - (time() - ($_SESSION[CSRF_SESSION_PREFIX . 'token_time'] ?? 0))
        ]);
    } catch (Exception $e) {
        log_message('CSRF Refresh Error: ' . $e->getMessage(), 'csrf.log', 'logs', 'ERROR');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to refresh token']);
        exit;
    }
    exit;
}
?>
