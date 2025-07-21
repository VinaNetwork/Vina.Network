<?php
// ============================================================================
// File: config/bootstrap.php
// Description: Security check and utility functions for Vina Network modules
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    http_response_code(403);
    exit('No direct access allowed!');
}

// ---------------------------------------------------
// Define core path constants
// Used for logging and configuration across modules
// ---------------------------------------------------
define('ROOT_PATH', dirname(__DIR__) . '/');
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('ACCOUNTS_PATH', LOGS_PATH . 'accounts/'); // Thêm định nghĩa cho thư mục accounts
define('ERROR_LOG_PATH', LOGS_PATH . 'error.txt');

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
// Load configuration file
// ---------------------------------------------------
$config_path = ROOT_PATH . 'config/config.php';
if (!file_exists($config_path)) {
    error_log("bootstrap: config.php not found at $config_path");
    http_response_code(500);
    echo '<div class="result-error"><p>Error: Configuration file not found</p></div>';
    exit;
}
require_once $config_path;

// ---------------------------------------------------
// Ensure directory and file exist with correct permissions
// Creates directory and file if they don't exist, sets permissions
// @param string $dir_path  - Directory path to check/create
// @param string $file_path - File path to check/create
// @return bool - True if successful, false if failed
// ---------------------------------------------------
function ensure_directory_and_file($dir_path, $file_path) {
    try {
        // Create directory if it doesn't exist
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0700, true)) {
                error_log("Failed to create directory: $dir_path");
                return false;
            }
            chmod($dir_path, 0700);
        }
        // Check if directory is writable
        if (!is_writable($dir_path)) {
            error_log("Directory not writable: $dir_path");
            return false;
        }
        // Create file if it doesn't exist
        if (!file_exists($file_path)) {
            if (file_put_contents($file_path, '') === false) {
                error_log("Failed to create file: $file_path");
                return false;
            }
            chmod($file_path, 0600);
        }
        // Check if file is writable
        if (!is_writable($file_path)) {
            error_log("File not writable: $file_path");
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("Error in ensure_directory_and_file: " . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------
// Logging utility function
// Writes timestamped messages to the specified log file
// @param string $message    - The log content/message
// @param string $log_file   - Filename (e.g., acc_auth.txt)
// @param string $module     - Module name (e.g., accounts, tools); if empty, logs to ERROR_LOG_PATH
// @param string $log_type   - Optional: log level (INFO, ERROR, DEBUG, etc.)
// ---------------------------------------------------
function log_message($message, $log_file = 'acc_auth.txt', $module = 'accounts', $log_type = 'INFO') {
    $log_path = empty($module) ? ERROR_LOG_PATH : ACCOUNTS_PATH . $log_file; // Sử dụng ACCOUNTS_PATH
    $dir_path = empty($module) ? LOGS_PATH : ACCOUNTS_PATH; // Sử dụng ACCOUNTS_PATH
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;

    try {
        // Ensure the log directory and file are set up correctly
        if (!ensure_directory_and_file($dir_path, $log_path)) {
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
