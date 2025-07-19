<?php
header('Content-Type: application/json');

// Giả định kiểm tra xác thực (ví dụ: JWT)
$authToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($authToken, 'Bearer your-auth-token') === false) {
    echo json_encode(['error' => 'Xác thực thất bại']);
    exit;
}

require_once '../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo json_encode([
    'secretKey' => $_ENV['SECRET_KEY']
]);
