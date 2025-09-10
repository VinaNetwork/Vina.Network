<?php
// ============================================================================
// File: mm/private-key/delete-private-key.php
// Description: API for deleting private key.
// Created by: Vina Network
// ============================================================================

$root_path = __DIR__ . '/../../';
require_once $root_path . 'mm/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not supported']);
    exit;
}

csrf_protect();
$pdo = get_db_connection();

// Lấy public_key từ session
$public_key = $_SESSION['public_key'] ?? null;
$walletId = $_POST['walletId'] ?? null;

// Kiểm tra public_key và walletId
if (!$public_key) {
    log_message("Missing public_key in session, redirecting to login", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Session expired, please log in again']);
    exit;
}
if (!$walletId) {
    log_message("Missing walletId in POST data", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid wallet ID']);
    exit;
}

// Lấy user_id từ public_key
try {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE public_key = ?");
    $stmt->execute([$public_key]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        log_message("No account found for public_key: $public_key", 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        exit;
    }
    $user_id = $account['id'];
} catch (PDOException $e) {
    log_message("Account query error: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Account query error']);
    exit;
}

// Xóa private key
try {
    $stmt = $pdo->prepare("DELETE FROM private_key WHERE id = ? AND user_id = ?");
    $stmt->execute([$walletId, $user_id]);
    if ($stmt->rowCount() > 0) {
        log_message("Deleted wallet ID=$walletId for user_id=$user_id", 'private-key-page.log', 'make-market', 'INFO');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Wallet deleted successfully']);
    } else {
        log_message("Wallet ID=$walletId not found for user_id=$user_id", 'private-key-page.log', 'make-market', 'ERROR');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Wallet not found']);
    }
} catch (PDOException $e) {
    log_message("Error deleting wallet: {$e->getMessage()}", 'private-key-page.log', 'make-market', 'ERROR');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error deleting wallet']);
}
exit;
?>
