<?php
// ============================================================================
// File: config/constants.php
// Description: Dynamic Domain Name Definition
// Created by: Vina Network
// ============================================================================

// Access Conditions - allow includes from other files
if (!defined('VINANETWORK_ENTRY') && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Dynamic Domain Name Definition
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$is_secure = $protocol === 'https://';
$domain = filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_URL) ?: 'localhost'; // Sanitize HTTP_HOST
define('BASE_URL', $protocol . $domain . '/');
$csp_base = rtrim(BASE_URL, '/');

// List of allowed sources
define('ALLOWED_ORIGINS', [
    BASE_URL,
    'http://localhost',
    'http://localhost:8080'
]);
?>
