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

// Dynamic Domain Name Definition
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$is_secure = $protocol === 'https://';
$domain = $_SERVER['HTTP_HOST'];
define('BASE_URL', $protocol . $domain . '/');
$csp_base = rtrim(BASE_URL, '/');

// Website root directory
define('ROOT_PATH', dirname(__DIR__) . '/');

// Logs directory
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('ACCOUNTS_PATH', LOGS_PATH . 'accounts/');
define('TOOLS_PATH', LOGS_PATH . 'tools/');
define('MAKE_MARKET_PATH', LOGS_PATH . 'make-market/');
define('ERROR_LOG_PATH', LOGS_PATH . 'error.txt');
define('MAX_LOG_SIZE', 1024 * 1024); // 1MB max log file size

// Load configuration
require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/csrf.php';
require_once ROOT_PATH . 'config/db.php';

// Define environment
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Initialize session with security options
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start([
        'cookie_lifetime' => 0,
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $is_secure,
        'cookie_domain' => $domain
    ])) {
        error_log("Failed to start session");
        log_message("Failed to start session, domain=$domain, is_secure=" . ($is_secure ? 'true' : 'false'), 'error.txt', 'logs', 'ERROR');
        http_response_code(500);
        exit('Server configuration error');
    }
    log_message("Session started, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain", 'bootstrap.log', 'logs', 'INFO');
} else {
    log_message("Session already started, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain", 'bootstrap.log', 'logs', 'DEBUG');
}

// PHP configuration
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', true);
// Ensure ERROR_LOG_PATH is writable
if (!ensure_directory_and_file(LOGS_PATH, ERROR_LOG_PATH)) {
    error_log("Cannot set error log path: " . ERROR_LOG_PATH);
} else {
    ini_set('error_log', ERROR_LOG_PATH);
}

// Check/create dir & file with correct permissions
function ensure_directory_and_file($dir_path, $file_path) {
    try {
        // Create dir if missing
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0755, true)) {
                error_log("Failed to create directory: $dir_path");
                return false;
            }
            if (!chmod($dir_path, 0755)) {
                error_log("Failed to set permissions for directory: $dir_path");
                return false;
            }
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
            if (!chmod($file_path, 0664)) {
                error_log("Failed to set permissions for file: $file_path");
                return false;
            }
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

// Rotate log file if it exceeds max size
function rotate_log_file($log_path) {
    if (file_exists($log_path) && filesize($log_path) > MAX_LOG_SIZE) {
        $backup_path = $log_path . '.' . date('Y-m-d_H-i-s') . '.bak';
        if (!rename($log_path, $backup_path)) {
            error_log("Failed to rotate log file: $log_path to $backup_path");
            return false;
        }
        if (file_put_contents($log_path, '') === false) {
            error_log("Failed to create new empty log file: $log_path");
            return false;
        }
        if (!chmod($log_path, 0664)) {
            error_log("Failed to set permissions for new log file: $log_path");
            return false;
        }
    }
    return true;
}

// Write log entry to file
function log_message($message, $log_file = 'app.log', $module = 'logs', $log_type = 'INFO') {
    static $checked_paths = [];

    if ($log_type === 'DEBUG' && (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development')) {
        return;
    }

    // Determine log directory based on module
    $dir_path = match ($module) {
        'accounts' => ACCOUNTS_PATH,
        'make-market' => MAKE_MARKET_PATH,
        'tools' => TOOLS_PATH,
        default => LOGS_PATH,
    };
    // Determine log file path
    $log_path = $dir_path . $log_file;

    // Cache directory/file check
    $cache_key = $dir_path . '|' . $log_path;
    if (!isset($checked_paths[$cache_key])) {
        if (!ensure_directory_and_file($dir_path, $log_path)) {
            error_log("Log setup failed for $log_path: $message");
            return;
        }
        $checked_paths[$cache_key] = true;
    }

    // Rotate log file if needed
    if (!rotate_log_file($log_path)) {
        error_log("Failed to rotate log file: $log_path");
        return;
    }

    // Write log entry
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;
    try {
        if (file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write log to $log_path: $message");
        } else {
            // Debug: Confirm log write
            error_log("Successfully wrote log to $log_path: $message");
        }
    } catch (Exception $e) {
        error_log("Log write error for $log_path: " . $e->getMessage());
    }
}
?>
