<?php
// ============================================================================
// File: core/logs/get-logs.php
// Description: Handles client-side logging for all modules with size limitation and enhanced security.
// Created by: Vina Network
// ============================================================================

// Set response header
header('Content-Type: application/json');

// Validate POST request and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Check user session (basic authorization)
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
$message = preg_replace("/[\r\n\t]+/", " ", $data['message']); // Remove newline character
$url = filter_var($data['url'], FILTER_VALIDATE_URL) ? $data['url'] : 'Invalid URL';
$userAgent = htmlspecialchars($data['userAgent'], ENT_QUOTES, 'UTF-8');
$level = strtoupper($data['level']);

// Validate log file name to prevent path traversal
$log_file_name = basename($data['log_file'] ?? 'general.log');
if (!preg_match('/^[a-zA-Z0-9_-]+\.log$/', $log_file_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid log file name']);
    exit;
}

// Determine module and log directory
$module = $data['module'] ?? 'general';
$log_dir = determine_log_directory($module);
$log_file = $log_dir . $log_file_name;

// Ensure log directory exists and rotate log file if needed
if (!ensure_directory_and_file($log_dir, $log_file)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create log directory or file']);
    exit;
}

// Format and log the message (đã loại bỏ IP address)
$formatted_message = "[URL:$url] [UA:$userAgent] $message";
if (!log_message($formatted_message, $log_file_name, $module, $level)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to write log']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;

/**
 * Determine log directory based on module
 * @param string $module Module name
 * @return string Log directory path
 */
function determine_log_directory($module) {
    $module_paths = [
        'accounts' => ACCOUNTS_PATH,
        'make-market' => MAKE_MARKET_PATH,
        'tools' => TOOLS_PATH,
        'logs' => LOGS_PATH
    ];
    
    return $module_paths[$module] ?? LOGS_PATH . $module . '/';
}
?>
