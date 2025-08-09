<?php
// ============================================================================
// File: make-market/security-headers.php
// Description: Defines HTTP security headers to protect the Make Market
// Created by: Vina Network
// ============================================================================

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
// CORS – adjust the origin if Make Market API is accessed from another domain
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token, Authorization");
// Optional: disable caching if the data is sensitive
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
// Only allow content loading over HTTPS and block mixed content
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.googletagmanager.com; "
    . "connect-src 'self' $csp_base https://quote-api.jup.ag https://api.mainnet-beta.solana.com https://mainnet.helius-rpc.com https://www.google-analytics.com wss://quote-api.jup.ag wss://api.mainnet-beta.solana.com wss://mainnet.helius-rpc.com; "
    . "img-src 'self' $csp_base data: https:; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self';"
);
?>
