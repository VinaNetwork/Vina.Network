<?php
// ============================================================================
// File: acc/write-logs.php
// Description: Handles client-side logging using core logging utilities for multiple modules.
// Created by: Vina Network
// ============================================================================

// Web root
$root_path = __DIR__ . '/../';
require_once $root_path . 'acc/bootstrap.php';

// Set response header
header('Content-Type: application/json');

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("Invalid request method: {$_SERVER['REQUEST_METHOD']}, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST.']);
    exit;
}

// Check X-Auth-Token
$headers = getallheaders();
$authToken = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : null;

if ($authToken !== JWT_SECRET) {
    log_message("Invalid or missing X-Auth-Token, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'], $data['log_type'], $data['log_file'], $data['module'])) {
    log_message("Invalid log data: Missing required fields, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'accounts.log', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid log data']);
    exit;
}

// Validate log file name to prevent directory traversal
$log_file = basename($data['log_file']);
$module = $data['module'];
$level = strtoupper($data['log_type']);

// Get client information
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$url = $_SERVER['HTTP_REFERER'] ?? 'Unknown';

// Format log message
$message = "[IP:$ip_address] [URL:$url] [UA:$user_agent] {$data['message']}";

// Log the message
log_message($message, $log_file, $module, $level);

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;
?>
