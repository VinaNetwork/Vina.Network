<?php
// ============================================================================
// File: make-market/api/get-encryption-key.php
// Description: API to return encryption key with JWT authentication
// Created by: Vina Network
// ============================================================================

require_once '../vendor/autoload.php'; // Cập nhật đường dẫn
use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..'); // Trỏ đến .env ở thư mục gốc
$dotenv->load();
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';

// Kiểm tra JWT
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Thiếu hoặc sai định dạng token']);
    exit;
}

$token = $matches[1];
try {
    $decoded = JWT::decode($token, new Key($JWT_SECRET, 'HS256'));
    echo json_encode(['secretKey' => $_ENV['SECRET_KEY']]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ: ' . $e->getMessage()]);
    exit;
}
