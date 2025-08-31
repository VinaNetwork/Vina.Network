<?php
// ============================================================================
// File: core/session.php
// Description: Initialize session with hardened security options.
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'core/constants.php'; // $is_secure, $domain

// Initialize session with hardened security options
if (!defined('SESSION_STARTED')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 0,             // session cookie (destroy when browser closes)
            'use_strict_mode' => true,          // prevent session fixation
            'cookie_httponly' => true,          // prevent JS from reading cookie
            'cookie_samesite' => 'Strict',      // strongest CSRF defense (set 'Lax' if cross-site POST is needed)
            'cookie_secure' => $is_secure,      // only send cookie over HTTPS
            'cookie_domain' => $domain          // set domain dynamically
        ]);
        define('SESSION_STARTED', true);
    }
}

// Session regeneration to prevent fixation
if (!isset($_SESSION['regen_at']) || time() - $_SESSION['regen_at'] > 900) { // every 15 min
    session_regenerate_id(true);
    $_SESSION['regen_at'] = time();
}

// Bind fingerprint to prevent hijacking
if (!isset($_SESSION['fingerprint'])) {
    $fp = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (explode('.', $_SERVER['REMOTE_ADDR'])[0] ?? ''));
    $_SESSION['fingerprint'] = $fp;
} elseif ($_SESSION['fingerprint'] !== hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (explode('.', $_SERVER['REMOTE_ADDR'])[0] ?? ''))) {
    session_unset();
    session_destroy();
    http_response_code(403);
    exit('Session fingerprint mismatch');
}
?>
