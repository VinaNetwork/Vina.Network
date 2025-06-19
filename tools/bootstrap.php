<?php
// ============================================================================
// File: tools/bootstrap.php
// Description: Security check: Prevent direct access to this file
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// ---------------------------------------------------
// Define core path constants
// Used across the tools module for easier path management
// ---------------------------------------------------
define('ROOT_PATH', dirname(__DIR__) . '/');
define('TOOLS_PATH', ROOT_PATH . 'tools/');
define('NFT_HOLDERS_PATH', TOOLS_PATH . 'nft-holders/');
define('LOGS_PATH', TOOLS_PATH . 'logs/');
define('ERROR_LOG_PATH', LOGS_PATH . 'php_errors.txt');

// ---------------------------------------------------
// Load configuration file
// ---------------------------------------------------
require_once ROOT_PATH . 'config/config.php';

// ---------------------------------------------------
// Logging utility function
// Writes timestamped messages to the specified log file
//
// @param string $message    - The log content/message
// @param string $log_file   - Filename within LOGS_PATH to write logs
// @param string $log_type   - Optional: log level (INFO, ERROR, DEBUG, etc.)
// ---------------------------------------------------
function log_message($message, $log_file = 'debug_log.txt', $log_type = 'INFO') {
    $log_path = LOGS_PATH . $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;

    try {
        // Create LOGS_PATH if it doesn't exist
        if (!is_dir(LOGS_PATH)) {
            if (!mkdir(LOGS_PATH, 0764, true)) {
                error_log("Failed to create log directory: " . LOGS_PATH);
                return;
            }
            chown(LOGS_PATH, 'www-data');
            chgrp(LOGS_PATH, 'www-data');
            chmod(LOGS_PATH, 0764);
        }
        // Ensure log file exists
        if (!file_exists($log_path)) {
            touch($log_path);
            chown($log_path, 'www-data');
            chgrp($log_path, 'www-data');
            chmod($log_path, 0664);
        }
        if (file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write log to $log_path: $message");
        }
    } catch (Exception $e) {
        error_log("Log error: " . $e->getMessage());
    }
}

// ---------------------------------------------------
// Generate CSRF token
// Creates a unique token stored in session for form security
// @return string - The CSRF token
// ---------------------------------------------------
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ---------------------------------------------------
// Validate CSRF token
// Checks if the provided token matches the session token
// @param string $token - The token to validate
// @return bool - True if valid, false otherwise
// ---------------------------------------------------
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
