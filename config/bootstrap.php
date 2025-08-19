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
// Logs directory
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('TOOLS_PATH', LOGS_PATH . 'tools/');
define('ERROR_LOG_PATH', LOGS_PATH . 'error.txt');

// Check/create dir & file with correct permissions
function ensure_directory_and_file($dir_path, $file_path) {
    try {
        // Create dir if missing
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0755, true)) {
                error_log("Failed to create directory: $dir_path");
                return false;
            }
            chmod($dir_path, 0755);
        }
        // Check dir writable
        if (!is_writable($dir_path)) {
            error_log("Directory not writable: $dir_path");
            return false;
        }
        // Create file if missing
        if (!file_exists($file_path)) {
            if (file_put_contents($file_path, '') === false) {
                error_log("Failed to create file: $file_path");
                return false;
            }
            chmod($file_path, 0664);
        }
        // Check file writable
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

// Write log entry to file
function log_message($message, $log_file = 'tools.log', $module = 'tools', $log_type = 'INFO') {
    if ($log_type === 'DEBUG' && (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development')) {
        return;
    }
    $dir_path = empty($module) ? LOGS_PATH : ($module === 'accounts' ? ACCOUNTS_PATH : ($module === 'make-market' ? MAKE_MARKET_PATH : TOOLS_PATH));
    $log_path = empty($module) ? ERROR_LOG_PATH : ($module === 'accounts' ? ACCOUNTS_PATH . $log_file : ($module === 'make-market' ? MAKE_MARKET_PATH . $log_file : TOOLS_PATH . $log_file));
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;
    try {
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

// Create CSRF token in session
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token from session
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
