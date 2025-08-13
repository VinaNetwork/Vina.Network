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
// Determine the protocol: https or http
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
require_once ROOT_PATH . 'config/csrf.php';
require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'config/db.php';

// Initialize session with security options
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure' => $is_secure,
        'cookie_domain' => $domain
    ]);
    log_message("Session started, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain", 'make-market.log', 'make-market', 'INFO');
} else {
    log_message("Session already started, session_id=" . session_id() . ", secure=" . ($is_secure ? 'true' : 'false') . ", cookie_domain=$domain", 'make-market.log', 'make-market', 'DEBUG');
}

// PHP configuration
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', true);
ini_set('error_log', ERROR_LOG_PATH);

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

// Rotate log file if it exceeds max size
function rotate_log_file($log_path) {
    if (file_exists($log_path) && filesize($log_path) > MAX_LOG_SIZE) {
        $backup_path = $log_path . '.' . date('Y-m-d_H-i-s') . '.bak';
        if (!rename($log_path, $backup_path)) {
            error_log("Failed to rotate log file: $log_path");
            return false;
        }
        file_put_contents($log_path, ''); // Create new empty log file
        chmod($log_path, 0664);
    }
    return true;
}

// Write log entry to file
function log_message($message, $log_file = 'accounts.log', $module = 'accounts', $log_type = 'INFO') {
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
        rotate_log_file($log_path);
        if (file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write log to $log_path: $message");
        }
    } catch (Exception $e) {
        error_log("Log error: " . $e->getMessage());
    }
}

// Define environment
define('ENVIRONMENT', 'development');
?>
