<?php
// ============================================================================
// File: config/csrf.php
// Description: CSRF protection utilities + AJAX refresh endpoint
// Author: Vina Network (Optimized version)
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/bootstrap.php';

// ==== CSRF CONFIG ====
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
    define('CSRF_TOKEN_LENGTH', 32); // bytes => 64 hex chars
    define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie');
    define('CSRF_TOKEN_TTL', 1200); // Token lifetime in seconds (20 min)
}

// ==== SESSION HANDLER ====
function ensure_session($max_attempts = 3) {
    global $is_secure, $domain;
    $config = [
        'cookie_lifetime' => 0,
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $is_secure,
        'cookie_domain' => $domain
    ];

    for ($i = 0; $i < $max_attempts; $i++) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_start($config)) continue;
        }
        return true;
    }
    return false;
}

// ==== TOKEN FUNCTIONS ====
function generate_csrf_token() {
    if (!ensure_session()) return false;
    $now = time();
    if (
        empty($_SESSION[CSRF_TOKEN_NAME]) ||
        empty($_SESSION['csrf_token_time']) ||
        ($now - $_SESSION['csrf_token_time']) > CSRF_TOKEN_TTL
    ) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION['csrf_token_time'] = $now;
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validate_csrf_token($token) {
    if (!ensure_session()) return false;
    if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) return false;
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TTL) return false;
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function regenerate_csrf_token() {
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME], $_SESSION['csrf_token_time']);
    }
    return generate_csrf_token();
}

function get_csrf_field() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

function csrf_protect() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            exit(json_encode(['error' => 'Invalid CSRF token']));
        }
        regenerate_csrf_token();
    }
}

function set_csrf_cookie($token = null) {
    global $is_secure;
    if (!$token) $token = generate_csrf_token();
    if (!$token) return false;

    setcookie(CSRF_TOKEN_COOKIE, $token, [
        'expires' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    return true;
}

// ==== REFRESH TOKEN ENDPOINT ====
if (
    php_sapi_name() !== 'cli' &&
    isset($_GET['action']) && $_GET['action'] === 'refresh'
) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // Chặn request từ domain khác
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $allowed_host = $_SERVER['HTTP_HOST'];
    if (
        ($_SERVER['REQUEST_METHOD'] !== 'GET') ||
        (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') ||
        ($origin && parse_url($origin, PHP_URL_HOST) !== $allowed_host) ||
        ($referer && parse_url($referer, PHP_URL_HOST) !== $allowed_host)
    ) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }

    $token = regenerate_csrf_token();
    if (!$token || !set_csrf_cookie($token)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to refresh token']);
        exit;
    }

    echo json_encode(['status' => 'success', 'csrf_token' => $token]);
    exit;
}
