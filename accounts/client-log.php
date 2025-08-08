<?php
// ============================================================================
// File: accounts/client-log.php
// Description: Handles client-side logging with size limitation.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'config/bootstrap.php';

// Set response header
header('Content-Type: application/json');

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'], $data['level'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log data']);
    exit;
}

// Log file configuration
$log_dir = ACCOUNTS_PATH . '/logs/accounts/';
$log_file = $log_dir . 'client.log';
$max_size = 10 * 1024 * 1024; // 10MB in bytes

// Ensure log directory exists
if (!is_dir($log_dir)) {
    if (!mkdir($log_dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create log directory']);
        exit;
    }
}

// Check and rotate log file if size exceeds 10MB
if (file_exists($log_file) && filesize($log_file) >= $max_size) {
    $new_log_file = $log_dir . 'client-' . date('YmdHis') . '.log';
    if (!rename($log_file, $new_log_file)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to rotate log file']);
        exit;
    }
    log_message("Rotated log file to $new_log_file due to size limit (10MB)", 'client.log', 'accounts', 'INFO');
}

// Log the message
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$level = strtoupper($data['level']);
$message = "[IP:$ip_address] [URL:{$data['url']}] [UA:{$data['userAgent']}] {$data['message']}";
log_message($message, 'client.log', 'accounts', $level);

echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;
?>
