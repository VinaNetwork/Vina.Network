<?php
// ============================================================================
// File: core/logs/logging.php
// Description: Logging utilities for Vina Network without session handling
// Created by: Vina Network
// ============================================================================

// Access Conditions - allow includes from other files
if (!defined('VINANETWORK_ENTRY') && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('No direct access allowed!');
}

// Website root directory
define('ROOT_PATH', dirname(__DIR__) . '/');
// Logs directory
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('ACCOUNTS_PATH', LOGS_PATH . 'accounts/');
define('TOOLS_PATH', LOGS_PATH . 'tools/');
define('MAKE_MARKET_PATH', LOGS_PATH . 'make-market/');
define('ERROR_LOG_PATH', LOGS_PATH . 'error.txt');
define('MAX_LOG_SIZE', 1024 * 1024);
// Application environment (development, staging, production)
define('ENVIRONMENT', 'development');

// Cache for directory/file checks
$checked_paths = [];

// Check/create dir & file with correct permissions
function ensure_directory_and_file($dir_path, $file_path) {
    global $checked_paths;
    $cache_key = $dir_path . '|' . $file_path;

    // Check cache first
    if (isset($checked_paths[$cache_key])) {
        return $checked_paths[$cache_key];
    }

    try {
        // Create dir if missing
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0755, true)) {
                error_log("Failed to create directory: $dir_path");
                $checked_paths[$cache_key] = false;
                return false;
            }
            chmod($dir_path, 0755);
        }
        // Check dir writable
        if (!is_writable($dir_path)) {
            error_log("Directory not writable: $dir_path");
            $checked_paths[$cache_key] = false;
            return false;
        }
        // Create file if missing
        if (!file_exists($file_path)) {
            if (file_put_contents($file_path, '') === false) {
                error_log("Failed to create file: $file_path");
                $checked_paths[$cache_key] = false;
                return false;
            }
            chmod($file_path, 0664);
        }
        // Check file writable
        if (!is_writable($file_path)) {
            error_log("File not writable: $file_path");
            $checked_paths[$cache_key] = false;
            return false;
        }
        $checked_paths[$cache_key] = true;
        return true;
    } catch (Exception $e) {
        error_log("Error in ensure_directory_and_file: " . $e->getMessage());
        $checked_paths[$cache_key] = false;
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
function log_message($message, $log_file = 'bootstrap.log', $module = 'logs', $log_type = 'INFO') {
    if ($log_type === 'DEBUG' && (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development')) {
        return;
    }

    // Simplified module-to-path mapping
    $module_paths = [
        'accounts' => ACCOUNTS_PATH,
        'make-market' => MAKE_MARKET_PATH,
        'tools' => TOOLS_PATH,
        'logs' => LOGS_PATH
    ];
    $dir_path = $module_paths[$module] ?? LOGS_PATH;
    $log_path = empty($module) ? ERROR_LOG_PATH : $dir_path . $log_file;

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
?>
