<?php
// accounts/include/acc-api.php
header('Content-Type: application/json');
require_once 'auth.php'; // Cập nhật đường dẫn

$auth = new Auth();
$data = json_decode(file_get_contents('php://input'), true);

$action = $data['action'] ?? '';
$publicKey = $data['publicKey'] ?? '';
$message = $data['message'] ?? '';
$signature = $data['signature'] ?? '';

if (empty($action) || empty($publicKey) || empty($message) || empty($signature)) {
    echo json_encode(['message' => 'Invalid input']);
    exit;
}

// Xác minh chữ ký
if (!$auth->verifySignature($publicKey, $message, $signature)) {
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

// Xử lý theo hành động
if ($action === 'register') {
    // Kiểm tra xem publicKey đã tồn tại chưa
    $stmt = $auth->pdo->prepare('SELECT id FROM users WHERE public_key = ?');
    $stmt->execute([$publicKey]);
    if ($stmt->fetch()) {
        echo json_encode(['message' => 'User already exists']);
        exit;
    }

    // Lưu tài khoản vào database
    $stmt = $auth->pdo->prepare('INSERT INTO users (public_key) VALUES (?)');
    $stmt->execute([$publicKey]);

    echo json_encode(['message' => 'Registration successful']);
} elseif ($action === 'login') {
    // Kiểm tra xem publicKey có tồn tại không
    $stmt = $auth->pdo->prepare('SELECT id FROM users WHERE public_key = ?');
    $stmt->execute([$publicKey]);
    if (!$stmt->fetch()) {
        echo json_encode(['message' => 'User not found']);
        exit;
    }

    // Tạo JWT
    $token = $auth->createJWT($publicKey);
    echo json_encode(['message' => 'Login successful', 'token' => $token]);
} else {
    echo json_encode(['message' => 'Invalid action']);
}
?>
