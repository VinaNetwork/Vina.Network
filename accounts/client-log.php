<?php
// File: accounts/client-log.php
if (!defined('VINANETWORK_ENTRY')) {
    die("Access denied: Direct access to this file is not allowed.");
}

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_file = ACCOUNTS_PATH . 'client.log';
    $data = json_decode(file_get_contents('php://input'), true);
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $level = $data['level'] ?? 'INFO';
    $message = $data['message'] ?? 'No message provided';
    $userAgent = $data['userAgent'] ?? 'Unknown';
    $url = $data['url'] ?? 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    // Rút ngắn public_key trong log
    $message = preg_replace('/([123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44})/', substr('$1', 0, 4) . '...'. substr('$1', -4), $message);
    $log_message = "[$timestamp] [$level] [IP:$ip] [URL:$url] [UA:$userAgent] $message\n";
    // Giới hạn kích thước log (10MB)
    if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) {
        rename($log_file, $log_file . '.' . time() . '.bak');
    }
    if (!file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX)) {
        error_log("Failed to write to client.log: Check permissions for $log_file");
    }
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}
