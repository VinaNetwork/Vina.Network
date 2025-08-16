<?php
// ============================================================================
// File: config/error.php
// Description: PHP configuration
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// PHP configuration
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);
?>
