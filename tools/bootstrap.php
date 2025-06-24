<?php
// ============================================================================
// File: tools/bootstrap.php
// Description: Security check and utility functions for tools module
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// ---------------------------------------------------
// PHP configuration
// Set error handling and session
// ---------------------------------------------------
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
session_start();
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

// ---------------------------------------------------
// Define core path constants
// Used across the tools module for easier path management
// ---------------------------------------------------
define('ROOT_PATH', dirname(__DIR__) . '/');
define('TOOLS_PATH', ROOT_PATH . 'tools/');
define('NFT_INFO_PATH', TOOLS_PATH . 'nft-info/');
define('NFT_HOLDERS_PATH', TOOLS_PATH . 'nft-holders/');
define('WALLET_ANALYSIS_PATH', TOOLS_PATH . 'wallet-analysis/');
define('LOGS_PATH', TOOLS_PATH . 'logs/');
define('ERROR_LOG_PATH', LOGS_PATH . 'php_errors.txt');

// ---------------------------------------------------
// Load configuration file
// ---------------------------------------------------
require_once ROOT_PATH . 'config/config.php';

// ---------------------------------------------------
// Ensure directory and file exist with correct permissions
// Creates directory and file if they don't exist, sets permissions
// @param string $dir_path  - Directory path to check/create
// @param string $file_path - File path to check/create
// @param string $log_file  - Log file for errors (default: debug_log.txt)
// @return bool - True if successful, false if failed
// ---------------------------------------------------
function ensure_directory_and_file($dir_path, $file_path, $log_file = 'debug_log.txt') {
    try {
        // Create directory if it doesn't exist
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0764, true)) {
                log_message("Failed to create directory: $dir_path", $log_file, 'ERROR');
                return false;
            }
            chown($dir_path, 'www-data');
            chgrp($dir_path, 'www-data');
            chmod($dir_path, 0764);
            log_message("Created directory: $dir_path", $log_file, 'INFO');
        }
        // Check if directory is writable
        if (!is_writable($dir_path)) {
            log_message("Directory not writable: $dir_path", $log_file, 'ERROR');
            return false;
        }
        // Create file if it doesn't exist
        if (!file_exists($file_path)) {
            if (file_put_contents($file_path, json_encode([])) === false) {
                log_message("Failed to create file: $file_path", $log_file, 'ERROR');
                return false;
            }
            chown($file_path, 'www-data');
            chgrp($file_path, 'www-data');
            chmod($file_path, 0664);
            log_message("Created file: $file_path", $log_file, 'INFO');
        }
        // Check if file is writable
        if (!is_writable($file_path)) {
            log_message("File not writable: $file_path", $log_file, 'ERROR');
            return false;
        }
        return true;
    } catch (Exception $e) {
        log_message("Error in ensure_directory_and_file: " . $e->getMessage(), $log_file, 'ERROR');
        return false;
    }
}

// ---------------------------------------------------
// Logging utility function
// Writes timestamped messages to the specified log file
// @param string $message    - The log content/message
// @param string $log_file   - Filename within LOGS_PATH to write logs
// @param string $log_type   - Optional: log level (INFO, ERROR, DEBUG, etc.)
// ---------------------------------------------------
function log_message($message, $log_file = 'debug_log.txt', $log_type = 'INFO') {
    $log_path = LOGS_PATH . $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;

    try {
        // Use ensure_directory_and_file for log directory and file
        if (!ensure_directory_and_file(LOGS_PATH, $log_path, $log_file)) {
            error_log("Log setup failed for $log_path: $message");
            return;
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
