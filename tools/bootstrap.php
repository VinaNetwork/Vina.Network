<?php
// tools/bootstrap.php

// ---------------------------------------------------
// Security check: Prevent direct access to this file
// ---------------------------------------------------
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

// ---------------------------------------------------
// Load configuration file
// ---------------------------------------------------
require_once ROOT_PATH . 'config/config.php';

// ---------------------------------------------------
// Logging utility function
// Writes timestamped messages to the specified log file
//
// @param string $message    - The log content/message
// @param string $log_file   - Filename within TOOLS_PATH to write logs
// @param string $log_type   - Optional: log level (INFO, ERROR, DEBUG, etc.)
// ---------------------------------------------------
function log_message($message, $log_file = 'debug_log.txt', $log_type = 'INFO') {
    $log_path = TOOLS_PATH . $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$log_type] $message" . PHP_EOL;

    try {
        if (file_put_contents($log_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write log to $log_path: $message");
        }
    } catch (Exception $e) {
        error_log("Log error: " . $e->getMessage());
    }
}
?>
