<?php
// ============================================================================
// File: accounts/include/log-client.php
// Description: Endpoint to receive and log client-side messages for Accounts
// Created by: Vina Network
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.vina.network');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';
$csrf_token = $data['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    log_message("Client: Invalid CSRF token", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if ($message) {
    log_message("Client: $message", 'acc_auth.txt', 'accounts', 'INFO');
    echo json_encode(['status' => 'success']);
} else {
    log_message("Client: Invalid log message received", 'acc_auth.txt', 'accounts', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid log message']);
}
?>
