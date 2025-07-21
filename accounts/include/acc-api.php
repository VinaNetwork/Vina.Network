<?php
// accounts/include/acc-api.php
require_once '../../config/bootstrap.php';
require_once 'auth.php';
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.vina.network');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("API: Method not allowed", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    log_message("API: Invalid JSON input", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON input']);
    exit;
}

$action = $data['action'] ?? '';
$publicKey = $data['publicKey'] ?? '';
$message = $data['message'] ?? '';
$signature = $data['signature'] ?? '';
$csrf_token = $data['csrf_token'] ?? '';

log_message("API: Received request action=$action, publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');

if (!validate_csrf_token($csrf_token)) {
    log_message("API: Invalid CSRF token", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(403);
    echo json_encode(['message' => 'Invalid CSRF token']);
    exit;
}

if (empty($action) || empty($publicKey) || empty($message) || empty($signature)) {
    log_message("API: Invalid input for action=$action", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['message' => 'Invalid input']);
    exit;
}

$auth = new Auth();
if (!$auth->verifySignature($publicKey, $message, $signature)) {
    log_message("API: Invalid signature for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(401);
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

if ($action === 'register') {
    try {
        $stmt = $auth->pdo->prepare('SELECT id FROM users WHERE public_key = ?');
        $stmt->execute([$publicKey]);
        if ($stmt->fetch()) {
            log_message("API: Registration failed - User already exists: publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'ERROR');
            http_response_code(400);
            echo json_encode(['message' => 'User already exists']);
            exit;
        }

        $stmt = $auth->pdo->prepare('INSERT INTO users (public_key, created_at, last_login) VALUES (?, NOW(), NOW())');
        $stmt->execute([$publicKey]);
        log_message("API: Registration successful for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');
        echo json_encode(['message' => 'Registration successful']);
    } catch (PDOException $e) {
        log_message("API: Registration failed for publicKey=$publicKey: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
        http_response_code(500);
        echo json_encode(['message' => 'Registration failed']);
        exit;
    }
} elseif ($action === 'login') {
    try {
        $stmt = $auth->pdo->prepare('SELECT id FROM users WHERE public_key = ?');
        $stmt->execute([$publicKey]);
        if (!$stmt->fetch()) {
            log_message("API: Login failed - User not found: publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'ERROR');
            http_response_code(404);
            echo json_encode(['message' => 'User not found']);
            exit;
        }

        $stmt = $auth->pdo->prepare('UPDATE users SET last_login = NOW() WHERE public_key = ?');
        $stmt->execute([$publicKey]);

        $token = $auth->createJWT($publicKey);
        $balance = $auth->getBalance($publicKey);
        log_message("API: Login successful for publicKey=$publicKey", 'acc_auth.txt', 'accounts', 'INFO');
        echo json_encode(['message' => 'Login successful', 'token' => $token, 'balance' => $balance]);
    } catch (PDOException $e) {
        log_message("API: Login failed for publicKey=$publicKey: " . $e->getMessage(), 'acc_auth.txt', 'accounts', 'ERROR');
        http_response_code(500);
        echo json_encode(['message' => 'Login failed']);
        exit;
    }
} else {
    log_message("API: Invalid action: action=$action", 'acc_auth.txt', 'accounts', 'ERROR');
    http_response_code(400);
    echo json_encode(['message' => 'Invalid action']);
}
?>
