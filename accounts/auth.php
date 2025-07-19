<?php
// Xử lý xác thực ví Solana + tạo và kiểm tra JWT
require_once __DIR__ . '/../vendor/autoload.php'; // Firebase\JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

define("JWT_SECRET", "RANDOM_SUPER_SECRET_KEY_123"); // Bro nên đổi cái này
define("JWT_EXP", 86400); // 24h

function getDB() {
    $host = "localhost";
    $db = "vina";
    $user = "root";
    $pass = "your_password";
    return new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
}

// Tạo JWT
function generate_jwt($wallet) {
    $payload = [
        'wallet' => $wallet,
        'iat' => time(),
        'exp' => time() + JWT_EXP
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

// Kiểm tra JWT
function verify_jwt($jwt) {
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
        return $decoded->wallet ?? false;
    } catch (Exception $e) {
        return false;
    }
}

// Xác minh chữ ký từ ví Solana (tạm thời mock lại true)
function verify_signature($wallet, $message, $signature) {
    // TODO: Bro có thể gọi Helius hoặc endpoint riêng xác minh chữ ký Solana
    return true; // tạm thời luôn đúng để test
}
