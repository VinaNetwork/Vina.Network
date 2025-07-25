<?php
// ============================================================================
// File: accounts/client-log.php
// Description: Logging processing.
// Created by: Vina Network
// ============================================================================

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $level = $data['level'] ?? 'INFO';
    $message = $data['message'] ?? 'No message provided';
    $userAgent = $data['userAgent'] ?? 'Unknown';
    $url = $data['url'] ?? 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $log_message = "[IP:$ip] [URL:$url] [UA:$userAgent] $message";
    log_message($log_message, 'client.log', 'accounts', $level);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}
?>
