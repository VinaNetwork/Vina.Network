<?php
// ============================================================================
// File: accounts/clean-logs.php
// Description: Script to delete log files older than 7 days in /logs/accounts/.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/../config/bootstrap.php';

// Log directory
$log_dir = ACCOUNTS_PATH . '/logs/accounts/';
$max_age = 7 * 24 * 60 * 60; // 7 days in seconds

// Ensure log directory exists
if (!is_dir($log_dir)) {
    log_message("Log directory $log_dir does not exist", 'accounts.log', 'accounts', 'ERROR');
    exit;
}

// Get all log files
$log_files = glob($log_dir . '*.log');
$deleted_files = [];
$current_time = time();

foreach ($log_files as $file) {
    // Skip if not a file
    if (!is_file($file)) {
        continue;
    }

    // Check file modification time
    $mtime = filemtime($file);
    if ($mtime === false) {
        log_message("Failed to get modification time for $file", 'accounts.log', 'accounts', 'ERROR');
        continue;
    }

    // Delete files older than 7 days
    if ($current_time - $mtime > $max_age) {
        if (unlink($file)) {
            $deleted_files[] = basename($file);
            log_message("Deleted old log file: $file", 'accounts.log', 'accounts', 'INFO');
        } else {
            log_message("Failed to delete old log file: $file", 'accounts.log', 'accounts', 'ERROR');
        }
    }
}

// Log summary of deleted files
if (empty($deleted_files)) {
    log_message("No log files older than 7 days found in $log_dir", 'accounts.log', 'accounts', 'INFO');
} else {
    log_message("Deleted " . count($deleted_files) . " old log files: " . implode(', ', $deleted_files), 'accounts.log', 'accounts', 'INFO');
}

exit;
?>
