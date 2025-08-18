<?php
// ============================================================================
// File: mm/csrf/csrf.php
// Description: CSRF protection configuration and utilities for Vina Network
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../../';
require_once $root_path . 'config/bootstrap.php';

// CSRF Configuration
define('CSRF_TOKEN_NAME', 'csrf_token'); // Name of the CSRF token field in forms
define('CSRF_TOKEN_LENGTH', 32); // Length of the CSRF token
define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie'); // Name of the CSRF cookie (for AJAX requests)

// Ensure session is active
function ensure_session() {
    global $is_secure, $domain;
    try {
        // Check if session is already started by bootstrap.php
        if (defined('SESSION_STARTED') && session_status() === PHP_SESSION_ACTIVE) {
            log_message("Session already active, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", domain=$domain", 'make-market.log', 'make-market', 'INFO');
            return true;
        }

        // Start session if not already started
        if (!defined('SESSION_STARTED') && session_status() === PHP_SESSION_NONE) {
            $required_session_config = [
                'cookie_lifetime' => 0,
                'use_strict_mode' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => $is_secure, // Configuration on config/constants.php
                'cookie_domain' => $domain     // Configuration on config/constants.php
            ];

            $session_started = session_start($required_session_config);
            if ($session_started) {
                define('SESSION_STARTED', true);
                log_message(
                    "Session started with secure settings, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
                    'make-market.log',
                    'make-market',
                    'INFO'
                );
                return true;
            } else {
                log_message("Failed to start session: session_start failed, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
                return false;
            }
        }

        log_message("Session not active and failed to start, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'CRITICAL');
        return false;
    } catch (Exception $e) {
        log_message("Error starting session for CSRF: " . $e->getMessage() . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
        return false;
    }
}

// Generate CSRF token and store in session, optionally tied to transactionId
function generate_csrf_token($transactionId = null) {
    try {
        if (!ensure_session()) {
            throw new Exception('Failed to start session');
        }

        $sessionKey = $transactionId ? 'csrf_token_' . $transactionId : CSRF_TOKEN_NAME;
        if (empty($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            log_message("CSRF token generated for " . ($transactionId ? "transaction $transactionId" : "session") . ": " . $_SESSION[$sessionKey] . ", uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'INFO');
        }
        return $_SESSION[$sessionKey];
    } catch (Exception $e) {
        log_message("Error generating CSRF token: " . $e->getMessage() . ", transactionId=$transactionId, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
        return false;
    }
}

// Validate CSRF token against session or database
function validate_csrf_token($token, $transactionId = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ? 'yes' : 'no';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    if (!ensure_session()) {
        log_message("CSRF token validation failed: session not active, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'make-market.log', 'make-market', 'CRITICAL');
        return false;
    }

    $sessionKey = $transactionId ? 'csrf_token_' . $transactionId : CSRF_TOKEN_NAME;
    if (!isset($_SESSION[$sessionKey]) || empty($token)) {
        // Fallback to database for transaction-specific token
        if ($transactionId) {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT csrf_token FROM make_market WHERE id = ? AND status != 'completed'");
                $stmt->execute([$transactionId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['csrf_token'] && hash_equals($row['csrf_token'], $token)) {
                    log_message("CSRF token validated via database for transaction $transactionId: $token, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'make-market.log', 'make-market', 'INFO');
                    return true;
                }
            } catch (PDOException $e) {
                log_message("Database error during CSRF validation for transaction $transactionId: {$e->getMessage()}, IP=$ip, URI=$uri", 'make-market.log', 'make-market', 'ERROR');
            }
        }
        log_message("CSRF token validation failed: token empty or session token missing, transactionId=$transactionId, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'make-market.log', 'make-market', 'CRITICAL');
        return false;
    }

    if (!hash_equals($_SESSION[$sessionKey], $token)) {
        log_message("CSRF token validation failed: provided=$token, expected=" . $_SESSION[$sessionKey] . ", transactionId=$transactionId, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'make-market.log', 'make-market', 'WARNING');
        return false;
    }
    
    log_message("CSRF token validated successfully: $token, transactionId=$transactionId, IP=$ip, URI=$uri, Method=$method, AJAX=$is_ajax, User-Agent=$user_agent", 'make-market.log', 'make-market', 'INFO');
    return true;
}

// Clear CSRF token for a specific transaction
function clear_csrf_token($transactionId) {
    if (!ensure_session()) {
        log_message("Failed to clear CSRF token: session not active, transactionId=$transactionId, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'ERROR');
        return false;
    }

    $sessionKey = 'csrf_token_' . $transactionId;
    if (isset($_SESSION[$sessionKey])) {
        unset($_SESSION[$sessionKey]);
        log_message("CSRF token cleared from session for transaction $transactionId", 'make-market.log', 'make-market', 'INFO');
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE make_market SET csrf_token = NULL WHERE id = ?");
        $stmt->execute([$transactionId]);
        log_message("CSRF token cleared from database for transaction $transactionId", 'make-market.log', 'make-market', 'INFO');
        return true;
    } catch (PDOException $e) {
        log_message("Failed to clear CSRF token from database for transaction $transactionId: {$e->getMessage()}", 'make-market.log', 'make-market', 'ERROR');
        return false;
    }
}

// Regenerate CSRF token after successful validation
function regenerate_csrf_token($transactionId = null) {
    if (ensure_session()) {
        $sessionKey = $transactionId ? 'csrf_token_' . $transactionId : CSRF_TOKEN_NAME;
        unset($_SESSION[$sessionKey]);
    }
    return generate_csrf_token($transactionId);
}

// Generate hidden input field for CSRF token in forms
function get_csrf_field($transactionId = null) {
    $token = generate_csrf_token($transactionId);
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

// Middleware to protect POST requests with CSRF validation
function csrf_protect($transactionId = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? $_COOKIE[CSRF_TOKEN_COOKIE] ?? '';
        if (!validate_csrf_token($token, $transactionId)) {
            log_message("CSRF protection triggered: Invalid token, transactionId=$transactionId, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'WARNING');
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token', 'new_csrf_token' => generate_csrf_token($transactionId)]);
            } else {
                header('Location: /error?message=Invalid+CSRF+token');
            }
            exit;
        }
        // Chỉ regenerate token nếu không gắn với transactionId
        if (!$transactionId) {
            regenerate_csrf_token();
        }
    }
}

// Set CSRF token in a cookie for AJAX requests
function set_csrf_cookie($token = null, $transactionId = null) {
    global $is_secure;
    
    if (!$token) {
        $token = generate_csrf_token($transactionId);
    }
    if ($token === false) {
        return false;
    }

    $options = [
        'expires' => 0, // Session cookie
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
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

    log_message("CSRF cookie set: $token, transactionId=$transactionId, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'), 'make-market.log', 'make-market', 'INFO');
    return true;
}
?>
