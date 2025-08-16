<?php
// ============================================================================
// File: config/constants.php
// Description: Dynamic Domain & URL constants
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Detect protocol more reliably (consider proxy headers)
$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
);
$protocol   = $is_https ? 'https://' : 'http://';
$is_secure  = $protocol === 'https://';

// Sanitize domain safely (keep port for BASE_URL but strip it for cookies)
$host = isset($_SERVER['HTTP_HOST'])
    ? preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $_SERVER['HTTP_HOST'])
    : 'localhost';

// Extract domain (no port) for cookies
$host_parts = explode(':', $host, 2);
$domain     = $host_parts[0]; // safe domain (no port, used for cookies)
$host_full  = $host;          // full host (may include port, used for BASE_URL)

// Handle base path (in case app runs in subdirectory)
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_path   = ($script_name === '/' || $script_name === '.') ? '' : $script_name;

// Define constants
define('BASE_URL', $protocol . $host_full . $base_path . '/');
define('CSP_BASE', rtrim(BASE_URL, '/'));
?>
