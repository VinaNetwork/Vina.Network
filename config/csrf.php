<?php
// ============================================================================
// File: config/csrf.php
// Description: CSRF protection configuration and utilities for Vina Network
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/bootstrap.php';

// CSRF Configuration
define('CSRF_TOKEN_NAME', 'csrf_token'); // Name of the CSRF token field in forms
define('CSRF_TOKEN_LENGTH', 32); // Length of the CSRF token
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie'); // Name of the CSRF cookie (for AJAX requests)

// Ensure session is active
function ensure_session() {
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_lifetime' => 0,
                'use_strict_mode' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'cookie_secure' => $GLOBALS['is_secure'],
                'cookie_domain' => $_SERVER['HTTP_HOST']
            ]);
            log_message("Session restarted for CSRF, session_id=" . session_id(), 'security.log', 'logs', 'INFO');
        }
        return true;
    } catch (Exception $e) {
        log_message("Error starting session for CSRF: " . $e->getMessage(), 'security.log', 'logs', 'ERROR');
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
            log_message("CSRF token generated: " . $_SESSION[CSRF_TOKEN_NAME], 'security.log', 'logs', 'INFO');
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    } catch (Exception $e) {
        log_message("Error generating CSRF token: " . $e->getMessage(), 'security.log', 'logs', 'ERROR');
        return false;
    }
}

// Validate CSRF token against session
function validate_csrf_token($token) {
    if (!ensure_session() || !isset($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        log_message("CSRF token validation failed: session not active or token empty", 'security.log', 'logs', 'ERROR');
        return false;
    }

    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
        log_message("CSRF token validation failed: provided=$token, expected=" . $_SESSION[CSRF_TOKEN_NAME], 'security.log', 'logs', 'ERROR');
        return false;
    }
    
    log_message("CSRF token validated successfully: $token", 'security.log', 'logs', 'INFO');
    return true;
}

// Regenerate CSRF token after successful validation
function regenerate_csrf_token() {
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
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
            log_message("CSRF protection triggered: Invalid token", 'security.log', 'logs', 'WARNING');
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid CSRF token']);
            } else {
                header('Location: /error?message=Invalid+CSRF+token');
            }
            exit;
        }
        regenerate_csrf_token(); // Regenerate token after successful validation
    }
}

// Set CSRF token in a cookie for AJAX requests
function set_csrf_cookie() {
    global $is_secure;
    
    $token = generate_csrf_token();
    if ($token === false) {
        return false;
    }

    $options = [
        'expires' => 0, // Session cookie
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ];

    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        setcookie(CSRF_TOKEN_COOKIE, $token, $options);
    } else {
        setcookie(
            CSRF_TOKEN_COOKIE,
            $token,
            $options['expires'],
            $options['path'],
            $options['domain'],
            $options['secure'],
            $options['httponly']
        );
    }

    log_message("CSRF cookie set: $token", 'security.log', 'logs', 'INFO');
    return true;
}
?>
