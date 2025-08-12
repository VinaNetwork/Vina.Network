<?php
// ============================================================================
// File: config/csrf.php
// Description: CSRF protection configuration and utilities for Vina Network
// Created by: Vina Network
// ============================================================================

// CSRF Configuration
define('CSRF_TOKEN_NAME', 'csrf_token'); // Name of the CSRF token field in forms
define('CSRF_TOKEN_LENGTH', 32); // Length of the CSRF token
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie'); // Name of the CSRF cookie (for AJAX requests)

// Generate CSRF token and store in session
function generate_csrf_token() {
    try {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            log_message("CSRF token generated: " . $_SESSION[CSRF_TOKEN_NAME], 'security.log', 'make-market', 'INFO');
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    } catch (Exception $e) {
        log_message("Error generating CSRF token: " . $e->getMessage(), 'security.log', 'make-market', 'ERROR');
        return false;
    }
}

// Validate CSRF token against session
function validate_csrf_token($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
        log_message("CSRF token validation failed: provided=$token, expected=" . ($_SESSION[CSRF_TOKEN_NAME] ?? 'none'), 'security.log', 'make-market', 'ERROR');
        return false;
    }
    log_message("CSRF token validated successfully: $token", 'security.log', 'make-market', 'INFO');
    return true;
}

// Regenerate CSRF token after successful validation
function regenerate_csrf_token() {
    unset($_SESSION[CSRF_TOKEN_NAME]);
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
            log_message("CSRF protection triggered: Invalid token", 'security.log', 'make-market', 'WARNING');
            exit('Invalid CSRF token');
        }
        regenerate_csrf_token(); // Regenerate token after successful validation
    }
}

// Set CSRF token in a cookie for AJAX requests
function set_csrf_cookie() {
    $token = generate_csrf_token();
    setcookie(CSRF_TOKEN_COOKIE, $token, [
        'expires' => 0, // Session cookie
        'path' => '/',
        'domain' => '.vina.network',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    log_message("CSRF cookie set: $token", 'security.log', 'make-market', 'INFO');
}
?>
