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
define('CSRF_TOKEN_NAME', 'csrf_token'); // Name of the CSRF token field in forms
define('CSRF_TOKEN_LENGTH', 32); // Length of the CSRF token
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie'); // Name of the CSRF cookie (for AJAX requests)

// Ensure session is active
function ensure_session($max_attempts = 3) {
    try {
        global $is_secure, $domain;
        $required_session_config = [
            'cookie_lifetime' => 0,
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $is_secure,
            'cookie_domain' => $domain
        ];

        $attempt = 0;
        while ($attempt < $max_attempts) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                $session_started = session_start($required_session_config);
                if (!$session_started) {
                    log_message("Failed to start session (attempt " . ($attempt + 1) . "): session_start failed", 'csrf.log', 'logs', 'ERROR');
                    $attempt++;
                    continue;
                }
                log_message("Session started with secure settings, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", domain=$domain", 'csrf.log', 'logs', 'INFO');
                return true;
            }

            // Verify existing session settings
            $session_params = session_get_cookie_params();
            $errors = [];

            if ($session_params['lifetime'] !== $required_session_config['cookie_lifetime']) {
                $errors[] = "Session cookie_lifetime mismatch: expected 0, got " . $session_params['lifetime'];
            }
            if ($session_params['httponly'] !== $required_session_config['cookie_httponly']) {
                $errors[] = "Session cookie_httponly mismatch: expected true, got " . ($session_params['httponly'] ? 'true' : 'false');
            }
            if ($session_params['samesite'] !== $required_session_config['cookie_samesite']) {
                $errors[] = "Session cookie_samesite mismatch: expected 'Strict', got " . $session_params['samesite'];
            }
            if ($session_params['secure'] !== $required_session_config['cookie_secure']) {
                $errors[] = "Session cookie_secure mismatch: expected " . ($is_secure ? 'true' : 'false') . ", got " . ($session_params['secure'] ? 'true' : 'false');
            }
            if ($session_params['domain'] !== $required_session_config['cookie_domain']) {
                $errors[] = "Session cookie_domain mismatch: expected $domain, got " . $session_params['domain'];
            }

            if (!empty($errors)) {
                log_message("Session validation failed (attempt " . ($attempt + 1) . "): " . implode("; ", $errors), 'csrf.log', 'logs', 'ERROR');
                session_destroy();
                $attempt++;
                continue;
            }

            log_message("Session validated successfully, session_id=" . session_id(), 'csrf.log', 'logs', 'INFO');
            return true;
        }

        log_message("Failed to start or validate session after $max_attempts attempts", 'csrf.log', 'logs', 'CRITICAL');
        return false;
    } catch (Exception $e) {
        log_message("Error starting session for CSRF: " . $e->getMessage(), 'csrf.log', 'logs', 'ERROR');
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
            log_message("CSRF token generated: " . $_SESSION[CSRF_TOKEN_NAME], 'csrf.log', 'logs', 'INFO');
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    } catch (Exception $e) {
        log_message("Error generating CSRF token: " . $e->getMessage(), 'csrf.log', 'logs', 'ERROR');
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

    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
        log_message("CSRF token validation failed: provided=$token, expected=" . $_SESSION[CSRF_TOKEN_NAME] . ", IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'csrf.log', 'logs', 'WARNING');
        return false;
    }
    
    log_message("CSRF token validated successfully: $token, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'csrf.log', 'logs', 'INFO');
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
            log_message("CSRF protection triggered: Invalid token", 'csrf.log', 'logs', 'WARNING');
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

    log_message("CSRF cookie set: $token", 'csrf.log', 'logs', 'INFO');
    return true;
}
?>
