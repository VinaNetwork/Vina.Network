<?php
// accounts/include/refresh.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.vina.network');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/bootstrap.php';

$auth = new Auth();
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

log_message("Refresh: Received refresh request", 'acc_auth.txt', 'accounts', 'INFO');

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
