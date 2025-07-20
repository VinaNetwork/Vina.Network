<?php
// ============================================================================
// File: accounts/include/refresh.php
// Description: Endpoint to refresh JWT for Vina Network Accounts
// Created by: Vina Network
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.vina.network');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/bootstrap.php';

$auth = new Auth();
$data = json_decode(file_get_contents('php://input'), true);
$csrf_token = $data['csrf_token'] ?? '';
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

log_message("Refresh: Received refresh request", 'acc_auth.txt', 'accounts', 'INFO');

if (!validate_csrf_token($csrf_token)) {
    log_message("Refresh: Invalid CSRF token", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(403);
    echo json_encode(['message' => 'Invalid CSRF token']);
    exit;
}

$publicKey = $auth->verifyJWT($token);
if (!$publicKey) {
    log_message("Refresh: Invalid token", 'acc_auth.txt', 'accounts', 'ERROR');
    echo json_encode(['message' => 'Invalid token']);
    exit;
}

$newToken = $auth->createJWT($publicKey);
log_message("Refresh: New token created for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');
echo json_encode(['token' => $newToken]);
?>
