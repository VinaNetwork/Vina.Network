<?php
// ============================================================================
// File: config/bootstrap.php
// Description: Security check and utility functions for Vina Network modules
// Created by: Vina Network
// ============================================================================

// Access Conditions
if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Website root directory
define('ROOT_PATH', dirname(__DIR__) . '/');
// Load configuration
require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/csrf.php';
require_once ROOT_PATH . 'config/db.php';

// PHP configuration
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);
?>
