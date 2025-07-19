<?php
// ============================================================================
// File: api/login.php
// Description: API to generate JWT for authenticated users
// Created by: Vina Network
// ============================================================================

require_once '../make-market/vendor/autoload.php';
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

header('Content-Type: application/json');

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Giả lập kiểm tra đăng nhập (thay bằng logic thực tế, ví dụ: kiểm tra database)
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
    echo json_encode(['error' => 'Thông tin đăng nhập không hợp lệ']);
}
