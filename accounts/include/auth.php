<?php
// accounts/include/auth.php
require_once '../../config/bootstrap.php';
require_once '../vendor/autoload.php';
use \Firebase\JWT\JWT;

class Auth {
    private $pdo;
    private $jwt_secret = JWT_SECRET;

    public function __construct() {
        $this->pdo = getDB();
        log_message("Auth: Initialized Auth class", 'auth.log', 'INFO');
    }

    public function verifySignature($publicKey, $message, $signature) {
        try {
            $publicKeyObj = new \SolanaPhpSdk\PublicKey($publicKey);
            $messageBytes = (new \SolanaPhpSdk\Util\Buffer($message))->toArray();
            $signatureBytes = base64_decode($signature);
            $result = nacl_sign_detached_verify($messageBytes, $signatureBytes, $publicKeyObj->toBytes());
            log_message("Auth: Signature verification for publicKey=$publicKey: " . ($result ? 'Success' : 'Failed'), 'auth.log', 'INFO');
            return $result;
        } catch (Exception $e) {
            log_message("Auth: Signature verification failed: " . $e->getMessage(), 'auth.log', 'ERROR');
            return false;
        }
    }

    public function createJWT($publicKey) {
        try {
            $payload = [
                'iss' => 'vina.network',
                'sub' => $publicKey,
                'iat' => time(),
                'exp' => time() + 3600
            ];
            $token = JWT::encode($payload, $this->jwt_secret, 'HS256');
            log_message("Auth: Created JWT for publicKey=$publicKey", 'auth.log', 'INFO');
            return $token;
        } catch (Exception $e) {
            log_message("Auth: Failed to create JWT: " . $e->getMessage(), 'auth.log', 'ERROR');
            throw $e;
        }
    }

    public function verifyJWT($token) {
        try {
            $decoded = JWT::decode($token, $this->jwt_secret, ['HS256']);
            log_message("Auth: JWT verification successful for publicKey=" . $decoded->sub, 'auth.log', 'INFO');
            return $decoded->sub;
        } catch (Exception $e) {
            log_message("Auth: JWT verification failed: " . $e->getMessage(), 'auth.log', 'ERROR');
            return false;
        }
    }
}
?>
