<?php
// ============================================================================
// File: mm/log.php
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

// Validate POST request and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    log_message("Invalid request to log.php: method={$_SERVER['REQUEST_METHOD']}, AJAX=" . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'none'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Check session
if (!ensure_session()) {
    log_message("Session not active for logging request", 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Session not active']);
    exit;
}

// Protect POST requests with CSRF
try {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? $_COOKIE[CSRF_TOKEN_COOKIE] ?? '';
    $_POST[CSRF_TOKEN_NAME] = $csrf_token;
    csrf_protect();
} catch (Exception $e) {
    log_message("CSRF validation failed in log.php: {$e->getMessage()}, provided_token=$csrf_token, session_id=" . (session_id() ?: 'none'), 'make-market.log', 'make-market', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'], $data['level'])) {
    log_message("Invalid log data received", 'make-market.log', 'make-market', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid log data']);
    exit;
}

// Log file configuration
$log_dir = MAKE_MARKET_PATH . '/logs/make-market/';
$log_file = $log_dir . 'make-market.log';
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
    log_message("Rotated log file to $new_log_file due to size limit (10MB)", 'make-market.log', 'make-market', 'INFO');
}

// Log the message
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$level = strtoupper($data['level']);
$message = "[IP:$ip_address] [URL:{$data['url']}] [UA:{$data['userAgent']}] {$data['message']}";
log_message($message, 'make-market.log', 'make-market', $level);

echo json_encode(['status' => 'success', 'message' => 'Log recorded']);
exit;
?>
