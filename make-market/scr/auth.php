<?php
// ============================================================================
// File: make-market/src/auth.php
// Description: JWT Authentication class
// Created by: Vina Network
// ============================================================================

namespace VinaNetwork;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth {
    private $jwtSecret;

    public function __construct($jwtSecret) {
        $this->jwtSecret = $jwtSecret;
    }

    public function validateToken($authHeader) {
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return ['valid' => false, 'error' => 'Thiếu hoặc sai định dạng token'];
        }
        try {
            JWT::decode($matches[1], new Key($this->jwtSecret, 'HS256'));
            return ['valid' => true];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Token không hợp lệ: ' . $e->getMessage()];
        }
    }
}
