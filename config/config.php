<?php
/*
 * ============================================================================
 * File: config.php
 * Description: Central configuration file for Vina Network.
 *              Defines global constants such as API keys, base paths,
 *              error logging settings, and security access control.
 *              Used throughout the entire project.
 * Created by: Vina Network Development Team
 * ============================================================================
 */

// Prevent direct access to this config file
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct script access allowed!');
}

// ---------------------------------------------------------------------------
// Helius API Configuration
// ---------------------------------------------------------------------------
if (!defined('HELIUS_API_KEY')) {
    define('HELIUS_API_KEY', '8eb75cd9-015a-4e24-9de2-5be9ee0f1c63'); // Helius API Key for Solana blockchain queries
}

// ---------------------------------------------------------------------------
// Logging and Path Configuration
// ---------------------------------------------------------------------------
if (!defined('ERROR_LOG_PATH')) {
    define('ERROR_LOG_PATH', '/var/www/vinanetwork/public_html/tools/error_log.txt'); // Path to error log file
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/var/www/vinanetwork/public_html/'); // Root base path of the Vina Network project
}
?>
