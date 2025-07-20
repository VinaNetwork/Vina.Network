<?php
// accounts/include/acc-api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.vina.network');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/bootstrap.php';

$auth = new Auth();
$data = json_decode(file_get_contents('php://input'), true);

$action = $data['action'] ?? '';
$publicKey = $data['publicKey'] ?? '';
$message = $data['message'] ?? '';
$signature = $data['signature'] ?? '';

log_message("API: Received request action=$action, publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');

if (empty($action) || empty($publicKey) || empty($message) || empty($signature)) {
    log_message("API: Invalid input for action=$action", 'acc_auth.txt', 'accounts', 'ERROR');
    echo json_encode(['message' => 'Invalid input']);
    exit;
}

if (!$auth->verifySignature($publicKey, $message, $signature)) {
    log_message("API: Invalid signature for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'ERROR');
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

if ($action === 'register') {
    try {
        $stmt = $auth->pdo->prepare('SELECT id FROM users WHERE public_key = ?');
        $stmt->execute([$publicKey]);
        if ($stmt->fetch()) {
            log_message("API: Registration failed - User already exists: publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'ERROR');
            echo json_encode(['message' => 'User already exists']);
            exit;
        }

        $stmt = $auth->pdo->prepare('INSERT INTO users (public_key) VALUES (?)');
        $stmt->execute([$publicKey]);
        log_message("API: Registration successful for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');
        echo json_encode(['message' => 'Registration successful']);
    } catch (PDOException $e) {
        log_message("API: Registration failed for publicKey=$publicKey: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
        echo json_encode(['message' => 'Registration failed']);
        exit;
    }
} elseif ($action === 'login') {
    try {
        $stmt = $auth->pdo->prepare('SELECT id FROM users WHERE public_key = ?');
        $stmt->execute([$publicKey]);
        if (!$stmt->fetch()) {
            log_message("API: Login failed - User not found: publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'ERROR');
            echo json_encode(['message' => 'User not found']);
            exit;
        }

        $token = $auth->createJWT($publicKey);
        log_message("API: Login successful for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');
        echo json_encode(['message' => 'Login successful', 'token' => $token]);
    } catch (PDOException $e) {
        log_message("API: Login failed for publicKey=$publicKey: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
        echo json_encode(['message' => 'Login failed']);
        exit;
    }
} else {
    log_message("API: Invalid action: action=$action", 'acc_auth.txt', 'accounts', 'ERROR');
    echo json_encode(['message' => 'Invalid action']);
}
?>
