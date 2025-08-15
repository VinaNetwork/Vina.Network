<?php
// ============================================================================
// File: config/csrf.php
// Description: CSRF protection utilities + AJAX refresh endpoint (Optimized)
// Author: Vina Network
// ============================================================================

declare(strict_types=1);

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/bootstrap.php';

// ==== CSRF CONFIG ====
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
    define('CSRF_TOKEN_LENGTH', 32); // bytes => 64 hex chars
    define('CSRF_TOKEN_COOKIE', 'csrf_token_cookie');
    define('CSRF_TOKEN_TTL', 1200); // 20 minutes
    define('CSRF_SESSION_PREFIX', 'csrf_');
}

// ==== SESSION HANDLER (Optimized) ====
function ensure_session(): bool {
    static $session_initialized = false;
    
    if ($session_initialized) {
        return true;
    }

    global $is_secure, $domain;
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        $session_initialized = true;
        return true;
    }

    $config = [
        'cookie_lifetime' => 0,
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $is_secure,
        'cookie_domain' => $domain,
        'read_and_close' => false // Ensure we can write to session later
    ];

    if (!session_start($config)) {
        error_log('CSRF Protection: Failed to start session');
        return false;
    }

    $session_initialized = true;
    return true;
}

// ==== TOKEN FUNCTIONS (Optimized) ====
function generate_csrf_token(): string|false {
    if (!ensure_session()) {
        return false;
    }

    $now = time();
    $token_key = CSRF_SESSION_PREFIX . CSRF_TOKEN_NAME;
    $time_key = CSRF_SESSION_PREFIX . 'token_time';

    if (
        empty($_SESSION[$token_key]) ||
        empty($_SESSION[$time_key]) ||
        ($now - $_SESSION[$time_key]) > CSRF_TOKEN_TTL
    ) {
        try {
            $_SESSION[$token_key] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            $_SESSION[$time_key] = $now;
        } catch (Exception $e) {
            error_log('CSRF Token Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    return $_SESSION[$token_key];
}

function validate_csrf_token(?string $token): bool {
    if (!ensure_session() || empty($token)) {
        return false;
    }

    $token_key = CSRF_SESSION_PREFIX . CSRF_TOKEN_NAME;
    $time_key = CSRF_SESSION_PREFIX . 'token_time';

    if (empty($_SESSION[$token_key])) {
        return false;
    }

    // Validate time first (cheaper operation)
    if (time() - ($_SESSION[$time_key] ?? 0) > CSRF_TOKEN_TTL) {
        return false;
    }

    return hash_equals($_SESSION[$token_key], $token);
}

function regenerate_csrf_token(): string|false {
    $token_key = CSRF_SESSION_PREFIX . CSRF_TOKEN_NAME;
    $time_key = CSRF_SESSION_PREFIX . 'token_time';

    if (ensure_session()) {
        unset($_SESSION[$token_key], $_SESSION[$time_key]);
    }
    
    return generate_csrf_token();
}

function get_csrf_field(): string {
    $token = generate_csrf_token();
    return sprintf(
        '<input type="hidden" name="%s" value="%s">',
        htmlspecialchars(CSRF_TOKEN_NAME, ENT_QUOTES),
        htmlspecialchars($token ?? '', ENT_QUOTES)
    );
}

function csrf_protect(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            exit(json_encode(['error' => 'Invalid CSRF token']));
        }
        // Only regenerate if validation passed
        regenerate_csrf_token();
    }
}

function set_csrf_cookie(?string $token = null): bool {
    global $is_secure;
    
    if (!$token) {
        $token = generate_csrf_token();
    }
    
    if (!$token) {
        return false;
    }

    $options = [
        'expires' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    return setcookie(CSRF_TOKEN_COOKIE, $token, $options);
}

// ==== REFRESH TOKEN ENDPOINT (Optimized) ====
if (
    php_sapi_name() !== 'cli' &&
    isset($_GET['action']) && 
    $_GET['action'] === 'refresh' &&
    $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    // Prepare headers first for security
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Validate request
    $allowed_host = $_SERVER['HTTP_HOST'];
    $origin = parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST);
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    
    $is_valid_request = (
        ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' &&
        (!$origin || $origin === $allowed_host) &&
        (!$referer || $referer === $allowed_host)
    );

    if (!$is_valid_request) {
        http_response_code(403);
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
        error_log('CSRF Refresh Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to refresh token']);
    }
    
    exit;
}
