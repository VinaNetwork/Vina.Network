<?php
// ============================================================================
// File: accounts/header-auth.php
// Description: Defines HTTP security headers to protect the Accounts
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

// Force HTTPS and prevent downgrade attacks
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Prevent clickjacking
header("X-Frame-Options: DENY");

// Prevent MIME type sniffing
header("X-Content-Type-Options: nosniff");

// Enable basic XSS protection (for older browsers)
header("X-XSS-Protection: 1; mode=block");

// Restrict referrer information
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy - allow Phantom Wallet, site scripts, and AJAX headers
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://www.googletagmanager.com; "
    . "connect-src 'self' $csp_base https://*.phantom.app https://www.google-analytics.com; "
    . "img-src 'self' $csp_base data: https:; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "report-uri /csp-violation-report;" // Add endpoint report
);
?>
