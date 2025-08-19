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

// Dynamic Domain Name Definition
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];
define('BASE_URL', $protocol . $domain . '/');

// ---------------------------------------------------
// Define core path constants
// ---------------------------------------------------
define('ROOT_PATH', dirname(__DIR__) . '/');
define('LOGS_PATH', ROOT_PATH . 'logs/');
define('TOOLS_PATH', LOGS_PATH . 'tools/');
define('ERROR_LOG_PATH', LOGS_PATH . 'error.txt');

// ---------------------------------------------------
// PHP configuration
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
// ---------------------------------------------------
function ensure_directory_and_file($dir_path, $file_path) {
    try {
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0755, true)) {
                error_log("Failed to create directory: $dir_path");
                return false;
            }
            chmod($dir_path, 0755);
        }
        if (!is_writable($dir_path)) {
            error_log("Directory not writable: $dir_path");
            return false;
        }
        if (!file_exists($file_path)) {
            if (file_put_contents($file_path, '') === false) {
                error_log("Failed to create file: $file_path");
                return false;
            }
            chmod($file_path, 0664);
        }
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
// ---------------------------------------------------
function log_message($message, $log_file = 'tools.log', $module = 'tools', $log_type = 'INFO') {
    $dir_path = empty($module) ? LOGS_PATH : TOOLS_PATH;
    $log_path = empty($module) ? ERROR_LOG_PATH : TOOLS_PATH . $log_file;
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

// ---------------------------------------------------
// CSRF utilities
// ---------------------------------------------------
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
