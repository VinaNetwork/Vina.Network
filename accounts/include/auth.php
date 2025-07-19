<?php
// accounts/include/auth.php
require_once '../../config/db.php'; // Kết nối database
require_once '../../config/config.php'; // Thêm file cấu hình chung
require_once '../vendor/autoload.php'; // Nếu dùng composer cho Firebase JWT
use \Firebase\JWT\JWT;

class Auth {
    private $pdo;
    private $jwt_secret = JWT_SECRET; // Sử dụng hằng số từ config.php

    public function __construct() {
        // Sử dụng hàm getDB() để kết nối database
        $this->pdo = getDB();
    }

    // Xác minh chữ ký Solana
    public function verifySignature($publicKey, $message, $signature) {
        $publicKey = new \SolanaPhpSdk\PublicKey($publicKey);
        $messageBytes = (new \SolanaPhpSdk\Util\Buffer($message))->toArray();
        $signatureBytes = base64_decode($signature);
        return nacl_sign_detached_verify($messageBytes, $signatureBytes, $publicKey->toBytes());
    }

    // Tạo JWT
    public function createJWT($publicKey) {
        $payload = [
            'iss' => 'vina.network',
            'sub' => $publicKey,
            'iat' => time(),
            'exp' => time() + 3600 // Hết hạn sau 1 giờ
        ];
        return JWT::encode($payload, $this->jwt_secret, 'HS256');
    }

    // Kiểm tra JWT
    public function verifyJWT($token) {
        try {
            $decoded = JWT::decode($token, $this->jwt_secret, ['HS256']);
            return $decoded->sub;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
