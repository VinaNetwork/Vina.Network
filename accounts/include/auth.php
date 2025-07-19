<?php
// auth.php
require_once '../vendor/autoload.php'; // composer cho Firebase JWT
use \Firebase\JWT\JWT;

class Auth {
    private $pdo;
    private $jwt_secret = 'v5njta8HCXPdFQLWkbzC+q1x+zht34edaMDNer+WwKM='; // key bí mật

    public function __construct() {
        // Kết nối database
        $this->pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
