<?php
// ============================================================================
// File: accounts/refresh.php
// Description: API to refresh JWT using refresh token
// Created by: Vina Network
// ============================================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../make-market/vendor/autoload.php';
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

header('Content-Type: application/json');

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/../make-market');
$dotenv->load();
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_NAME = $_ENV['DB_NAME'] ?? 'vinanetwork';
$DB_USER = $_ENV['DB_USER'] ?? '';
$DB_PASS = $_ENV['DB_PASS'] ?? '';

// Kết nối database
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Database error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'Lỗi kết nối database']);
    exit;
}

// Nhận refresh token
$refreshToken = $_POST['refreshToken'] ?? '';
if (!$refreshToken) {
    http_response_code(400);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Thiếu refresh token\n", FILE_APPEND);
    echo json_encode(['error' => 'Thiếu refresh token']);
    exit;
}

// Kiểm tra refresh token
$stmt = $pdo->prepare('SELECT user_id, expires_at FROM refresh_tokens WHERE token = ?');
$stmt->execute([$refreshToken]);
$tokenData = $stmt->fetch();

if (!$tokenData || strtotime($tokenData['expires_at']) < time()) {
    http_response_code(401);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Refresh token không hợp lệ hoặc đã hết hạn\n", FILE_APPEND);
    echo json_encode(['error' => 'Refresh token không hợp lệ hoặc đã hết hạn']);
    exit;
}

// Lấy thông tin người dùng
$stmt = $pdo->prepare('SELECT solana_address FROM users WHERE id = ?');
$stmt->execute([$tokenData['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Người dùng không tồn tại\n", FILE_APPEND);
    echo json_encode(['error' => 'Người dùng không tồn tại']);
    exit;
}

// Tạo JWT mới
$payload = [
    'iat' => time(),
    'exp' => time() + 3600,
    'sub' => $user['solana_address'],
    'user_id' => $tokenData['user_id']
];
$jwt = JWT::encode($payload, $JWT_SECRET, 'HS256');

// Cập nhật refresh token mới
$newRefreshToken = bin2hex(random_bytes(32));
$stmt = $pdo->prepare('UPDATE refresh_tokens SET token = ?, expires_at = ? WHERE user_id = ?');
$stmt->execute([$newRefreshToken, date('Y-m-d H:i:s', time() + 604800), $tokenData['user_id']]);

echo json_encode(['token' => $jwt, 'refreshToken' => $newRefreshToken]);
