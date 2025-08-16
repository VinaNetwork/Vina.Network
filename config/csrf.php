<?php
// ============================================================================
// File: config/csrf.php
// Description: CSRF protection configuration and utilities for Vina Network
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// CSRF Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie');

// Ensure session is active
function ensure_session() {
    try {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        require_once __DIR__ . '/session.php';
        return session_status() === PHP_SESSION_ACTIVE;
    } catch (Exception $e) {
        log_message("Error ensuring session for CSRF: " . $e->getMessage() . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'csrf.log', 'logs', 'ERROR');
        return false;
    }
}

// Generate CSRF token and store in session
function generate_csrf_token() {
    try {
        if (!ensure_session()) {
            throw new Exception('Failed to start session');
        }
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            $_SESSION[CSRF_TOKEN_NAME . '_expires'] = time() + 3600; // Token expires after 1 hour
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    } catch (Exception $e) {
        log_message("Error generating CSRF token: " . $e->getMessage() . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'csrf.log', 'logs', 'ERROR');
        return false;
    }
}

// Validate CSRF token against session
function validate_csrf_token($token) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ? 'yes' : 'no';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    if (!ensure_session()) {
        log_message("CSRF token validation failed: session not active, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'csrf.log', 'logs', 'CRITICAL');
        return false;
    }

    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        log_message("CSRF token validation failed: token empty or session token missing, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'csrf.log', 'logs', 'CRITICAL');
        return false;
    }

    if (isset($_SESSION[CSRF_TOKEN_NAME . '_expires']) && time() > $_SESSION[CSRF_TOKEN_NAME . '_expires']) {
        log_message("CSRF token expired: $token, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'csrf.log', 'logs', 'WARNING');
        return false;
    }

    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
        log_message("CSRF token validation failed: provided=$token, expected=" . $_SESSION[CSRF_TOKEN_NAME] . ", IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'csrf.log', 'logs', 'WARNING');
        return false;
    }
    
    return true;
}

// Regenerate CSRF token after successful validation
function regenerate_csrf_token() {
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_expires']);
    }
    return generate_csrf_token();
}

// Generate hidden input field for CSRF token in forms
function get_csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

// Middleware to protect POST requests with CSRF validation
function csrf_protect() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_COOKIE[CSRF_TOKEN_COOKIE] ?? '';
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid CSRF token']);
            } else {
                header('Location: /error?message=Invalid+CSRF+token');
            }
            exit;
        }
        regenerate_csrf_token();
    }
}

// Set CSRF token in a cookie for AJAX requests
function set_csrf_cookie() {
    global $is_secure;
    
    $token = generate_csrf_token();
    if ($token === false) {
        return false;
    }

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
?>
