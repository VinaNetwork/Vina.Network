<?php
// ============================================================================
// File: core/error.php
// Description: PHP configuration
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// PHP configuration
ini_set('display_errors', 0);          // Disable displaying errors on screen (production safety)
ini_set('display_startup_errors', 0);  // Disable displaying startup errors on screen
error_reporting(E_ALL);                // Report all PHP errors (for logging purposes)
ini_set('log_errors', true);           // Enable error logging to file
ini_set('error_log', ERROR_LOG_PATH);  // Set custom path for error log file
?>
