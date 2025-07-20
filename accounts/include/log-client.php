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

if ($message) {
    log_message("Client: $message", 'auth.log', 'INFO');
    echo json_encode(['status' => 'success']);
} else {
    log_message("Client: Invalid log message received", 'auth.log', 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Invalid log message']);
}
?>
