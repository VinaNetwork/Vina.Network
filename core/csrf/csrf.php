<?php
// ============================================================================
// File: core/csrf/csrf.php
// Description: CSRF protection utilities for Vina Network
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'core/constants.php';

// CSRF Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie');
define('CSRF_TOKEN_TTL', 86400); // 24h

// Check CORS header
function set_cors_headers() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    log_message("Checking CORS: origin=$origin, allowed=" . json_encode(ALLOWED_ORIGINS), 'bootstrap.log', 'logs', 'DEBUG');
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST");
        header("Access-Control-Allow-Headers: X-CSRF-Token, Content-Type");
        log_message("CORS headers set for origin=$origin", 'bootstrap.log', 'logs', 'INFO');
    } else {
        log_message("CORS check failed: origin=$origin, allowed=" . json_encode(ALLOWED_ORIGINS), 'bootstrap.log', 'logs', 'ERROR');
    }
}

// Ensure session is active
function ensure_session() {
    if (defined('SESSION_STARTED') && session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (!defined('SESSION_STARTED') && session_status() === PHP_SESSION_NONE) {
        return session_start();
    }
    return session_status() === PHP_SESSION_ACTIVE;
}

// Generate CSRF token and store in session
function generate_csrf_token() {
    if (!ensure_session()) return false;

    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION[CSRF_TOKEN_NAME . '_created'] = time();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Validate CSRF token
function validate_csrf_token($token) {
    if (!ensure_session()) return false;

    if (empty($token) || !isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }

    // TTL check
    if (isset($_SESSION[CSRF_TOKEN_NAME . '_created']) &&
        (time() - $_SESSION[CSRF_TOKEN_NAME . '_created']) > CSRF_TOKEN_TTL) {
        return false;
    }

    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Regenerate CSRF token
function regenerate_csrf_token() {
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME], $_SESSION[CSRF_TOKEN_NAME . '_created']);
    }
    return generate_csrf_token();
}

// Get hidden input field
function get_csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

// CSRF middleware
function csrf_protect() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? $_COOKIE[CSRF_TOKEN_COOKIE] ?? '';

        // Check Origin/Referer for extra defense
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($origin && parse_url($origin, PHP_URL_HOST) !== $host) {
            http_response_code(403); exit('Invalid Origin');
        }
        if ($referer && parse_url($referer, PHP_URL_HOST) !== $host) {
            http_response_code(403); exit('Invalid Referer');
        }

        if (!validate_csrf_token($token)) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid or expired CSRF token']);
            } else {
                header('Location: /error?message=Invalid+CSRF+token');
            }
            exit;
        }
    }
}

// Set CSRF token in cookie for AJAX
function set_csrf_cookie() {
    global $is_secure;
    $token = generate_csrf_token();
    if (!$token) return false;

    setcookie(
        CSRF_TOKEN_COOKIE,
        $token,
        [
            'expires' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );
    return true;
}

// Clear CSRF token
function clear_csrf_token() {
    global $is_secure;
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME], $_SESSION[CSRF_TOKEN_NAME . '_created']);
        setcookie(CSRF_TOKEN_COOKIE, '', time() - 3600, '/', $_SERVER['HTTP_HOST'], $is_secure, true);
        return true;
    }
    return false;
}
?>
