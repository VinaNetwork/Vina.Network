<?php
// ============================================================================
// File: config/session.php
// Description: Initialize session with security options.
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'core/constants.php'; // Dynamic Domain Name Definition

// Initialize session with security options
if (!defined('SESSION_STARTED')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 0,
            'use_strict_mode' => true,
            'cookie_httponly' => false,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $is_secure, // Configured in config/constants.php
            'cookie_domain' => $domain     // Configured in config/constants.php
        ]);
        define('SESSION_STARTED', true);   // Mark session as started
        log_message(
            "Session started, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
            'bootstrap.log',
            'logs',
            'INFO'
        );
    } else {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            log_message(
                "Session already active, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain, uri=" . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
                'bootstrap.log',
                'logs',
                'DEBUG'
            );
        }
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
