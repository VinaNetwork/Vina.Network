<?php
// Làm mới JWT token nếu hợp lệ
require_once 'auth.php';

header('Content-Type: application/json');

$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

if (!$auth || !str_starts_with($auth, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(["error" => "Token không hợp lệ"]);
    exit;
}

$jwt = substr($auth, 7);
$wallet = verify_jwt($jwt);

if (!$wallet) {
    http_response_code(403);
    echo json_encode(["error" => "Token hết hạn hoặc sai"]);
    exit;
}

$newToken = generate_jwt($wallet);
echo json_encode(["token" => $newToken]);
