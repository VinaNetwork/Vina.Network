<?php
// ============================================================================
// File: accounts/login.php
// Description: API to generate JWT for authenticated users
// Created by: Vina Network
// ============================================================================

require_once __DIR__ . '/../make-market/vendor/autoload.php'; // Cập nhật đường dẫn
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

header('Content-Type: application/json');

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/../make-market'); // Cập nhật từ __DIR__ . '/..'
$dotenv->load();
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Giả lập kiểm tra đăng nhập (thay bằng logic database thực tế)
if ($username === 'admin' && $password === 'your_secure_password') {
    $payload = [
        'iat' => time(),
        'exp' => time() + 3600, // Token hết hạn sau 1 giờ
        'sub' => $username
    ];
    $jwt = JWT::encode($payload, $JWT_SECRET, 'HS256');
    echo json_encode(['token' => $jwt]);
} else {
    http_response_code(401);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Thông tin đăng nhập không hợp lệ\n", FILE_APPEND);
    echo json_encode(['error' => 'Thông tin đăng nhập không hợp lệ']);
}
