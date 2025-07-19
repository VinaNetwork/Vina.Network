<?php
// ============================================================================
// File: accounts/register.php
// Description: API to register user with Solana wallet address
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

// Nhận dữ liệu
$publicKey = $_POST['publicKey'] ?? '';
$signature = $_POST['signature'] ?? '';
$message = $_POST['message'] ?? 'Sign to register with Vina Network';

// Kiểm tra dữ liệu đầu vào
if (!$publicKey || !$signature || !$message) {
    http_response_code(400);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Thiếu tham số\n", FILE_APPEND);
    echo json_encode(['error' => 'Thiếu tham số']);
    exit;
}

// Xác minh chữ ký Solana
try {
    $isValid = sodium_crypto_sign_verify_detached(
        base64_decode($signature),
        $message,
        base64_decode($publicKey)
    );
    if (!$isValid) {
        http_response_code(401);
        file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Chữ ký không hợp lệ\n", FILE_APPEND);
        echo json_encode(['error' => 'Chữ ký không hợp lệ']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Lỗi xác minh chữ ký: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'Lỗi xác minh chữ ký']);
    exit;
}

// Kiểm tra ví đã đăng ký chưa
$stmt = $pdo->prepare('SELECT id FROM users WHERE solana_address = ?');
$stmt->execute([$publicKey]);
if ($stmt->fetch()) {
    http_response_code(409);
    file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Ví đã được đăng ký\n", FILE_APPEND);
    echo json_encode(['error' => 'Ví đã được đăng ký']);
    exit;
}

// Lưu ví vào database
$stmt = $pdo->prepare('INSERT INTO users (solana_address) VALUES (?)');
$stmt->execute([$publicKey]);
$userId = $pdo->lastInsertId();

// Tạo JWT
$payload = [
    'iat' => time(),
    'exp' => time() + 3600,
    'sub' => $publicKey,
    'user_id' => $userId
];
$jwt = JWT::encode($payload, $JWT_SECRET, 'HS256');
echo json_encode(['token' => $jwt]);
