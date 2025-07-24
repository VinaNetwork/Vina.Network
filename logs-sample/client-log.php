<?php
// File: logs/client-log.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_file = __DIR__ . '/../logs/client.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $level = $data['level'] ?? 'INFO';
    $message = $data['message'] ?? 'No message provided';
    $userAgent = $data['userAgent'] ?? 'Unknown';
    $url = $data['url'] ?? 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $log_message = "[$timestamp] [$level] [IP:$ip] [URL:$url] [UA:$userAgent] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo json_encode(['status' => 'success']);
}
