<?php
// ============================================================================
// File: mm/get-logs.php
// Description: Handles client-side logging using core logging utilities for multiple modules.
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

require_once __DIR__ . '/../core/logging.php';

// Set response header
header('Content-Type: application/json');

// Validate POST request and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'], $data['log_type'], $data['log_file'], $data['module'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log data']);
    exit;
}

// Validate log file name to prevent directory traversal
$log_file = basename($data['log_file']); // Ensure only the file name is used
$module = $data['module'];
$level = strtoupper($data['log_type']);

// Get client information
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$url = $_SERVER['HTTP_REFERER'] ?? 'Unknown';

// Format log message
$message = "[IP:$ip_address] [URL:$url] [UA:$user_agent] {$data['message']}";

// Log the message using core logging function
log_message($message, $log_file, $module, $level);

echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;
?>
