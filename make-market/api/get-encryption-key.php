<?php
// ============================================================================
// File: make-market/api/get-encryption-key.php
// Description: API to return encryption key with JWT authentication
// Created by: Vina Network
// ============================================================================

require_once '../vendor/autoload.php';
use Dotenv\Dotenv;
use VinaNetwork\JwtAuth;

header('Content-Type: application/json');

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/..'); // Cập nhật từ __DIR__ . '/../..'
$dotenv->load();
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';

// Kiểm tra JWT
$jwtAuth = new JwtAuth($JWT_SECRET);
$authResult = $jwtAuth->validateToken($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!$authResult['valid']) {
    http_response_code(401);
    file_put_contents(__DIR__ . '/../logs/auth_errors.log', date('Y-m-d H:i:s') . ": " . $authResult['error'] . "\n", FILE_APPEND);
    echo json_encode(['error' => $authResult['error']]);
    exit;
}

echo json_encode(['secretKey' => $_ENV['SECRET_KEY']]);
