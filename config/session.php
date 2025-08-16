<?php
// ============================================================================
// File: config/session.php
// Description: Initialize session with security options.
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Initialize session with security options
if (!defined('SESSION_STARTED')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 0,
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $is_secure,
            'cookie_domain' => $domain
        ]);
        define('SESSION_STARTED', true); // Mark session as started
        log_message(
            "Session started, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
            'bootstrap.log',
            'logs',
            'INFO'
        );
    } else {
        log_message(
            "Session already active, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
            'bootstrap.log',
            'logs',
            'WARNING'
        );
    }
} else {
    log_message(
        "Attempt to start session ignored, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
        'bootstrap.log',
        'logs',
        'WARNING'
    );
}
?>
