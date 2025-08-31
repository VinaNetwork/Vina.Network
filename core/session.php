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
    }
}
?>
