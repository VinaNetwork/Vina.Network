<?php
// ============================================================================
// File: core/csrf.php
// Description: CSRF protection configuration and utilities for Vina Network
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// CSRF Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');          // Name of the CSRF token field in forms
define('CSRF_TOKEN_LENGTH', 32);                  // Length of the CSRF token
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie'); // Name of the CSRF cookie (for AJAX requests)
define('CSRF_TOKEN_TTL', 86400);                  // Token time-to-live in seconds (24h)

// Ensure session is active
function ensure_session() {
    global $is_secure, $domain;
    try {
        // Check if session is already started by bootstrap.php
        if (defined('SESSION_STARTED') && session_status() === PHP_SESSION_ACTIVE) {
            log_message("Session already active, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", domain=$domain", 'bootstrap.log', 'logs', 'INFO');
            return true;
        }

        // Start session if not already started
        if (!defined('SESSION_STARTED') && session_status() === PHP_SESSION_NONE) {
            $required_session_config = [
                'cookie_lifetime' => 0,
                'use_strict_mode' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => $is_secure, // Configuration on core/constants.php
                'cookie_domain' => $domain     // Configuration on core/constants.php
            ];

            $session_started = session_start($required_session_config);
            if ($session_started) {
                define('SESSION_STARTED', true);
                log_message(
                    "Session started with secure settings, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
                    'bootstrap.log',
                    'logs',
                    'INFO'
                );
                return true;
            } else {
                log_message("Failed to start session: session_start failed, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
                return false;
            }
        }

        log_message("Session not active and failed to start, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'CRITICAL');
        return false;
    } catch (Exception $e) {
        log_message("Error starting session for CSRF: " . $e->getMessage() . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
        return false;
    }
}

// Generate CSRF token and store in session with TTL
function generate_csrf_token() {
    try {
        if (!ensure_session()) {
            throw new Exception('Failed to start session');
        }

        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            $_SESSION[CSRF_TOKEN_NAME . '_created'] = time(); // Store creation time
            log_message("CSRF token generated: " . $_SESSION[CSRF_TOKEN_NAME] . ", created_at=" . $_SESSION[CSRF_TOKEN_NAME . '_created'] . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'INFO');
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    } catch (Exception $e) {
        log_message("Error generating CSRF token: " . $e->getMessage() . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
        return false;
    }
}

// Validate CSRF token against session with TTL check
function validate_csrf_token($token) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ? 'yes' : 'no';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    if (!ensure_session()) {
        log_message("CSRF token validation failed: session not active, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'bootstrap.log', 'logs', 'CRITICAL');
        return false;
    }

    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        log_message("CSRF token validation failed: token empty or session token missing, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'bootstrap.log', 'logs', 'CRITICAL');
        return false;
    }

    // Check TTL
    if (isset($_SESSION[CSRF_TOKEN_NAME . '_created']) && (time() - $_SESSION[CSRF_TOKEN_NAME . '_created']) > CSRF_TOKEN_TTL) {
        log_message("CSRF token validation failed: token expired, provided=$token, created_at=" . $_SESSION[CSRF_TOKEN_NAME . '_created'] . ", IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'bootstrap.log', 'logs', 'WARNING');
        return false;
    }

    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
        log_message("CSRF token validation failed: provided=$token, expected=" . $_SESSION[CSRF_TOKEN_NAME] . ", IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'bootstrap.log', 'logs', 'WARNING');
        return false;
    }
    
    log_message("CSRF token validated successfully: $token, created_at=" . $_SESSION[CSRF_TOKEN_NAME . '_created'] . ", IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'bootstrap.log', 'logs', 'INFO');
    return true;
}

// Regenerate CSRF token after successful validation
function regenerate_csrf_token() {
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_created']); // Remove creation time
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
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? $_COOKIE[CSRF_TOKEN_COOKIE] ?? '';
        if (!validate_csrf_token($token)) {
            log_message("CSRF protection triggered: Invalid or expired token, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'WARNING');
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid or expired CSRF token']);
            } else {
                header('Location: /error?message=Invalid+or+expired+CSRF+token');
            }
            exit;
        }
        // Keep tokens fixed throughout the process
        log_message("CSRF token validated successfully for POST request: $token", 'bootstrap.log', 'logs', 'INFO');
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
        'httponly' => false,
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

    log_message("CSRF cookie set: $token, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'INFO');
    return true;
}

// Clear CSRF token when transaction process completes
function clear_csrf_token() {
    global $is_secure;
    if (ensure_session()) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_created']);
        // Clear the CSRF cookie
        setcookie(CSRF_TOKEN_COOKIE, '', time() - 3600, '/', $_SERVER['HTTP_HOST'], $is_secure, true);
        log_message("CSRF token cleared, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'INFO');
        return true;
    }
    log_message("Failed to clear CSRF token: session not active, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'bootstrap.log', 'logs', 'ERROR');
    return false;
}
?>
