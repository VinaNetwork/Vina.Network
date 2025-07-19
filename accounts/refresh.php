<?php
// refresh.php
header('Content-Type: application/json');
require_once 'auth.php';

$auth = new Auth();
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

$publicKey = $auth->verifyJWT($token);
if (!$publicKey) {
    echo json_encode(['message' => 'Invalid token']);
    exit;
}

// Tạo JWT mới
$newToken = $auth->createJWT($publicKey);
echo json_encode(['token' => $newToken]);
?>
