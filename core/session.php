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

// Session configuration BEFORE starting session
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 days
ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60);
session_set_cookie_params([
    'lifetime' => 30 * 24 * 60 * 60,
    'path' => '/',
    'domain' => $domain,     // core/constants.php
    'secure' => $is_secure,  // core/constants.php
    'httponly' => false,     // Allow JavaScript to access PHPSESSID
    'samesite' => 'Strict'
]);

// Initialize session with hardened security options
if (!defined('SESSION_STARTED')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'use_strict_mode' => true,          // prevent session fixation
            'use_only_cookies' => 1,            // only use cookies
            'use_trans_sid' => 0,               // disable URL-based sessions
            'cookie_httponly' => false,          // Allow JavaScript to access PHPSESSID
            'cookie_samesite' => 'Strict',      // strongest CSRF defense
            'cookie_secure' => $is_secure,      // only send cookie over HTTPS
            'cookie_domain' => $domain          // set domain dynamically
        ]);
        define('SESSION_STARTED', true);
        
        // Regenerate ID immediately for new sessions
        if (empty($_SESSION)) {
            session_regenerate_id(true);
        }
    }
}

// Session regeneration to prevent fixation (more frequent)
if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) { // every 5 min
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// Enhanced fingerprint to prevent hijacking
if (!isset($_SESSION['fingerprint'])) {
    $fp = hash('sha256', 
        ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . 
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') . '|' .
        (isset($_SERVER['REMOTE_ADDR']) ? implode('.', array_slice(explode('.', $_SERVER['REMOTE_ADDR']), 0, 2)) : '') // First 2 octets
    );
    $_SESSION['fingerprint'] = $fp;
} else {
    $current_fp = hash('sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . 
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') . '|' .
        (isset($_SERVER['REMOTE_ADDR']) ? implode('.', array_slice(explode('.', $_SERVER['REMOTE_ADDR']), 0, 2)) : '')
    );
    
    if ($_SESSION['fingerprint'] !== $current_fp) {
        // Log the incident before destroying
        error_log("Session fingerprint mismatch for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/', $domain, $is_secure, true);
        http_response_code(403);
        exit('Session security violation detected');
    }
}
?>
