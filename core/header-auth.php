<?php
// ============================================================================
// File: core/header-auth.php
// Description: Defines HTTP security headers to protect both Make Market and Accounts
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Prevent clickjacking (block iframe embedding from other domains)
header("X-Frame-Options: DENY");
// Prevent the browser from MIME type sniffing
header("X-Content-Type-Options: nosniff");
// Enable XSS Protection (mainly useful for old IE, Chrome/Edge have removed it)
header("X-XSS-Protection: 1; mode=block");
// HSTS: enforce HTTPS for 6 months (including all subdomains)
header("Strict-Transport-Security: max-age=15552000; includeSubDomains; preload");
// Control the referrer information sent with requests
header("Referrer-Policy: strict-origin-when-cross-origin");
// Only allow resource loading if the browser supports specific security features
header("Permissions-Policy: accelerometer=(), camera=(), microphone=(), geolocation=(), payment=()");
// CORS – adjust the origin based on allowed origins from constants
$origin = $_SERVER['HTTP_ORIGIN'] ?? BASE_URL;
if (in_array($origin, ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: " . BASE_URL);
}
header('Access-Control-Allow-Credentials: true');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-CSRF-Token, Authorization");
// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
// Optional: disable caching if the data is sensitive
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
// Content Security Policy - combined from both files
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://www.googletagmanager.com; "
    . "connect-src 'self' $csp_base https://quote-api.jup.ag https://*.jup.ag wss://*.jup.ag "
    . "https://api.mainnet-beta.solana.com https://mainnet.helius-rpc.com https://api.devnet.solana.com https://api.testnet.solana.com "
    . "https://www.google-analytics.com "
    . "https://*.phantom.app; "
    . "img-src 'self' $csp_base data: https:; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self';"
);
?>
