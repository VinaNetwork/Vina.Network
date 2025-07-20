<?php
// accounts/include/refresh.php
header('Content-Type: application/json');
require_once '../../config/bootstrap.php';

$auth = new Auth();
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

log_message("Refresh: Received refresh request", 'auth.log', 'INFO');

$publicKey = $auth->verifyJWT($token);
if (!$publicKey) {
    log_message("Refresh: Invalid token", 'auth.log', 'ERROR');
    echo json_encode(['message' => 'Invalid token']);
    exit;
}

$newToken = $auth->createJWT($publicKey);
log_message("Refresh: New token created for publicKey=$publicKey", 'auth.log', 'INFO');
echo json_encode(['token' => $newToken]);
?>
