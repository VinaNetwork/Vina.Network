<?php
// accounts/include/auth.php
require_once '../../config/bootstrap.php';
require_once '../../vendor/autoload.php';
use Firebase\JWT\JWT;
use SolanaPhpSdk\Connection;
use SolanaPhpSdk\SolanaRpcClient;
use SolanaPhpSdk\PublicKey;
use SolanaPhpSdk\Util\Buffer;

class Auth {
    private $pdo;
    private $jwt_secret = JWT_SECRET;
    private $rpc_client;

    public function __construct() {
        $this->pdo = getDB();
        $this->rpc_client = new Connection(new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT));
        log_message("Auth: Initialized Auth class", 'acc_auth.txt', 'accounts', 'INFO');
    }

    public function verifySignature($publicKey, $message, $signature) {
        try {
            $publicKeyObj = new PublicKey($publicKey);
            $messageBytes = (new Buffer($message))->toArray();
            $signatureBytes = base64_decode($signature); // SDK xử lý Base58, nhưng client gửi Base64
            $result = sodium_crypto_sign_verify_detached($signatureBytes, $messageBytes, $publicKeyObj->toBytes());
            log_message("Auth: Signature verification for publicKey=$publicKey: " . ($result ? 'Success' : 'Failed'), 'acc_auth.txt', 'accounts', 'INFO');

            // Lưu public_key và last_login vào database
            if ($result) {
                $stmt = $this->pdo->prepare("INSERT INTO users (public_key, created_at, last_login) VALUES (:public_key, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_login = NOW()");
                $stmt->execute(['public_key' => $publicKey]);
                log_message("Auth: Saved/Updated publicKey=$publicKey in database", 'acc_auth.txt', 'accounts', 'INFO');
            }
            return $result;
        } catch (Exception $e) {
            log_message("Auth: Signature verification failed: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
            return false;
        }
    }

    public function getBalance($publicKey) {
        try {
            $balance = $this->rpc_client->getBalance($publicKey);
            log_message("Auth: Fetched balance for publicKey=$publicKey: $balance lamports", 'acc_auth.txt', 'accounts', 'INFO');
            return $balance;
        } catch (Exception $e) {
            log_message("Auth: Failed to fetch balance: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
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
            log_message("Auth: Created JWT for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');
            return $token;
        } catch (Exception $e) {
            log_message("Auth: Failed to create JWT: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
            throw $e;
        }
    }

    public function verifyJWT($token) {
        try {
            $decoded = JWT::decode($token, $this->jwt_secret, ['HS256']);
            log_message("Auth: JWT verification successful for publicKey=" . $decoded->sub, 'acc_auth.txt', 'accounts', 'INFO');
            return $decoded->sub;
        } catch (Exception $e) {
            log_message("Auth: JWT verification failed: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
            return false;
        }
    }
}
?>
