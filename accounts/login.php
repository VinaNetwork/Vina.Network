<?php
// Đăng nhập tài khoản bằng ví Solana (ký message để xác minh)
require_once __DIR__ . '/../config/db.php';
require_once 'auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$wallet = $data['wallet'] ?? '';
$signature = $data['signature'] ?? '';
$message = $data['message'] ?? '';

if (!$wallet || !$signature || !$message) {
    http_response_code(400);
    echo json_encode(["error" => "Thiếu dữ liệu"]);
    exit;
}

if (!verify_signature($wallet, $message, $signature)) {
    http_response_code(403);
    echo json_encode(["error" => "Chữ ký không hợp lệ"]);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE wallet_address = ?");
    $stmt->execute([$wallet]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(["error" => "Tài khoản chưa đăng ký"]);
        exit;
    }

    $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE wallet_address = ?");
    $update->execute([$wallet]);

    $token = generate_jwt($wallet);
    echo json_encode(["token" => $token]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Lỗi hệ thống"]);
}
