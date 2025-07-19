<?php
// ============================================================================
// File: accounts/auth.php
// Description: Shared JWT authentication logic
// Created by: Vina Network
// ============================================================================

require_once __DIR__ . '/../make-market/vendor/autoload.php';
use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth {
    private $secret;

    public function __construct($secret) {
        $this->secret = $secret;
    }

    public function validateToken($authHeader) {
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return ['valid' => false, 'error' => 'Token không hợp lệ'];
        }

        $jwt = $matches[1];
        try {
            $decoded = JWT::decode($jwt, new Key($this->secret, 'HS256'));
            return ['valid' => true, 'payload' => (array) $decoded];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../make-market/logs/auth_errors.log', date('Y-m-d H:i:s') . ": Lỗi xác thực JWT: " . $e->getMessage() . "\n", FILE_APPEND);
            return ['valid' => false, 'error' => 'Lỗi xác thực JWT: ' . $e->getMessage()];
        }
    }
}

// Load biến môi trường
$dotenv = Dotenv::createImmutable(__DIR__ . '/../make-market');
$dotenv->load();
$JWT_SECRET = $_ENV['JWT_SECRET'] ?? '';
