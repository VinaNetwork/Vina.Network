<?php
// ============================================================================
// File: accounts/log.php
// Description: Handles client-side logging with size limitation and enhanced security (CSRF check removed).
// Created by: Vina Network
// ============================================================================

if (!defined('VINANETWORK_ENTRY')) {
    define('VINANETWORK_ENTRY', true);
}

$root_path = __DIR__ . '/../';
require_once $root_path . 'core/logging.php';

// Set response header
header('Content-Type: application/json');

// Validate POST request and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Check user session (basic authorization)
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'], $data['level'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log data']);
    exit;
}

// Sanitize input data to prevent log injection
$message = preg_replace("/[\r\n\t]+/", " ", $data['message']); // Loại bỏ ký tự xuống dòng
$url = filter_var($data['url'], FILTER_VALIDATE_URL) ? $data['url'] : 'Invalid URL';
$userAgent = htmlspecialchars($data['userAgent'], ENT_QUOTES, 'UTF-8');
$level = strtoupper($data['level']);

// Validate log file name to prevent path traversal
$log_file_name = basename($data['log_file'] ?? 'accounts.log');
if (!preg_match('/^[a-zA-Z0-9_-]+\.log$/', $log_file_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log file name']);
    exit;
}

// Log file configuration
$log_dir = ACCOUNTS_PATH; // Use ACCOUNTS_PATH directly (logs/accounts/)
$log_file = $log_dir . $log_file_name;

// Ensure log directory exists and rotate log file if needed
if (!ensure_directory_and_file($log_dir, $log_file)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create log directory or file']);
    exit;
}

// Get IP address safely
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_trusted_proxy()) { // Giả định hàm is_trusted_proxy tồn tại
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

// Format and log the message
$formatted_message = "[IP:$ip_address] [URL:$url] [UA:$userAgent] $message";
if (!log_message($formatted_message, $log_file_name, 'accounts', $level)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to write log']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;
?>
